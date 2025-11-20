import { Injectable, NotFoundException, Logger } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { ConfigService } from '@nestjs/config';
import axios from 'axios';
import { Payment } from './entities/payment.entity';
import { CreatePaymentDto } from './dto/create-payment.dto';
import { ConfirmPaymentDto } from './dto/confirm-payment.dto';

@Injectable()
export class PaymentService {
  private readonly logger = new Logger(PaymentService.name);

  constructor(
    @InjectRepository(Payment)
    private paymentRepo: Repository<Payment>,
    private configService: ConfigService,
  ) {}


  async createPayment(dto: CreatePaymentDto) {
    const payment = this.paymentRepo.create({
      orderId: dto.orderId,
      customerId: dto.customerId,
      amount: dto.amount,
      currency: dto.currency || 'VND',
      method: dto.method,
      status: 'PENDING',
    });

    const saved = await this.paymentRepo.save(payment);
    // TODO: sau này có thể gọi Notification Service gửi mail/sms
    return saved;
  }

 
  async confirmPayment(id: string, dto: ConfirmPaymentDto) {
    const payment = await this.paymentRepo.findOne({ where: { id } });
    if (!payment) throw new NotFoundException('Payment not found');

    if (dto.result === 'SUCCESS') {
      payment.status = 'PAID';
      payment.paidAt = new Date();
      
      // Gọi Customer Service để cập nhật paid status của service request
      // orderId trong payment-service tương ứng với service_request_id trong customer-service
      try {
        const orderId = payment.orderId; // orderId là service_request_id
        
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
        this.logger.error(`Failed to update paid status for request ${payment.orderId} (HTTP ${errorStatus}): ${errorMessage}`);
        if (error?.response?.data) {
          this.logger.error(`Error response: ${JSON.stringify(error.response.data)}`);
        }
        // Không throw error để payment vẫn được confirm
      }
      
      // TODO: Notification Service: gửi thông báo thành công
    } else {
      payment.status = 'FAILED';
      // TODO: Notification Service: gửi thông báo thất bại
    }

    return this.paymentRepo.save(payment);
  }

 
  async getPayment(id: string) {
    const payment = await this.paymentRepo.findOne({ where: { id } });
    if (!payment) throw new NotFoundException('Payment not found');
    return payment;
  }

  
  async getPaymentByOrder(orderId: string) {
    return this.paymentRepo.find({ where: { orderId } });
  }

  
  async getPaymentsByCustomer(customerId: string) {
    return this.paymentRepo.find({ where: { customerId } });
  }

  
  async getAllPayments() {
    return this.paymentRepo.find({ order: { createdAt: 'DESC' } });
  }
}
