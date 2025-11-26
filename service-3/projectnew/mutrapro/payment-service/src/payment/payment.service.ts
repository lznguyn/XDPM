import { Injectable, NotFoundException, Logger, Inject } from '@nestjs/common';
import { CACHE_MANAGER } from '@nestjs/cache-manager';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { ConfigService } from '@nestjs/config';
import { Cache } from 'cache-manager';
import axios from 'axios';
import { Payment } from './entities/payment.entity';
import { CreatePaymentDto } from './dto/create-payment.dto';
import { ConfirmPaymentDto } from './dto/confirm-payment.dto';
import { MqttService } from '../common/mqtt.module';

@Injectable()
export class PaymentService {
  private readonly logger = new Logger(PaymentService.name);
  private readonly CACHE_TTL = 3600; // 1 hour

  constructor(
    @InjectRepository(Payment)
    private paymentRepo: Repository<Payment>,
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    private configService: ConfigService,
    private mqttService: MqttService,
  ) {}


  async createPayment(dto: CreatePaymentDto) {
    // Map orderId (string) từ DTO vào workOrderId (number)
    // orderId trong DTO là service_request_id (string), cần convert sang number
    const workOrderId = parseInt(dto.orderId, 10);
    if (isNaN(workOrderId)) {
      throw new Error(`Invalid orderId: ${dto.orderId}. Must be a number.`);
    }

    const customerIdNum = parseInt(dto.customerId, 10);
    if (isNaN(customerIdNum)) {
      throw new Error(`Invalid customerId: ${dto.customerId}. Must be a number.`);
    }

    // Lấy customer email từ customer service hoặc auth service
    let customerEmail = `customer${customerIdNum}@example.com`; // Default email
    try {
      const kongGatewayUrl = this.configService.get<string>('KONG_GATEWAY_URL', 'http://kong:8000');
      const customerUrl = `${kongGatewayUrl}/api/Customer/${customerIdNum}`;
      const customerResponse = await axios.get(customerUrl, { timeout: 5000 });
      if (customerResponse.data && customerResponse.data.email) {
        customerEmail = customerResponse.data.email;
      }
    } catch (error) {
      this.logger.warn(`Could not fetch customer email for customer ${customerIdNum}, using default: ${error.message}`);
      // Sử dụng default email nếu không lấy được
    }

    // Đảm bảo customerEmail không null hoặc undefined
    if (!customerEmail || customerEmail.trim() === '') {
      customerEmail = `customer${customerIdNum}@example.com`;
      this.logger.warn(`Customer email was empty, using default: ${customerEmail}`);
    }

    this.logger.log(`Creating payment with customerEmail: ${customerEmail} for customer ${customerIdNum}`);

    const payment = this.paymentRepo.create({
      workOrderId: workOrderId,
      customerId: customerIdNum,
      customerEmail: customerEmail, // Đảm bảo không null
      amount: dto.amount,
      method: dto.method,
      status: 'pending', // lowercase để khớp với database default
      transactionId: null,
      notes: null,
    });

    const saved = await this.paymentRepo.save(payment);
    
    // Invalidate cache
    await this.cacheManager.del(`payment:${saved.id}`);
    await this.cacheManager.del(`payment:order:${dto.orderId}`);
    await this.cacheManager.del(`payment:customer:${dto.customerId}`);
    await this.cacheManager.del('payment:all'); // Invalidate all payments cache

    // Publish MQTT notification
    this.mqttService.publish('payment/created', {
      paymentId: saved.id,
      orderId: dto.orderId, // Giữ nguyên orderId từ DTO
      customerId: dto.customerId, // Giữ nguyên customerId từ DTO
      amount: saved.amount,
      status: saved.status,
      timestamp: new Date().toISOString(),
    });

    // Return với format tương thích
    return {
      id: saved.id.toString(),
      orderId: dto.orderId,
      customerId: dto.customerId,
      amount: saved.amount,
      currency: dto.currency || 'VND',
      method: saved.method,
      status: saved.status,
      createdAt: saved.createdAt,
      updatedAt: saved.createdAt, // Không có updatedAt trong DB, dùng createdAt
      paidAt: saved.paidAt,
    };
  }

 
  async confirmPayment(id: string, dto: ConfirmPaymentDto) {
    const paymentId = parseInt(id, 10);
    if (isNaN(paymentId)) {
      throw new NotFoundException(`Invalid payment id: ${id}`);
    }
    
    const payment = await this.paymentRepo.findOne({ where: { id: paymentId } });
    if (!payment) throw new NotFoundException('Payment not found');

    if (dto.result === 'SUCCESS') {
      payment.status = 'paid'; // lowercase để khớp với database
      payment.paidAt = new Date();
      await this.paymentRepo.save(payment);
      
      // Gọi Customer Service để cập nhật paid status của service request
      // workOrderId trong payment-service tương ứng với service_request_id trong customer-service
      try {
        const orderId = payment.workOrderId.toString(); // Convert workOrderId sang string
        
        // Gọi qua Kong Gateway để cập nhật paid status
        const kongGatewayUrl = this.configService.get<string>('KONG_GATEWAY_URL', 'http://kong:8000');
        const updateUrl = `${kongGatewayUrl}/api/Customer/requests/${orderId}/paid`;
        
        this.logger.log(`Updating paid status for request ${orderId} via ${updateUrl}`);
        
        const response = await axios.patch(
          updateUrl,
          { paid: true },
          {
            headers: { 'Content-Type': 'application/json' },
            timeout: 10000,
          }
        );
        
        this.logger.log(`Successfully updated paid status for request ${orderId}: ${JSON.stringify(response.data)}`);
      } catch (error: any) {
        // Log error nhưng không fail payment confirmation
        const errorMessage = error?.response?.data?.message || error?.message || 'Unknown error';
        const errorStatus = error?.response?.status || 'N/A';
        const orderIdStr = payment.workOrderId.toString();
        this.logger.error(`Failed to update paid status for request ${orderIdStr} (HTTP ${errorStatus}): ${errorMessage}`);
        if (error?.response?.data) {
          this.logger.error(`Error response: ${JSON.stringify(error.response.data)}`);
        }
        // Không throw error để payment vẫn được confirm
      }

      const orderIdStr = payment.workOrderId.toString();
      const customerIdStr = payment.customerId.toString();

      // Invalidate cache
      await this.cacheManager.del(`payment:${payment.id}`);
      await this.cacheManager.del(`payment:order:${orderIdStr}`);
      await this.cacheManager.del(`payment:customer:${customerIdStr}`);

      // Publish MQTT notification for successful payment
      this.mqttService.publish('payment/confirmed', {
        paymentId: payment.id.toString(),
        orderId: orderIdStr,
        customerId: customerIdStr,
        amount: payment.amount,
        status: payment.status,
        timestamp: new Date().toISOString(),
      });
    } else {
      payment.status = 'failed'; // lowercase để khớp với database

      const orderIdStr = payment.workOrderId.toString();
      const customerIdStr = payment.customerId.toString();

      // Invalidate cache
      await this.cacheManager.del(`payment:${payment.id}`);
      await this.cacheManager.del(`payment:order:${orderIdStr}`);
      await this.cacheManager.del(`payment:customer:${customerIdStr}`);
      await this.cacheManager.del('payment:all'); // Invalidate all payments cache

      // Publish MQTT notification for failed payment
      this.mqttService.publish('payment/failed', {
        paymentId: payment.id.toString(),
        orderId: orderIdStr,
        customerId: customerIdStr,
        amount: payment.amount,
        status: payment.status,
        timestamp: new Date().toISOString(),
      });
    }

    const saved = await this.paymentRepo.save(payment);
    
    // Return với format tương thích
    return {
      id: saved.id.toString(),
      orderId: saved.workOrderId.toString(),
      customerId: saved.customerId.toString(),
      amount: saved.amount,
      currency: 'VND',
      method: saved.method,
      status: saved.status,
      createdAt: saved.createdAt,
      updatedAt: saved.createdAt,
      paidAt: saved.paidAt,
    };
  }

 
  async getPayment(id: string) {
    const paymentId = parseInt(id, 10);
    if (isNaN(paymentId)) {
      throw new NotFoundException(`Invalid payment id: ${id}`);
    }

    // Try to get from cache first
    const cacheKey = `payment:${id}`;
    const cached = await this.cacheManager.get<any>(cacheKey);
    if (cached) {
      this.logger.debug(`Cache hit for payment ${id}`);
      return cached;
    }

    const payment = await this.paymentRepo.findOne({ where: { id: paymentId } });
    if (!payment) throw new NotFoundException('Payment not found');
    
    // Format response
    const formatted = {
      id: payment.id.toString(),
      orderId: payment.workOrderId.toString(),
      customerId: payment.customerId.toString(),
      amount: payment.amount,
      currency: 'VND',
      method: payment.method,
      status: payment.status,
      createdAt: payment.createdAt,
      updatedAt: payment.createdAt,
      paidAt: payment.paidAt,
    };
    
    // Store in cache
    await this.cacheManager.set(cacheKey, formatted, this.CACHE_TTL);
    return formatted;
  }

  
  async getPaymentByOrder(orderId: string) {
    const workOrderId = parseInt(orderId, 10);
    if (isNaN(workOrderId)) {
      throw new NotFoundException(`Invalid orderId: ${orderId}`);
    }

    // Try to get from cache first
    const cacheKey = `payment:order:${orderId}`;
    const cached = await this.cacheManager.get<any[]>(cacheKey);
    if (cached) {
      this.logger.debug(`Cache hit for payments by order ${orderId}`);
      return cached;
    }

    const payments = await this.paymentRepo.find({ where: { workOrderId } });
    
    // Format response
    const formatted = payments.map(p => ({
      id: p.id.toString(),
      orderId: p.workOrderId.toString(),
      customerId: p.customerId.toString(),
      amount: p.amount,
      currency: 'VND',
      method: p.method,
      status: p.status,
      createdAt: p.createdAt,
      updatedAt: p.createdAt,
      paidAt: p.paidAt,
    }));
    
    // Store in cache
    await this.cacheManager.set(cacheKey, formatted, this.CACHE_TTL);
    return formatted;
  }

  
  async getPaymentsByCustomer(customerId: string) {
    const customerIdNum = parseInt(customerId, 10);
    if (isNaN(customerIdNum)) {
      throw new NotFoundException(`Invalid customerId: ${customerId}`);
    }

    // Try to get from cache first
    const cacheKey = `payment:customer:${customerId}`;
    const cached = await this.cacheManager.get<any[]>(cacheKey);
    if (cached) {
      this.logger.debug(`Cache hit for payments by customer ${customerId}`);
      return cached;
    }

    const payments = await this.paymentRepo.find({ where: { customerId: customerIdNum } });
    
    // Format response
    const formatted = payments.map(p => ({
      id: p.id.toString(),
      orderId: p.workOrderId.toString(),
      customerId: p.customerId.toString(),
      amount: p.amount,
      currency: 'VND',
      method: p.method,
      status: p.status,
      createdAt: p.createdAt,
      updatedAt: p.createdAt,
      paidAt: p.paidAt,
    }));
    
    // Store in cache
    await this.cacheManager.set(cacheKey, formatted, this.CACHE_TTL);
    return formatted;
  }

  
  async getAllPayments() {
    // Try to get from cache first
    const cacheKey = 'payment:all';
    const cached = await this.cacheManager.get<any[]>(cacheKey);
    if (cached) {
      this.logger.debug(`Cache hit for all payments: ${cached.length} payments`);
      return cached;
    }

    this.logger.log('Fetching payments from database...');
    const payments = await this.paymentRepo.find({ 
      order: { createdAt: 'DESC' as any } 
    });
    
    this.logger.log(`Found ${payments.length} payments in database`);
    
    // Format response
    const formatted = payments.map(p => ({
      id: p.id.toString(),
      orderId: p.workOrderId.toString(),
      customerId: p.customerId.toString(),
      amount: p.amount,
      currency: 'VND',
      method: p.method,
      status: p.status,
      createdAt: p.createdAt,
      updatedAt: p.createdAt,
      paidAt: p.paidAt,
    }));
    
    this.logger.log(`Formatted ${formatted.length} payments for response`);
    
    // Store in cache
    await this.cacheManager.set(cacheKey, formatted, this.CACHE_TTL);
    this.logger.log(`Cached ${formatted.length} payments with key: ${cacheKey}`);
    return formatted;
  }
}
