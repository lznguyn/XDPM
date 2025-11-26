export class CreateWorkOrderDto {
  orderId: string;
  customerId: string;
  serviceType: 'TRANSCRIPTION' | 'ARRANGEMENT' | 'RECORDING' | 'FULL_PACKAGE';
  priority?: 'LOW' | 'MEDIUM' | 'HIGH';
}
