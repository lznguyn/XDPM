"""
MuTraPro - Customer Microservice
Manages customer profiles, service requests, order tracking, and payments
Now uses auth-service API instead of JSON files
"""
import os
import uuid
from datetime import datetime, timezone, timedelta
from typing import List, Optional
from enum import Enum

from fastapi import FastAPI, HTTPException, File, UploadFile, Form
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, EmailStr
import uvicorn
import httpx
import logging
from db_client import db_client
from redis_client import redis_cache
from mqtt_client import mqtt_client

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# ============================================================================
# Timezone Configuration (UTC+7 - Vietnam Time)
# ============================================================================
VIETNAM_TZ = timezone(timedelta(hours=7))

def get_vietnam_time() -> datetime:
    """Lấy thời gian hiện tại theo timezone UTC+7 (Vietnam Time)"""
    return datetime.now(VIETNAM_TZ)

def format_vietnam_datetime(dt: datetime, format_str: str = "%Y-%m-%d %H:%M:%S") -> str:
    """Format datetime theo timezone UTC+7"""
    if dt.tzinfo is None:
        # Nếu không có timezone, giả sử là UTC
        dt = dt.replace(tzinfo=timezone.utc)
    vietnam_dt = dt.astimezone(VIETNAM_TZ)
    return vietnam_dt.strftime(format_str)

# ============================================================================
# Enums
# ============================================================================
class ServiceType(str, Enum):
    TRANSCRIPTION = "transcription"
    ARRANGEMENT = "arrangement"
    RECORDING = "recording"

class RequestStatus(str, Enum):
    REQUESTED = "requested"
    PENDING_REVIEW = "pending_review"
    CANCELLED = "cancelled"
    PENDING_MEETING_CONFIRMATION = "pending_meeting_confirmation"
    COMPLETED = "completed"
    REJECTED_BY_EXPERT = "rejected_by_expert"
    # Legacy statuses (kept for backward compatibility)
    SUBMITTED = "submitted"
    ASSIGNED = "assigned"
    IN_PROGRESS = "in_progress"
    REVISION_REQUESTED = "revision_requested"

class PaymentStatus(str, Enum):
    PENDING = "pending"
    COMPLETED = "completed"
    FAILED = "failed"
    REFUNDED = "refunded"

# ============================================================================
# Models (Pydantic)
# ============================================================================
class CustomerProfile(BaseModel):
    """Customer account and profile"""
    id: str
    name: str
    email: str
    phone: Optional[str] = None
    address: Optional[str] = None
    account_created: str
    is_active: bool = True

class ServiceRequest(BaseModel):
    """Customer service request (transcription, arrangement, recording)"""
    id: str
    customer_id: str
    service_type: ServiceType
    title: str
    description: Optional[str] = None
    file_name: Optional[str] = None
    status: RequestStatus
    created_date: str
    due_date: Optional[str] = None
    assigned_specialist: Optional[str] = None
    priority: str = "normal"  # normal, high, urgent

class Feedback(BaseModel):
    """Customer feedback or revision request"""
    id: str
    request_id: str
    feedback_text: str
    revision_needed: bool = False
    created_date: str

class Payment(BaseModel):
    """Payment record"""
    id: str
    customer_id: str
    service_request_id: str
    amount: float
    payment_method: str
    status: PaymentStatus
    payment_date: str
    transaction_id: Optional[str] = None

class Transaction(BaseModel):
    """Transaction history"""
    id: str
    customer_id: str
    description: str
    amount: float
    transaction_type: str  # payment, refund, credit
    date: str

class StudioCreate(BaseModel):
    """Studio creation request"""
    name: str
    location: str
    price: float
    status: int = 0  # 0=Available, 1=Occupied, 2=UnderMaintenance
    image: Optional[str] = None

class StudioUpdate(BaseModel):
    """Studio update request"""
    name: Optional[str] = None
    location: Optional[str] = None
    price: Optional[float] = None
    status: Optional[int] = None
    image: Optional[str] = None

# ============================================================================
# File Upload Directory
# ============================================================================
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
UPLOADS_DIR = os.path.join(BASE_DIR, "uploads")
os.makedirs(UPLOADS_DIR, exist_ok=True)

# ============================================================================
# FastAPI Setup
# ============================================================================
app = FastAPI(title="MuTraPro - Customer Service")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

# ============================================================================
# Startup and Shutdown Events
# ============================================================================
@app.on_event("startup")
async def startup_event():
    """Initialize Redis cache and MQTT client on startup"""
    redis_cache.connect()
    mqtt_client.connect()
    logger.info("Customer Service started")

@app.on_event("shutdown")
async def shutdown_event():
    """Cleanup on shutdown"""
    mqtt_client.disconnect()
    logger.info("Customer Service stopped")

# ============================================================================
# CUSTOMER ENDPOINTS
# ============================================================================
@app.post("/customers")
async def create_customer(name: str = Form(...), email: str = Form(...), phone: Optional[str] = Form(None), address: Optional[str] = Form(None)):
    """Create new customer account"""
    try:
        customer = await db_client.create_customer(name, email, phone, address)
        # Convert to expected format
        return {
            "id": str(customer["id"]),
            "name": customer["name"],
            "email": customer["email"],
            "phone": customer.get("phone"),
            "address": customer.get("address"),
            "account_created": customer["account_created"],
            "is_active": customer["is_active"]
        }
    except Exception as e:
        raise HTTPException(status_code=400, detail=str(e))

@app.get("/customers")
async def list_customers():
    """Get all customers"""
    try:
        # Try to get from cache first
        cache_key = "customers:all"
        cached = redis_cache.get(cache_key)
        if cached:
            logger.debug("Cache hit for all customers")
            return cached

        customers = await db_client.get_all_customers()
        # Convert to expected format
        result = [{
            "id": str(c["id"]),
            "name": c["name"],
            "email": c["email"],
            "phone": c.get("phone"),
            "address": c.get("address"),
            "account_created": c["account_created"],
            "is_active": c["is_active"]
        } for c in customers]

        # Store in cache with 15 minutes TTL
        redis_cache.set(cache_key, result, ttl=900)
        return result
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/customers/{customer_id}")
async def get_customer(customer_id: str):
    """Get customer profile by ID"""
    try:
        # Try to get from cache first
        cache_key = f"customer:{customer_id}"
        cached = redis_cache.get(cache_key)
        if cached:
            logger.debug(f"Cache hit for customer {customer_id}")
            return cached

        customer = await db_client.get_customer(int(customer_id))
        if not customer:
            raise HTTPException(status_code=404, detail="Customer not found")
        
        result = {
            "id": str(customer["id"]),
            "name": customer["name"],
            "email": customer["email"],
            "phone": customer.get("phone"),
            "address": customer.get("address"),
            "account_created": customer["account_created"],
            "is_active": customer["is_active"]
        }

        # Store in cache with 1 hour TTL
        redis_cache.set(cache_key, result, ttl=3600)
        return result
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.put("/customers/{customer_id}")
async def update_customer(customer_id: str, name: Optional[str] = Form(None), phone: Optional[str] = Form(None), address: Optional[str] = Form(None)):
    """Update customer profile"""
    try:
        customer = await db_client.update_customer(int(customer_id), name, phone, address)
        result = {
            "id": str(customer["id"]),
            "name": customer["name"],
            "email": customer["email"],
            "phone": customer.get("phone"),
            "address": customer.get("address"),
            "account_created": customer["account_created"],
            "is_active": customer["is_active"]
        }

        # Invalidate cache
        redis_cache.delete(f"customer:{customer_id}")
        redis_cache.delete_pattern("customers:*")

        # Publish MQTT notification
        mqtt_client.publish(f'customer/{customer_id}/updated', {
            'customerId': customer_id,
            'timestamp': get_vietnam_time().isoformat(),
        })

        return result
    except Exception as e:
        raise HTTPException(status_code=404, detail=str(e))

# ============================================================================
# SERVICE REQUEST ENDPOINTS
# ============================================================================
@app.post("/requests")
async def create_service_request(
    customer_id: str = Form(...),
    service_type: ServiceType = Form(...),
    title: str = Form(...),
    description: Optional[str] = Form(None),
    due_date: Optional[str] = Form(None),
    status: Optional[str] = Form("pending"), 
    file: Optional[UploadFile] = File(None)
):
    """Submit new service request (transcription, arrangement, recording)"""
    try:
        # Verify customer exists
        customer = await db_client.get_customer(int(customer_id))
        if not customer:
            raise HTTPException(status_code=404, detail="Customer not found")
        
        file_name = None
        
        # Save uploaded file if provided
        if file:
            file_name = f"{uuid.uuid4()}_{file.filename}"
            file_path = os.path.join(UPLOADS_DIR, file_name)
            with open(file_path, 'wb') as f:
                content = await file.read()
                f.write(content)
        
        # Parse due_date if provided
        due_date_parsed = None
        if due_date:
            try:
                due_date_parsed = datetime.fromisoformat(due_date.replace('Z', '+00:00'))
            except:
                pass
        
        # Create service request via API
        # service_type.value will be "recording", "transcription", or "arrangement" (lowercase)
        # But C# enum expects capitalized version, so convert it
        service_type_str = service_type.value.capitalize()  # "recording" -> "Recording"
        
        request = await db_client.create_service_request(
            customer_id=int(customer_id),
            service_type=service_type_str,
            title=title,
            description=description,
            file_name=file_name,
            due_date=due_date_parsed.isoformat() if due_date_parsed else None,
            priority="normal",
            status=status  # Truyền status từ frontend
        )
        
        result = {
            "id": str(request["id"]),
            "customer_id": str(request["customer_id"]),
            "service_type": request["service_type"],
            "title": request["title"],
            "description": request.get("description"),
            "file_name": request.get("file_name"),
            "status": request["status"],
            "created_date": request["created_date"],
            "due_date": request.get("due_date"),
            "assigned_specialist": str(request.get("assigned_specialist_id")) if request.get("assigned_specialist_id") else None,
            "priority": request.get("priority", "normal"),
            "paid": request.get("paid", False)
        }

        # Invalidate cache
        redis_cache.delete_pattern(f"request:customer:{customer_id}*")
        redis_cache.delete(f"request:{result['id']}")

        # Publish MQTT notification
        mqtt_client.publish('customer/request/created', {
            'requestId': result['id'],
            'customerId': result['customer_id'],
            'serviceType': result['service_type'],
            'title': result['title'],
            'status': result['status'],
            'timestamp': get_vietnam_time().isoformat(),
        })

        return result
    except HTTPException:
        raise
    except httpx.HTTPStatusError as e:
        # Forward HTTP errors from auth-service with more details
        error_detail = str(e)
        try:
            error_json = e.response.json()
            error_detail = error_json.get("message") or error_json.get("detail") or str(error_json)
        except:
            error_detail = e.response.text[:500] if e.response.text else str(e)
        raise HTTPException(status_code=e.response.status_code, detail=error_detail)
    except Exception as e:
        import traceback
        print(f"Unexpected error in create_service_request: {e}")
        print(traceback.format_exc())
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/requests/customer/{customer_id}")
async def get_customer_requests(customer_id: str):
    """Get all service requests for a customer"""
    try:
        # Try to get from cache first
        cache_key = f"request:customer:{customer_id}"
        cached = redis_cache.get(cache_key)
        if cached:
            logger.debug(f"Cache hit for customer requests {customer_id}")
            return cached

        requests = await db_client.get_customer_requests(int(customer_id))
        if not requests:
            raise HTTPException(status_code=404, detail="No requests found")
        
        result = [{
            "id": str(r["id"]),
            "customer_id": str(r["customer_id"]),
            "service_type": r["service_type"],
            "title": r["title"],
            "description": r.get("description"),
            "file_name": r.get("file_name"),
            "status": r["status"],
            "created_date": r["created_date"],
            "due_date": r.get("due_date"),
            "assigned_specialist": str(r.get("assigned_specialist_id")) if r.get("assigned_specialist_id") else None,
            "priority": r.get("priority", "normal"),
            "paid": r.get("paid", False)
        } for r in requests]

        # Store in cache with 30 minutes TTL
        redis_cache.set(cache_key, result, ttl=1800)
        return result
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/requests/{request_id}")
async def get_request_details(request_id: str):
    """Get service request details"""
    try:
        # Try to get from cache first
        cache_key = f"request:{request_id}"
        cached = redis_cache.get(cache_key)
        if cached:
            logger.debug(f"Cache hit for request {request_id}")
            return cached

        request = await db_client.get_service_request(int(request_id))
        if not request:
            raise HTTPException(status_code=404, detail="Request not found")
        
        result = {
            "id": str(request["id"]),
            "customer_id": str(request["customer_id"]),
            "service_type": request["service_type"],
            "title": request["title"],
            "description": request.get("description"),
            "file_name": request.get("file_name"),
            "status": request["status"],
            "created_date": request["created_date"],
            "due_date": request.get("due_date"),
            "assigned_specialist": str(request.get("assigned_specialist_id")) if request.get("assigned_specialist_id") else None,
            "priority": request.get("priority", "normal"),
            "paid": request.get("paid", False)
        }

        # Store in cache with 30 minutes TTL
        redis_cache.set(cache_key, result, ttl=1800)
        return result
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.put("/requests/{request_id}/status")
async def update_request_status(request_id: str, status: RequestStatus = Form(...)):
    """Update service request status"""
    try:
        # Convert enum to string
        status_str = status.value
        # Map to database enum values
        status_map = {
            "requested": "Requested",
            "pending_review": "PendingReview",
            "cancelled": "Cancelled",
            "pending_meeting_confirmation": "PendingMeetingConfirmation",
            "completed": "Completed",
            "rejected_by_expert": "RejectedByExpert",
            # Legacy statuses
            "submitted": "Submitted",
            "assigned": "Assigned",
            "in_progress": "InProgress",
            "revision_requested": "RevisionRequested"
        }
        db_status = status_map.get(status_str, status_str.capitalize())
        
        request = await db_client.update_request_status(int(request_id), db_status)
        result = {
            "id": str(request["id"]),
            "status": request["status"]
        }

        # Invalidate cache
        redis_cache.delete(f"request:{request_id}")
        redis_cache.delete_pattern(f"request:customer:*")

        # Publish MQTT notification
        mqtt_client.publish(f'customer/request/{request_id}/status', {
            'requestId': request_id,
            'status': request["status"],
            'timestamp': get_vietnam_time().isoformat(),
        })

        return result
    except Exception as e:
        raise HTTPException(status_code=404, detail=str(e))

# ============================================================================
# FEEDBACK & REVISION ENDPOINTS
# ============================================================================
@app.post("/feedback")
async def submit_feedback(
    request_id: str = Form(...),
    content: str = Form(...),
    feedback_type: str = Form("revision")
):
    """Submit feedback or request revision"""
    try:
        revision_needed = (feedback_type == "revision")
        feedback = await db_client.create_feedback(
            request_id=int(request_id),
            feedback_text=content,
            revision_needed=revision_needed
        )
        
        return {
            "id": str(feedback["id"]),
            "request_id": str(feedback["request_id"]),
            "feedback_text": feedback["feedback_text"],
            "revision_needed": feedback["revision_needed"],
            "created_date": feedback["created_date"]
        }
    except Exception as e:
        raise HTTPException(status_code=404, detail=str(e))

# ============================================================================
# PAYMENT & TRANSACTION ENDPOINTS
# ============================================================================
@app.post("/payments")
async def create_payment(
    customer_id: str = Form(...),
    service_request_id: str = Form(...),
    amount: float = Form(...),
    payment_method: str = Form(...)
):
    """Process payment - Tạo payment trong service-3 (payment-service)"""
    import httpx
    import os
    
    try:
        # Gọi service-3 (payment-service) để tạo payment
        payment_service_url = os.getenv("PAYMENT_SERVICE_URL", "http://kong:8000/api/payments")
        
        # Map payment method từ format service-2 sang format service-3
        method_map = {
            "BANK_TRANSFER": "BANK_TRANSFER",
            "CHUYEN_KHOAN": "BANK_TRANSFER",
            "CREDIT_CARD": "CREDIT_CARD",
            "THE_TIN_DUNG": "CREDIT_CARD",
            "MOMO": "MOMO",
            "VI_DIEN_TU": "MOMO",
            "CASH": "CASH",
            "TIEN_MAT": "CASH"
        }
        mapped_method = method_map.get(payment_method.upper(), payment_method.upper())
        
        payment_data = {
            "orderId": str(service_request_id),
            "customerId": str(customer_id),
            "amount": float(amount),
            "currency": "VND",
            "method": mapped_method
        }
        
        async with httpx.AsyncClient(timeout=30.0) as client:
            # Tạo payment trong service-3
            payment_response = await client.post(
                payment_service_url,
                json=payment_data
            )
            
            if not payment_response.is_success:
                error_detail = payment_response.text
                logger.error(f"Payment service error: {payment_response.status_code} - {error_detail}")
                raise HTTPException(
                    status_code=payment_response.status_code,
                    detail=f"Lỗi khi tạo payment trong payment-service: {error_detail}"
                )
            
            payment_result = payment_response.json()
            payment_id = payment_result.get("id")
            
            # Xác nhận payment ngay (trong môi trường thực tế, cần xác nhận từ ngân hàng)
            if payment_id:
                try:
                    confirm_response = await client.post(
                        f"{payment_service_url}/{payment_id}/confirm",
                        json={"result": "SUCCESS"}
                    )
                    
                    if confirm_response.is_success:
                        # Cập nhật paid status trong service-1
                        try:
                            await db_client.update_request_paid_status(int(service_request_id), True)
                            logger.info(f"Updated paid status for request {service_request_id}")
                        except Exception as paid_ex:
                            logger.warning(f"Could not update paid status: {paid_ex}")
                except Exception as confirm_ex:
                    logger.warning(f"Could not confirm payment: {confirm_ex}")
                    # Vẫn trả về success vì payment đã được tạo
        
        # Format result để tương thích với frontend
        result = {
            "id": payment_id,
            "customer_id": customer_id,
            "service_request_id": service_request_id,
            "amount": float(amount),
            "payment_method": mapped_method,
            "status": payment_result.get("status", "PENDING"),
            "payment_date": payment_result.get("createdAt", get_vietnam_time().isoformat()),
            "transaction_id": payment_id
        }

        # Invalidate cache
        redis_cache.delete_pattern(f"payment:customer:{customer_id}*")
        redis_cache.delete(f"payment:request:{service_request_id}")
        redis_cache.delete_pattern(f"transactions:customer:{customer_id}*")
        redis_cache.delete(f"request:{service_request_id}")

        # Publish MQTT notification
        mqtt_client.publish('customer/payment/created', {
            'paymentId': result['id'],
            'customerId': result['customer_id'],
            'requestId': result['service_request_id'],
            'amount': result['amount'],
            'status': result['status'],
            'timestamp': get_vietnam_time().isoformat(),
        })

        return result
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"[PAYMENT ERROR] {str(e)}")
        import traceback
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/payments/qr/{request_id}")
async def generate_payment_qr(request_id: str, amount: float = 50000):
    """Generate VietQR code for payment"""
    import qrcode
    from io import BytesIO
    import base64
    
    try:
        # Thông tin tài khoản ngân hàng (có thể cấu hình qua environment variables)
        bank_account = os.getenv("BANK_ACCOUNT", "0961991565")  # Số tài khoản ngân hàng
        bank_code = os.getenv("BANK_CODE", "970422")  # Mã ngân hàng (970422 = MBBANK - Ngân hàng Quân đội)
        bank_name = os.getenv("BANK_NAME", "Ngân hàng Quân đội (MBBANK)")
        account_name = os.getenv("ACCOUNT_NAME", "PHAN THANH AN")  # Tên chủ tài khoản
        
        # Format nội dung chuyển khoản
        content = f"Thanh toan don hang {request_id}"
        
        # Tạo chuỗi VietQR theo format EMV QR Code chuẩn
        # Format VietQR: 00020101021238570010A00000072701270006A000000727029700080000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000_string
        # Thông tin tài khoản ngân hàng (có thể cấu hình qua environment variables)
        bank_account = os.getenv("BANK_ACCOUNT", "0961991565")  # Số tài khoản ngân hàng
        bank_code = os.getenv("BANK_CODE", "970422")  # Mã ngân hàng (970422 = MBBANK - Ngân hàng Quân đội)
        bank_name = os.getenv("BANK_NAME", "Ngân hàng Quân đội (MBBANK)")
        account_name = os.getenv("ACCOUNT_NAME", "PHAN THANH AN")  # Tên chủ tài khoản
        
        # Format nội dung chuyển khoản
        content = f"Thanh toan don hang {request_id}"
        
        # Tạo chuỗi VietQR theo format EMV QR Code chuẩn
        # Format VietQR đơn giản: bank_account|bank_code|amount|content
        # Hoặc có thể dùng format EMV QR Code đầy đủ nếu cần
        qr_data = f"{bank_account}|{bank_code}|{int(amount)}|{content}"
        
        # Tạo QR code
        qr = qrcode.QRCode(
            version=1,
            error_correction=qrcode.constants.ERROR_CORRECT_L,
            box_size=10,
            border=4,
        )
        qr.add_data(qr_data)
        qr.make(fit=True)
        
        # Tạo image
        img = qr.make_image(fill_color="black", back_color="white")
        
        # Convert to base64
        buffer = BytesIO()
        img.save(buffer, format='PNG')
        img_str = base64.b64encode(buffer.getvalue()).decode()
        
        return {
            "qr_code": f"data:image/png;base64,{img_str}",
            "qr_data": qr_data,
            "bank_account": bank_account,
            "bank_code": bank_code,
            "bank_name": bank_name,
            "account_name": account_name,
            "amount": amount,
            "content": content,
            "request_id": request_id
        }
    except Exception as e:
        print(f"[QR ERROR] {str(e)}")
        import traceback
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/transactions/{customer_id}")
async def get_customer_transactions(customer_id: str):
    """Get transaction history for customer"""
    try:
        # Try to get from cache first
        cache_key = f"transactions:customer:{customer_id}"
        cached = redis_cache.get(cache_key)
        if cached:
            logger.debug(f"Cache hit for customer transactions {customer_id}")
            return cached

        transactions = await db_client.get_customer_transactions(int(customer_id))
        if not transactions:
            raise HTTPException(status_code=404, detail="No transactions found")
        
        result = [{
            "id": str(t["id"]),
            "customer_id": str(t["customer_id"]),
            "description": t["description"],
            "amount": float(t["amount"]),
            "transaction_type": t["transaction_type"],
            "date": t["date"],
            "payment_id": str(t["payment_id"]) if t.get("payment_id") else None
        } for t in transactions]

        # Store in cache with 10 minutes TTL
        redis_cache.set(cache_key, result, ttl=600)
        return result
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

# ============================================================================
# STUDIO ENDPOINTS
# ============================================================================
@app.get("/studios")
async def list_studios():
    """Get all studios"""
    try:
        print(f"[list_studios] Starting to fetch studios...")
        result = await db_client.get_all_studios()
        print(f"[list_studios] Got result: {result}")
        
        # API trả về format: { status: "success", message: "...", data: [...] }
        # hoặc { status: "error", message: "...", data: [] }
        # Luôn trả về result, ngay cả khi có lỗi (đã được handle trong db_client)
        
        # Đảm bảo result là dict
        if not isinstance(result, dict):
            print(f"[list_studios] Result is not a dict: {type(result)}")
            result = {"status": "error", "message": "Response format không hợp lệ", "data": []}
        
        # Đảm bảo có field data
        if "data" not in result:
            result["data"] = []
        
        # Đảm bảo data là list
        if not isinstance(result.get("data"), list):
            print(f"[list_studios] Data is not a list: {type(result.get('data'))}")
            result["data"] = []
        
        # Đảm bảo có field status
        if "status" not in result:
            result["status"] = "success" if result.get("data") else "error"
        
        # Đảm bảo có field message
        if "message" not in result:
            result["message"] = "Lấy danh sách studio thành công" if result.get("status") == "success" else "Có lỗi xảy ra"
        
        print(f"[list_studios] Returning result with status: {result.get('status')}, data count: {len(result.get('data', []))}")
        return result
    except Exception as e:
        import traceback
        error_msg = f"[list_studios] Error in endpoint: {str(e)}"
        error_trace = traceback.format_exc()
        print(error_msg)
        print(error_trace)
        # Trả về response hợp lệ thay vì raise exception
        return {
            "status": "error", 
            "message": f"Lỗi server: {str(e)}", 
            "data": []
        }

@app.get("/studios/{studio_id}")
async def get_studio(studio_id: str):
    """Get studio by ID"""
    try:
        result = await db_client.get_studio(int(studio_id))
        # API trả về format: { status: "success", message: "...", data: {...} }
        # hoặc { status: "error", message: "..." }
        if result.get("status") == "error":
            raise HTTPException(status_code=404, detail=result.get("message", "Studio not found"))
        return result
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/studios")
async def create_studio(studio: StudioCreate):
    """Create a new studio"""
    try:
        result = await db_client.create_studio(
            name=studio.name,
            location=studio.location,
            price=studio.price,
            status=studio.status,
            image=studio.image
        )
        # API trả về format: { status: "success", message: "...", data: {...} }
        return result
    except Exception as e:
        raise HTTPException(status_code=400, detail=str(e))

@app.put("/studios/{studio_id}")
async def update_studio(studio_id: str, studio: StudioUpdate):
    """Update studio"""
    try:
        result = await db_client.update_studio(
            studio_id=int(studio_id),
            name=studio.name,
            location=studio.location,
            price=studio.price,
            status=studio.status,
            image=studio.image
        )
        # API trả về format: { status: "success", message: "...", data: {...} }
        # hoặc { status: "error", message: "..." }
        if result.get("status") == "error":
            raise HTTPException(status_code=404, detail=result.get("message", "Studio not found"))
        return result
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.delete("/studios/{studio_id}")
async def delete_studio(studio_id: str):
    """Delete studio"""
    try:
        result = await db_client.delete_studio(int(studio_id))
        # API trả về format: { status: "success", message: "..." }
        # hoặc { status: "error", message: "..." }
        if result.get("status") == "error":
            raise HTTPException(status_code=404, detail=result.get("message", "Studio not found"))
        return result
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

# ============================================================================
# HEALTH & STATUS ENDPOINTS
# ============================================================================
@app.get("/health")
def health_check():
    return {"status": "ok", "service": "customer"}

@app.get("/ready")
def readiness_check():
    return {"status": "ready", "service": "customer"}

# ============================================================================
# MAIN
# ============================================================================
if __name__ == "__main__":
    host = os.environ.get("HOST", "0.0.0.0")
    port = int(os.environ.get("PORT", "8001"))
    reload_flag = os.environ.get("RELOAD", "false").lower() in ("1", "true", "yes")
    uvicorn.run("main:app", host=host, port=port, reload=reload_flag)
