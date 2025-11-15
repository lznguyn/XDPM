"""
MuTraPro - Customer Microservice
Manages customer profiles, service requests, order tracking, and payments
"""
import os
import json
import uuid
from datetime import datetime
from typing import List, Optional
from enum import Enum

from fastapi import FastAPI, HTTPException, File, UploadFile, Form
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, EmailStr
import uvicorn

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
# Database (Simple JSON files - can be replaced with DB later)
# ============================================================================
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(BASE_DIR, "data")
os.makedirs(DATA_DIR, exist_ok=True)

CUSTOMERS_FILE = os.path.join(DATA_DIR, "customers.json")
REQUESTS_FILE = os.path.join(DATA_DIR, "requests.json")
PAYMENTS_FILE = os.path.join(DATA_DIR, "payments.json")
TRANSACTIONS_FILE = os.path.join(DATA_DIR, "transactions.json")
FEEDBACKS_FILE = os.path.join(DATA_DIR, "feedbacks.json")

def load_json(file_path):
    """Load JSON file or return empty list"""
    if os.path.exists(file_path):
        with open(file_path, 'r') as f:
            return json.load(f)
    return []

def save_json(file_path, data):
    """Save data to JSON file"""
    with open(file_path, 'w') as f:
        json.dump(data, f, indent=2)

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
def create_customer(name: str = Form(...), email: str = Form(...), phone: Optional[str] = Form(None), address: Optional[str] = Form(None)):
    """Create new customer account"""
    customers = load_json(CUSTOMERS_FILE)
    
    # Check if email already exists
    if any(c["email"] == email for c in customers):
        raise HTTPException(status_code=400, detail="Email already exists")
    
    customer = {
        "id": str(uuid.uuid4()),
        "name": name,
        "email": email,
        "phone": phone,
        "address": address,
        "account_created": datetime.now().isoformat(),
        "is_active": True
    }
    
    customers.append(customer)
    save_json(CUSTOMERS_FILE, customers)
    
    return customer

@app.get("/customers")
def list_customers():
    """Get all customers"""
    return load_json(CUSTOMERS_FILE)

@app.get("/customers/{customer_id}")
def get_customer(customer_id: str):
    """Get customer profile by ID"""
    customers = load_json(CUSTOMERS_FILE)
    customer = next((c for c in customers if c["id"] == customer_id), None)
    
    if not customer:
        raise HTTPException(status_code=404, detail="Customer not found")
    
    return customer

@app.put("/customers/{customer_id}")
def update_customer(customer_id: str, name: Optional[str] = Form(None), phone: Optional[str] = Form(None), address: Optional[str] = Form(None)):
    """Update customer profile"""
    customers = load_json(CUSTOMERS_FILE)
    customer = next((c for c in customers if c["id"] == customer_id), None)
    
    if not customer:
        raise HTTPException(status_code=404, detail="Customer not found")
    
    if name:
        customer["name"] = name
    if phone:
        customer["phone"] = phone
    if address:
        customer["address"] = address
    
    save_json(CUSTOMERS_FILE, customers)
    return customer

# ============================================================================
# SERVICE REQUEST ENDPOINTS
# ============================================================================
@app.post("/requests")
def create_service_request(
    customer_id: str = Form(...),
    service_type: ServiceType = Form(...),
    title: str = Form(...),
    description: Optional[str] = Form(None),
    due_date: Optional[str] = Form(None),
    file: Optional[UploadFile] = File(None)
):
    """Submit new service request (transcription, arrangement, recording)"""
    # Verify customer exists
    customers = load_json(CUSTOMERS_FILE)
    if not any(c["id"] == customer_id for c in customers):
        raise HTTPException(status_code=404, detail="Customer not found")
    
    request_id = str(uuid.uuid4())
    file_name = None
    
    # Save uploaded file if provided
    if file:
        file_name = f"{request_id}_{file.filename}"
        uploads_dir = os.path.join(BASE_DIR, "uploads")
        os.makedirs(uploads_dir, exist_ok=True)
        
        with open(os.path.join(uploads_dir, file_name), 'wb') as f:
            f.write(file.file.read())
    
    service_request = {
        "id": request_id,
        "customer_id": customer_id,
        "service_type": service_type.value,
        "title": title,
        "description": description,
        "file_name": file_name,
        "status": RequestStatus.SUBMITTED.value,
        "created_date": datetime.now().isoformat(),
        "due_date": due_date,
        "assigned_specialist": None,
        "priority": "normal",
        "paid": False
    }
    
    requests = load_json(REQUESTS_FILE)
    requests.append(service_request)
    save_json(REQUESTS_FILE, requests)
    
    return service_request

@app.get("/requests/customer/{customer_id}")
def get_customer_requests(customer_id: str):
    """Get all service requests for a customer"""
    requests = load_json(REQUESTS_FILE)
    customer_requests = [r for r in requests if r["customer_id"] == customer_id]
    
    if not customer_requests:
        raise HTTPException(status_code=404, detail="No requests found")
    
    return customer_requests

@app.get("/requests/{request_id}")
def get_request_details(request_id: str):
    """Get service request details"""
    requests = load_json(REQUESTS_FILE)
    request = next((r for r in requests if r["id"] == request_id), None)
    
    if not request:
        raise HTTPException(status_code=404, detail="Request not found")
    
    return request

@app.put("/requests/{request_id}/status")
def update_request_status(request_id: str, status: RequestStatus = Form(...)):
    """Update service request status"""
    requests = load_json(REQUESTS_FILE)
    request = next((r for r in requests if r["id"] == request_id), None)
    
    if not request:
        raise HTTPException(status_code=404, detail="Request not found")
    
    request["status"] = status.value
    save_json(REQUESTS_FILE, requests)
    
    return request

# ============================================================================
# FEEDBACK & REVISION ENDPOINTS
# ============================================================================
@app.post("/feedback")
def submit_feedback(
    request_id: str = Form(...),
    content: str = Form(...),
    feedback_type: str = Form("revision")
):
    """Submit feedback or request revision"""
    requests_data = load_json(REQUESTS_FILE)
    if not any(r["id"] == request_id for r in requests_data):
        raise HTTPException(status_code=404, detail="Request not found")
    
    feedback = {
        "id": str(uuid.uuid4()),
        "request_id": request_id,
        "content": content,
        "feedback_type": feedback_type,
        "created_date": datetime.now().isoformat()
    }
    
    # If revision requested, update request status
    if feedback_type == "revision":
        request = next(r for r in requests_data if r["id"] == request_id)
        request["status"] = RequestStatus.REVISION_REQUESTED.value
        save_json(REQUESTS_FILE, requests_data)
    
    # Save feedback to file
    feedbacks = load_json(FEEDBACKS_FILE)
    feedbacks.append(feedback)
    save_json(FEEDBACKS_FILE, feedbacks)
    
    return feedback

@app.get("/feedback/request/{request_id}")
def get_feedback_by_request(request_id: str):
    """Get all feedback for a specific request"""
    feedbacks = load_json(FEEDBACKS_FILE)
    request_feedbacks = [f for f in feedbacks if f["request_id"] == request_id]
    return request_feedbacks

@app.get("/feedback/customer/{customer_id}")
def get_feedback_by_customer(customer_id: str):
    """Get all feedback for a customer's requests"""
    requests_data = load_json(REQUESTS_FILE)
    customer_request_ids = [r["id"] for r in requests_data if r["customer_id"] == customer_id]
    
    feedbacks = load_json(FEEDBACKS_FILE)
    customer_feedbacks = [f for f in feedbacks if f["request_id"] in customer_request_ids]
    return customer_feedbacks

# ============================================================================
# PAYMENT & TRANSACTION ENDPOINTS
# ============================================================================
@app.post("/payments")
def create_payment(
    customer_id: str = Form(...),
    service_request_id: str = Form(...),
    amount: float = Form(...),
    payment_method: str = Form(...)
):
    """Process payment"""
    # Verify customer exists
    customers = load_json(CUSTOMERS_FILE)
    if not any(c["id"] == customer_id for c in customers):
        raise HTTPException(status_code=404, detail="Customer not found")
    
    payment = {
        "id": str(uuid.uuid4()),
        "customer_id": customer_id,
        "service_request_id": service_request_id,
        "amount": amount,
        "payment_method": payment_method,
        "status": PaymentStatus.COMPLETED.value,
        "payment_date": datetime.now().isoformat(),
        "transaction_id": str(uuid.uuid4())
    }
    
    payments = load_json(PAYMENTS_FILE)
    payments.append(payment)
    save_json(PAYMENTS_FILE, payments)
    
    # Mark request as paid
    requests_data = load_json(REQUESTS_FILE)
    request_obj = next((r for r in requests_data if r["id"] == service_request_id), None)
    if request_obj:
        request_obj["paid"] = True
        save_json(REQUESTS_FILE, requests_data)
    
    # Record transaction
    transaction = {
        "id": str(uuid.uuid4()),
        "customer_id": customer_id,
        "description": f"Payment for {service_request_id}",
        "amount": amount,
        "transaction_type": "payment",
        "date": datetime.now().isoformat(),
        "payment_id": payment["id"],
        "request_id": service_request_id,
        "status": "completed"
    }
    
    transactions = load_json(TRANSACTIONS_FILE)
    transactions.append(transaction)
    save_json(TRANSACTIONS_FILE, transactions)
    
    return payment

@app.get("/transactions/{customer_id}")
def get_customer_transactions(customer_id: str):
    """Get transaction history for customer"""
    transactions = load_json(TRANSACTIONS_FILE)
    customer_transactions = [t for t in transactions if t["customer_id"] == customer_id]
    
    if not customer_transactions:
        raise HTTPException(status_code=404, detail="No transactions found")
    
    return customer_transactions

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
