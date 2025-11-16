"""
Database client for Customer Service
Uses HTTP API calls to auth-service instead of direct database access
"""
import os
import httpx
from typing import Optional, List, Dict, Any

AUTH_SERVICE_URL = os.environ.get("AUTH_SERVICE_URL", "http://localhost:8081")
API_BASE = f"{AUTH_SERVICE_URL}/api/Customer"

class DatabaseClient:
    """Client to interact with auth-service API"""
    
    def __init__(self):
        self.base_url = API_BASE
        self.client = httpx.AsyncClient(timeout=30.0)
    
    async def close(self):
        """Close HTTP client"""
        await self.client.aclose()
    
    # Customer operations
    async def create_customer(self, name: str, email: str, phone: Optional[str] = None, 
                             address: Optional[str] = None, user_id: Optional[int] = None) -> Dict[str, Any]:
        """Create a new customer"""
        data = {
            "name": name,
            "email": email,
            "phone": phone,
            "address": address
        }
        if user_id:
            data["userId"] = user_id
        response = await self.client.post(f"{self.base_url}", json=data)
        response.raise_for_status()
        return response.json()
    
    async def get_customer(self, customer_id: int) -> Optional[Dict[str, Any]]:
        """Get customer by ID"""
        try:
            response = await self.client.get(f"{self.base_url}/{customer_id}")
            response.raise_for_status()
            return response.json()
        except httpx.HTTPStatusError as e:
            if e.response.status_code == 404:
                return None
            raise
    
    async def get_all_customers(self) -> List[Dict[str, Any]]:
        """Get all customers"""
        response = await self.client.get(f"{self.base_url}")
        response.raise_for_status()
        return response.json()
    
    async def update_customer(self, customer_id: int, name: Optional[str] = None,
                             phone: Optional[str] = None, address: Optional[str] = None) -> Dict[str, Any]:
        """Update customer"""
        data = {}
        if name:
            data["name"] = name
        if phone:
            data["phone"] = phone
        if address:
            data["address"] = address
        
        response = await self.client.put(f"{self.base_url}/{customer_id}", json=data)
        response.raise_for_status()
        return response.json()
    
    # Service Request operations
    async def create_service_request(self, customer_id: int, service_type: str, title: str,
                                    description: Optional[str] = None, file_name: Optional[str] = None,
                                    due_date: Optional[str] = None, priority: str = "normal") -> Dict[str, Any]:
        """Create a new service request"""
        data = {
            "customerId": customer_id,
            "serviceType": service_type,
            "title": title,
            "description": description,
            "fileName": file_name,
            "dueDate": due_date,
            "priority": priority
        }
        response = await self.client.post(f"{self.base_url}/requests", json=data)
        response.raise_for_status()
        return response.json()
    
    async def get_service_request(self, request_id: int) -> Optional[Dict[str, Any]]:
        """Get service request by ID"""
        try:
            response = await self.client.get(f"{self.base_url}/requests/{request_id}")
            response.raise_for_status()
            return response.json()
        except httpx.HTTPStatusError as e:
            if e.response.status_code == 404:
                return None
            raise
    
    async def get_customer_requests(self, customer_id: int) -> List[Dict[str, Any]]:
        """Get all service requests for a customer"""
        try:
            response = await self.client.get(f"{self.base_url}/requests/customer/{customer_id}")
            response.raise_for_status()
            return response.json()
        except httpx.HTTPStatusError as e:
            if e.response.status_code == 404:
                return []
            raise
    
    async def update_request_status(self, request_id: int, status: str) -> Dict[str, Any]:
        """Update service request status"""
        data = {"status": status}
        response = await self.client.put(f"{self.base_url}/requests/{request_id}/status", json=data)
        response.raise_for_status()
        return response.json()
    
    # Feedback operations
    async def create_feedback(self, request_id: int, feedback_text: str, 
                             revision_needed: bool = False) -> Dict[str, Any]:
        """Create feedback for a service request"""
        data = {
            "requestId": request_id,
            "feedbackText": feedback_text,
            "revisionNeeded": revision_needed
        }
        response = await self.client.post(f"{self.base_url}/feedback", json=data)
        response.raise_for_status()
        return response.json()
    
    # Payment operations
    async def create_payment(self, customer_id: int, service_request_id: int,
                            amount: float, payment_method: str) -> Dict[str, Any]:
        """Create a payment record"""
        data = {
            "customerId": customer_id,
            "serviceRequestId": service_request_id,
            "amount": amount,
            "paymentMethod": payment_method
        }
        response = await self.client.post(f"{self.base_url}/payments", json=data)
        response.raise_for_status()
        return response.json()
    
    async def get_customer_transactions(self, customer_id: int) -> List[Dict[str, Any]]:
        """Get all transactions for a customer"""
        try:
            response = await self.client.get(f"{self.base_url}/transactions/{customer_id}")
            response.raise_for_status()
            return response.json()
        except httpx.HTTPStatusError as e:
            if e.response.status_code == 404:
                return []
            raise

# Global database client instance
db_client = DatabaseClient()

