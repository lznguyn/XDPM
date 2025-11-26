/**
 * Payment Service - Database Entities
 * Manages payments, invoices, customer balance, and transaction history
 */

import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
  Index,
} from 'typeorm';

@Entity('payments', { schema: 'payment' })
@Index(['customer_id'])
@Index(['payment_status'])
export class Payment {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  work_order_id: number;

  @Column()
  customer_id: number;

  @Column()
  customer_email: string;

  @Column({ type: 'decimal', precision: 12, scale: 2 })
  amount: number;

  @Column()
  payment_method: string;

  @Column({ default: 'pending' })
  payment_status: string;

  @Column({ nullable: true })
  transaction_id: string;

  @Column({ nullable: true })
  notes: string;

  @CreateDateColumn()
  created_date: Date;

  @Column({ nullable: true })
  completed_date: Date;
}

@Entity('invoices', { schema: 'payment' })
@Index(['payment_id'])
export class Invoice {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  payment_id: number;

  @Column({ unique: true })
  invoice_number: string;

  @Column({ type: 'decimal', precision: 12, scale: 2 })
  amount: number;

  @Column({ default: 'issued' })
  status: string;

  @Column({ nullable: true })
  pdf_path: string;

  @CreateDateColumn()
  issued_date: Date;

  @Column({ nullable: true })
  due_date: Date;
}

@Entity('customer_balance', { schema: 'payment' })
@Index(['customer_id'])
export class CustomerBalance {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ unique: true })
  customer_id: number;

  @Column()
  customer_email: string;

  @Column({
    type: 'decimal',
    precision: 12,
    scale: 2,
    default: 0,
  })
  total_balance: number;

  @Column({
    type: 'decimal',
    precision: 12,
    scale: 2,
    default: 0,
  })
  total_spent: number;

  @Column({
    type: 'decimal',
    precision: 12,
    scale: 2,
    default: 0,
  })
  total_earned: number;

  @Column({ nullable: true })
  last_transaction: Date;

  @UpdateDateColumn()
  updated_date: Date;
}

@Entity('payment_history', { schema: 'payment' })
@Index(['customer_id'])
export class PaymentHistory {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  customer_id: number;

  @Column({ nullable: true })
  payment_id: number;

  @Column()
  transaction_type: string; // 'debit', 'credit', 'refund'

  @Column({ type: 'decimal', precision: 12, scale: 2 })
  amount: number;

  @Column({ nullable: true })
  description: string;

  @Column({
    type: 'decimal',
    precision: 12,
    scale: 2,
    nullable: true,
  })
  balance_after: number;

  @CreateDateColumn()
  created_date: Date;
}

// =====================================================
// Services and Controllers example
// =====================================================

/*
Example Payment Service (payment.service.ts):

@Injectable()
export class PaymentService {
  constructor(
    @InjectRepository(Payment)
    private paymentRepo: Repository<Payment>,
    @InjectRepository(Invoice)
    private invoiceRepo: Repository<Invoice>,
    @InjectRepository(CustomerBalance)
    private balanceRepo: Repository<CustomerBalance>,
    @InjectRepository(PaymentHistory)
    private historyRepo: Repository<PaymentHistory>,
  ) {}

  async createPayment(data: any): Promise<Payment> {
    // Create payment record
    const payment = this.paymentRepo.create({
      work_order_id: data.work_order_id,
      customer_id: data.customer_id,
      customer_email: data.customer_email,
      amount: data.amount,
      payment_method: data.payment_method,
      payment_status: 'pending',
    });

    const savedPayment = await this.paymentRepo.save(payment);

    // Update customer balance
    await this.updateCustomerBalance(
      data.customer_id,
      data.customer_email,
      -data.amount,
    );

    // Create invoice
    await this.createInvoice(savedPayment.id, data.amount);

    // Notify coordinator that payment is ready
    // await this.coordinatorService.updateWorkOrderStatus(
    //   data.work_order_id,
    //   'awaiting_payment_confirmation'
    // );

    return savedPayment;
  }

  async updateCustomerBalance(
    customerId: number,
    customerEmail: string,
    amountChange: number,
  ): Promise<void> {
    let balance = await this.balanceRepo.findOne({
      where: { customer_id: customerId },
    });

    if (!balance) {
      balance = this.balanceRepo.create({
        customer_id: customerId,
        customer_email: customerEmail,
        total_balance: amountChange,
      });
    } else {
      balance.total_balance += amountChange;
      if (amountChange < 0) {
        balance.total_spent += Math.abs(amountChange);
      } else {
        balance.total_earned += amountChange;
      }
    }

    balance.last_transaction = new Date();
    await this.balanceRepo.save(balance);

    // Record in history
    await this.historyRepo.save({
      customer_id: customerId,
      transaction_type: amountChange < 0 ? 'debit' : 'credit',
      amount: Math.abs(amountChange),
      balance_after: balance.total_balance,
    });
  }

  async getCustomerBalance(customerId: number): Promise<CustomerBalance> {
    return this.balanceRepo.findOne({ where: { customer_id: customerId } });
  }

  async getPaymentsByCustomer(customerId: number): Promise<Payment[]> {
    return this.paymentRepo.find({
      where: { customer_id: customerId },
      order: { created_date: 'DESC' },
    });
  }

  async createInvoice(paymentId: number, amount: number): Promise<Invoice> {
    const invoiceNumber = `INV-${Date.now()}`;
    const invoice = this.invoiceRepo.create({
      payment_id: paymentId,
      invoice_number: invoiceNumber,
      amount,
      status: 'issued',
    });
    return this.invoiceRepo.save(invoice);
  }

  async confirmPayment(paymentId: number): Promise<Payment> {
    const payment = await this.paymentRepo.findOne({ where: { id: paymentId } });
    if (!payment) throw new Error('Payment not found');

    payment.payment_status = 'completed';
    payment.completed_date = new Date();

    const result = await this.paymentRepo.save(payment);

    // Notify coordinator about payment confirmation
    // await this.coordinatorService.updateWorkOrderStatus(
    //   payment.work_order_id,
    //   'paid'
    // );

    return result;
  }
}

Example Controller (payment.controller.ts):

@Controller('api/payments')
export class PaymentController {
  constructor(private paymentService: PaymentService) {}

  @Post()
  async createPayment(@Body() data: any) {
    return this.paymentService.createPayment(data);
  }

  @Get()
  async getPayments(@Query('customer_id') customerId: number) {
    return this.paymentService.getPaymentsByCustomer(customerId);
  }

  @Post(':id/confirm')
  async confirmPayment(@Param('id') paymentId: number) {
    return this.paymentService.confirmPayment(paymentId);
  }

  @Get('balance/:customer_id')
  async getBalance(@Param('customer_id') customerId: number) {
    return this.paymentService.getCustomerBalance(customerId);
  }

  @Post('balance/update')
  async updateBalance(@Body() data: any) {
    return this.paymentService.updateCustomerBalance(
      data.customer_id,
      data.customer_email,
      data.amount_change,
    );
  }
}
*/
