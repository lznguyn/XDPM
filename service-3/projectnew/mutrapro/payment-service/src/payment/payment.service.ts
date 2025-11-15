import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { Payment } from './entities/payment.entity';
import { CreatePaymentDto } from './dto/create-payment.dto';
import { ConfirmPaymentDto } from './dto/confirm-payment.dto';

@Injectable()
export class PaymentService {
  constructor(
    @InjectRepository(Payment)
    private paymentRepo: Repository<Payment>,
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
      // TODO: gọi Order/Customer Service để báo Order đã thanh toán
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
}
