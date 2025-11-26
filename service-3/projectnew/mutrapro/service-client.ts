/**
 * Customer Service Integration Client
 * Calls to Auth Service, Coordinator Service, Payment Service
 */

import axios, { AxiosInstance } from 'axios';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: string;
}

export interface WorkOrder {
  id: number;
  customer_id: number;
  service_type: string;
  title: string;
  status: string;
  created_date: string;
}

export interface Payment {
  id: number;
  work_order_id: number;
  customer_id: number;
  amount: number;
  payment_status: string;
  created_date: string;
}

export class ServiceClient {
  private authServiceClient: AxiosInstance;
  private coordinatorServiceClient: AxiosInstance;
  private paymentServiceClient: AxiosInstance;

  constructor() {
    // Initialize service clients
    // Note: When running in Docker, use service names (e.g., http://auth-service:8081)
    // When calling from frontend/external, use Kong Gateway (http://localhost:8000)
    // Environment variables can override defaults:
    // - AUTH_SERVICE_URL: defaults to gateway /api/Auth
    // - COORDINATOR_SERVICE_URL: defaults to gateway /api/coordinator
    // - PAYMENT_SERVICE_URL: defaults to gateway /api/payments
    
    const gatewayBase = process.env.KONG_GATEWAY_URL || 'http://localhost:8000';
    
    this.authServiceClient = axios.create({
      baseURL: process.env.AUTH_SERVICE_URL || `${gatewayBase}/api/Auth`,
      timeout: 5000,
    });

    this.coordinatorServiceClient = axios.create({
      baseURL: process.env.COORDINATOR_SERVICE_URL || `${gatewayBase}/api/coordinator`,
      timeout: 5000,
    });

    this.paymentServiceClient = axios.create({
      baseURL: process.env.PAYMENT_SERVICE_URL || `${gatewayBase}/api/payments`,
      timeout: 5000,
    });
  }

  // ==================== AUTH SERVICE ====================
  async getUserById(userId: number): Promise<AuthUser> {
    try {
      // If baseURL already includes gateway path (e.g., http://localhost:8000/api/Auth),
      // use path without /api prefix. Otherwise use full path.
      const baseURL = this.authServiceClient.defaults.baseURL || '';
      const path = baseURL.includes('/api/Auth') 
        ? `/users/${userId}` 
        : `/api/users/${userId}`;
      const response = await this.authServiceClient.get(path);
      return response.data;
    } catch (error) {
      console.error('Error fetching user from Auth Service:', error);
      throw error;
    }
  }

  async verifyToken(token: string): Promise<AuthUser> {
    try {
      const baseURL = this.authServiceClient.defaults.baseURL || '';
      const path = baseURL.includes('/api/Auth')
        ? `/verify`
        : `/api/auth/verify`;
      const response = await this.authServiceClient.post(path, {
        token,
      });
      return response.data;
    } catch (error) {
      console.error('Error verifying token:', error);
      throw error;
    }
  }

  // ==================== COORDINATOR SERVICE ====================
  async getWorkOrders(customerId: number): Promise<WorkOrder[]> {
    try {
      const baseURL = this.coordinatorServiceClient.defaults.baseURL || '';
      // If baseURL includes gateway path (e.g., http://localhost:8000/api/coordinator),
      // use path without /api/coordinator prefix. Otherwise use full path.
      const path = baseURL.includes('/api/coordinator')
        ? `/work-orders?customer_id=${customerId}`
        : `/api/work-orders?customer_id=${customerId}`;
      const response = await this.coordinatorServiceClient.get(path);
      return response.data;
    } catch (error) {
      console.error('Error fetching work orders:', error);
      throw error;
    }
  }

  async createWorkOrder(workOrder: any): Promise<WorkOrder> {
    try {
      const baseURL = this.coordinatorServiceClient.defaults.baseURL || '';
      const path = baseURL.includes('/api/coordinator')
        ? `/work-orders`
        : `/api/work-orders`;
      const response = await this.coordinatorServiceClient.post(path, workOrder);
      return response.data;
    } catch (error) {
      console.error('Error creating work order:', error);
      throw error;
    }
  }

  async updateWorkOrderStatus(
    workOrderId: number,
    status: string,
  ): Promise<WorkOrder> {
    try {
      const baseURL = this.coordinatorServiceClient.defaults.baseURL || '';
      const path = baseURL.includes('/api/coordinator')
        ? `/work-orders/${workOrderId}`
        : `/api/work-orders/${workOrderId}`;
      const response = await this.coordinatorServiceClient.patch(path, { status });
      return response.data;
    } catch (error) {
      console.error('Error updating work order:', error);
      throw error;
    }
  }

  // ==================== PAYMENT SERVICE ====================
  async getPaymentsByCustomer(customerId: number): Promise<Payment[]> {
    try {
      const baseURL = this.paymentServiceClient.defaults.baseURL || '';
      // If baseURL includes gateway path (e.g., http://localhost:8000/api/payments),
      // use path without /api/payments prefix. Otherwise use full path.
      const path = baseURL.includes('/api/payments')
        ? `?customer_id=${customerId}`
        : `/api/payments?customer_id=${customerId}`;
      const response = await this.paymentServiceClient.get(path);
      return response.data;
    } catch (error) {
      console.error('Error fetching payments:', error);
      throw error;
    }
  }

  async createPayment(payment: any): Promise<Payment> {
    try {
      const baseURL = this.paymentServiceClient.defaults.baseURL || '';
      const path = baseURL.includes('/api/payments')
        ? ''
        : '/api/payments';
      const response = await this.paymentServiceClient.post(path, payment);
      // Update customer balance
      await this.updateCustomerBalance(
        payment.customer_id,
        payment.customer_email,
        -payment.amount,
      );
      return response.data;
    } catch (error) {
      console.error('Error creating payment:', error);
      throw error;
    }
  }

  async getCustomerBalance(customerId: number): Promise<any> {
    try {
      const baseURL = this.paymentServiceClient.defaults.baseURL || '';
      const path = baseURL.includes('/api/payments')
        ? `/balance/${customerId}`
        : `/api/balance/${customerId}`;
      const response = await this.paymentServiceClient.get(path);
      return response.data;
    } catch (error) {
      console.error('Error fetching customer balance:', error);
      throw error;
    }
  }

  async updateCustomerBalance(
    customerId: number,
    customerEmail: string,
    amount: number,
  ): Promise<any> {
    try {
      const baseURL = this.paymentServiceClient.defaults.baseURL || '';
      const path = baseURL.includes('/api/payments')
        ? `/balance/update`
        : `/api/balance/update`;
      const response = await this.paymentServiceClient.post(path, {
        customer_id: customerId,
        customer_email: customerEmail,
        amount_change: amount,
      });
      return response.data;
    } catch (error) {
      console.error('Error updating customer balance:', error);
      throw error;
    }
  }
}

export default new ServiceClient();
