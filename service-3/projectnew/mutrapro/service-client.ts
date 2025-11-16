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
    this.authServiceClient = axios.create({
      baseURL: process.env.AUTH_SERVICE_URL || 'http://localhost:8081',
      timeout: 5000,
    });

    this.coordinatorServiceClient = axios.create({
      baseURL: process.env.COORDINATOR_SERVICE_URL || 'http://localhost:3000',
      timeout: 5000,
    });

    this.paymentServiceClient = axios.create({
      baseURL: process.env.PAYMENT_SERVICE_URL || 'http://localhost:3001',
      timeout: 5000,
    });
  }

  // ==================== AUTH SERVICE ====================
  async getUserById(userId: number): Promise<AuthUser> {
    try {
      const response = await this.authServiceClient.get(`/api/users/${userId}`);
      return response.data;
    } catch (error) {
      console.error('Error fetching user from Auth Service:', error);
      throw error;
    }
  }

  async verifyToken(token: string): Promise<AuthUser> {
    try {
      const response = await this.authServiceClient.post('/api/auth/verify', {
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
      const response = await this.coordinatorServiceClient.get(
        `/api/work-orders?customer_id=${customerId}`,
      );
      return response.data;
    } catch (error) {
      console.error('Error fetching work orders:', error);
      throw error;
    }
  }

  async createWorkOrder(workOrder: any): Promise<WorkOrder> {
    try {
      const response = await this.coordinatorServiceClient.post(
        '/api/work-orders',
        workOrder,
      );
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
      const response = await this.coordinatorServiceClient.patch(
        `/api/work-orders/${workOrderId}`,
        { status },
      );
      return response.data;
    } catch (error) {
      console.error('Error updating work order:', error);
      throw error;
    }
  }

  // ==================== PAYMENT SERVICE ====================
  async getPaymentsByCustomer(customerId: number): Promise<Payment[]> {
    try {
      const response = await this.paymentServiceClient.get(
        `/api/payments?customer_id=${customerId}`,
      );
      return response.data;
    } catch (error) {
      console.error('Error fetching payments:', error);
      throw error;
    }
  }

  async createPayment(payment: any): Promise<Payment> {
    try {
      const response = await this.paymentServiceClient.post(
        '/api/payments',
        payment,
      );
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
      const response = await this.paymentServiceClient.get(
        `/api/balance/${customerId}`,
      );
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
      const response = await this.paymentServiceClient.post(
        `/api/balance/update`,
        {
          customer_id: customerId,
          customer_email: customerEmail,
          amount_change: amount,
        },
      );
      return response.data;
    } catch (error) {
      console.error('Error updating customer balance:', error);
      throw error;
    }
  }
}

export default new ServiceClient();
