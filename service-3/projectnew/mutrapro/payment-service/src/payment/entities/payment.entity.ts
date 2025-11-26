import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  CreateDateColumn,
} from 'typeorm';

@Entity({ name: 'payments', schema: 'payment' })
export class Payment {
  @PrimaryGeneratedColumn()
  id: number;

  // orderId từ DTO sẽ được map vào work_order_id
  // Trong database: work_order_id là INT, nhưng orderId từ DTO là string (service_request_id)
  @Column({ name: 'work_order_id' })
  workOrderId: number;

  @Column({ name: 'customer_id' })
  customerId: number;

  @Column({ name: 'customer_email' })
  customerEmail: string;

  @Column('decimal', { precision: 12, scale: 2 })
  amount: number;

  @Column({ name: 'payment_method' })
  method: string; // CREDIT_CARD / MOMO / BANK_TRANSFER / CASH

  @Column({ name: 'payment_status', default: 'pending' })
  status: string; // pending / paid / failed / canceled / refunded

  @Column({ name: 'transaction_id', nullable: true })
  transactionId: string;

  @CreateDateColumn({ name: 'created_date' })
  createdAt: Date;

  @Column({ name: 'completed_date', type: 'timestamp', nullable: true })
  paidAt: Date | null;

  @Column({ type: 'text', nullable: true })
  notes: string;
}
