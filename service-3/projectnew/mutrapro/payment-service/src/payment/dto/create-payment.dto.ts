export class CreatePaymentDto {
  orderId: string;
  customerId: string;
  amount: number;
  currency?: string;
  method: 'CREDIT_CARD' | 'MOMO' | 'BANK_TRANSFER' | 'CASH';
}
