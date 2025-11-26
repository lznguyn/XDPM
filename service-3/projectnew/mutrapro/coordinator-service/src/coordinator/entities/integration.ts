/**
 * Coordinator Service - Database Entities
 * Manages work orders, tasks, studios, and revisions
 */

import {
  Entity,
  Column,
  PrimaryGeneratedColumn,
  CreateDateColumn,
  UpdateDateColumn,
  Index,
} from 'typeorm';

@Entity('work_orders', { schema: 'coordinator' })
@Index(['customer_id'])
@Index(['status'])
export class WorkOrder {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  customer_id: number;

  @Column()
  customer_email: string;

  @Column()
  service_type: string;

  @Column()
  title: string;

  @Column({ nullable: true })
  description: string;

  @Column({ nullable: true })
  file_path: string;

  @Column({ nullable: true })
  file_name: string;

  @Column({ default: 'submitted' })
  status: string;

  @Column({ nullable: true })
  due_date: Date;

  @Column({ nullable: true })
  assigned_specialist_id: number;

  @Column({ nullable: true })
  assigned_specialist_name: string;

  @Column({ default: 'normal' })
  priority: string;

  @CreateDateColumn()
  created_date: Date;

  @UpdateDateColumn()
  updated_date: Date;
}

@Entity('tasks', { schema: 'coordinator' })
@Index(['work_order_id'])
@Index(['task_status'])
export class Task {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  work_order_id: number;

  @Column()
  task_name: string;

  @Column({ nullable: true })
  task_description: string;

  @Column({ nullable: true })
  assigned_to_id: number;

  @Column({ nullable: true })
  assigned_to_name: string;

  @Column({ default: 'pending' })
  task_status: string;

  @Column({ nullable: true })
  start_date: Date;

  @Column({ nullable: true })
  end_date: Date;

  @CreateDateColumn()
  created_date: Date;

  @UpdateDateColumn()
  updated_date: Date;
}

@Entity('studios', { schema: 'coordinator' })
export class Studio {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  studio_name: string;

  @Column()
  studio_owner_id: number;

  @Column({ nullable: true })
  contact_phone: string;

  @Column({ nullable: true })
  contact_email: string;

  @Column({ nullable: true })
  address: string;

  @Column({ type: 'decimal', precision: 10, scale: 2, nullable: true })
  hourly_rate: number;

  @Column({ default: true })
  is_active: boolean;

  @CreateDateColumn()
  created_date: Date;
}

@Entity('revisions', { schema: 'coordinator' })
@Index(['work_order_id'])
export class Revision {
  @PrimaryGeneratedColumn()
  id: number;

  @Column()
  work_order_id: number;

  @Column()
  feedback_text: string;

  @Column({ default: false })
  revision_needed: boolean;

  @CreateDateColumn()
  created_date: Date;

  @Column({ nullable: true })
  resolved_date: Date;
}

// =====================================================
// Services and Controllers example
// =====================================================

/*
Example Coordinator Service (coordinator.service.ts):

@Injectable()
export class CoordinatorService {
  constructor(
    @InjectRepository(WorkOrder)
    private workOrderRepo: Repository<WorkOrder>,
    @InjectRepository(Task)
    private taskRepo: Repository<Task>,
  ) {}

  async createWorkOrder(data: any): Promise<WorkOrder> {
    // Call Auth Service to verify customer
    // const user = await this.authService.getUserById(data.customer_id);
    
    const workOrder = this.workOrderRepo.create({
      customer_id: data.customer_id,
      customer_email: data.customer_email,
      service_type: data.service_type,
      title: data.title,
      description: data.description,
      file_path: data.file_path,
      status: 'submitted',
    });
    
    return this.workOrderRepo.save(workOrder);
  }

  async updateWorkOrderStatus(id: number, status: string): Promise<WorkOrder> {
    await this.workOrderRepo.update(id, { status });
    
    // Notify payment service if status is "completed"
    if (status === 'completed') {
      // await this.paymentService.createPaymentRequest(id);
    }
    
    return this.workOrderRepo.findOne({ where: { id } });
  }

  async getWorkOrdersByCustomer(customerId: number): Promise<WorkOrder[]> {
    return this.workOrderRepo.find({
      where: { customer_id: customerId },
      order: { created_date: 'DESC' },
    });
  }

  async createTask(workOrderId: number, taskData: any): Promise<Task> {
    const task = this.taskRepo.create({
      work_order_id: workOrderId,
      task_name: taskData.task_name,
      task_description: taskData.task_description,
      assigned_to_id: taskData.assigned_to_id,
      task_status: 'pending',
    });
    
    return this.taskRepo.save(task);
  }
}

Example Controller (coordinator.controller.ts):

@Controller('api/work-orders')
export class CoordinatorController {
  constructor(private coordinatorService: CoordinatorService) {}

  @Post()
  async createWorkOrder(@Body() data: any) {
    return this.coordinatorService.createWorkOrder(data);
  }

  @Get()
  async getWorkOrders(@Query('customer_id') customerId: number) {
    return this.coordinatorService.getWorkOrdersByCustomer(customerId);
  }

  @Patch(':id')
  async updateWorkOrder(@Param('id') id: number, @Body() data: any) {
    return this.coordinatorService.updateWorkOrderStatus(id, data.status);
  }

  @Post(':id/tasks')
  async createTask(@Param('id') workOrderId: number, @Body() data: any) {
    return this.coordinatorService.createTask(workOrderId, data);
  }
}
*/
