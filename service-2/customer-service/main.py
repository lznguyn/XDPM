"""
MuTraPro - Customer Microservice
Manages customer profiles, service requests, order tracking, and payments
Now uses auth-service API instead of JSON files
"""
import os
import uuid
from datetime import datetime
from typing import List, Optional
from enum import Enum

from fastapi import FastAPI, HTTPException, File, UploadFile, Form
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, EmailStr
import uvicorn
from db_client import db_client

# ============================================================================
# Enums
# ============================================================================
class ServiceType(str, Enum):
    TRANSCRIPTION = "transcription"
    ARRANGEMENT = "arrangement"
    RECORDING = "recording"

class RequestStatus(str, Enum):
    SUBMITTED = "submitted"
    ASSIGNED = "assigned"
    IN_PROGRESS = "in_progress"
    PENDING_REVIEW = "pending_review"
    COMPLETED = "completed"
    REVISION_REQUESTED = "revision_requested"
    CANCELLED = "cancelled"

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
        customers = await db_client.get_all_customers()
        # Convert to expected format
        return [{
            "id": str(c["id"]),
            "name": c["name"],
            "email": c["email"],
            "phone": c.get("phone"),
            "address": c.get("address"),
            "account_created": c["account_created"],
            "is_active": c["is_active"]
        } for c in customers]
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/customers/{customer_id}")
async def get_customer(customer_id: str):
    """Get customer profile by ID"""
    try:
        customer = await db_client.get_customer(int(customer_id))
        if not customer:
            raise HTTPException(status_code=404, detail="Customer not found")
        
        return {
            "id": str(customer["id"]),
            "name": customer["name"],
            "email": customer["email"],
            "phone": customer.get("phone"),
            "address": customer.get("address"),
            "account_created": customer["account_created"],
            "is_active": customer["is_active"]
        }
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.put("/customers/{customer_id}")
async def update_customer(customer_id: str, name: Optional[str] = Form(None), phone: Optional[str] = Form(None), address: Optional[str] = Form(None)):
    """Update customer profile"""
    try:
        customer = await db_client.update_customer(int(customer_id), name, phone, address)
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
        request = await db_client.create_service_request(
            customer_id=int(customer_id),
            service_type=service_type.value,
            title=title,
            description=description,
            file_name=file_name,
            due_date=due_date_parsed.isoformat() if due_date_parsed else None,
            priority="normal"
        )
        
        return {
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
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/requests/customer/{customer_id}")
async def get_customer_requests(customer_id: str):
    """Get all service requests for a customer"""
    try:
        requests = await db_client.get_customer_requests(int(customer_id))
        if not requests:
            raise HTTPException(status_code=404, detail="No requests found")
        
        return [{
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
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/requests/{request_id}")
async def get_request_details(request_id: str):
    """Get service request details"""
    try:
        request = await db_client.get_service_request(int(request_id))
        if not request:
            raise HTTPException(status_code=404, detail="Request not found")
        
        return {
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
            "submitted": "Submitted",
            "assigned": "Assigned",
            "in_progress": "InProgress",
            "pending_review": "PendingReview",
            "completed": "Completed",
            "revision_requested": "RevisionRequested",
            "cancelled": "Cancelled"
        }
        db_status = status_map.get(status_str, status_str.capitalize())
        
        request = await db_client.update_request_status(int(request_id), db_status)
        return {
            "id": str(request["id"]),
            "status": request["status"]
        }
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
    """Process payment"""
    import httpx
    import os
    
    try:
        # Tạo payment record
        payment = await db_client.create_payment(
            customer_id=int(customer_id),
            service_request_id=int(service_request_id),
            amount=amount,
            payment_method=payment_method
        )
        
        # Sau khi tạo payment thành công, cập nhật paid status của service request
        try:
            auth_service_url = os.getenv("AUTH_SERVICE_URL", "http://auth-service:8081")
            async with httpx.AsyncClient() as client:
                # Cập nhật paid status qua endpoint PATCH
                update_response = await client.patch(
                    f"{auth_service_url}/api/Customer/requests/{service_request_id}/paid",
                    json={"paid": True},
                    timeout=10.0
                )
                if update_response.status_code == 200:
                    print(f"[PAYMENT] Successfully updated paid status for request {service_request_id}")
                else:
                    print(f"[PAYMENT] Warning: Could not update request paid status: {update_response.status_code} - {update_response.text}")
        except Exception as e:
            # Log error nhưng không fail payment
            print(f"[PAYMENT] Warning: Could not update request paid status: {e}")
        
        return {
            "id": str(payment["id"]),
            "customer_id": str(payment["customer_id"]),
            "service_request_id": str(payment["service_request_id"]),
            "amount": float(payment["amount"]),
            "payment_method": payment["payment_method"],
            "status": payment["payment_status"],
            "payment_date": payment["payment_date"],
            "transaction_id": payment.get("transaction_id")
        }
    except Exception as e:
        print(f"[PAYMENT ERROR] {str(e)}")
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
        bank_account = os.getenv("BANK_ACCOUNT", "1234567890")  # Số tài khoản ngân hàng
        bank_code = os.getenv("BANK_CODE", "970422")  # Mã ngân hàng (970422 = Techcombank)
        bank_name = os.getenv("BANK_NAME", "Ngân hàng Techcombank")
        
        # Format nội dung chuyển khoản
        content = f"Thanh toan don hang {request_id}"
        
        # Tạo chuỗi VietQR theo format đơn giản
        # Format: bank_account|bank_code|amount|content
        # Có thể mở rộng để dùng format EMV QR Code nếu cần
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
        transactions = await db_client.get_customer_transactions(int(customer_id))
        if not transactions:
            raise HTTPException(status_code=404, detail="No transactions found")
        
        return [{
            "id": str(t["id"]),
            "customer_id": str(t["customer_id"]),
            "description": t["description"],
            "amount": float(t["amount"]),
            "transaction_type": t["transaction_type"],
            "date": t["date"],
            "payment_id": str(t["payment_id"]) if t.get("payment_id") else None
        } for t in transactions]
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
