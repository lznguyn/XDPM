import { Injectable, NotFoundException, Logger, Inject } from '@nestjs/common';
import { CACHE_MANAGER } from '@nestjs/cache-manager';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { Cache } from 'cache-manager';
import { WorkOrder } from './entities/work-order.entity';
import { Task } from './entities/task.entity';
import { CreateWorkOrderDto } from './dto/create-work-order.dto';
import { AssignTaskDto } from './dto/assign-task.dto';
import { UpdateTaskStatusDto } from './dto/update-task-status.dto';
import { MqttService } from '../common/mqtt.module';

@Injectable()
export class CoordinatorService {
  private readonly logger = new Logger(CoordinatorService.name);
  private readonly CACHE_TTL = 3600; // 1 hour

  constructor(
    @InjectRepository(WorkOrder)
    private workOrderRepo: Repository<WorkOrder>,
    @InjectRepository(Task)
    private taskRepo: Repository<Task>,
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    private mqttService: MqttService,
  ) {}

  async createWorkOrder(dto: CreateWorkOrderDto) {
    
    const workOrder = this.workOrderRepo.create({
      orderId: dto.orderId,
      customerId: dto.customerId,
      serviceType: dto.serviceType,
      priority: dto.priority || 'MEDIUM',
      status: 'NEW',
    });

    
    const tasks: Task[] = [];

    if (dto.serviceType === 'TRANSCRIPTION' || dto.serviceType === 'FULL_PACKAGE') {
      const t = this.taskRepo.create({
        taskType: 'TRANSCRIPTION',
        status: 'PENDING',
      });
      tasks.push(t);
    }

    if (dto.serviceType === 'ARRANGEMENT' || dto.serviceType === 'FULL_PACKAGE') {
      const t = this.taskRepo.create({
        taskType: 'ARRANGEMENT',
        status: 'PENDING',
      });
      tasks.push(t);
    }

    if (dto.serviceType === 'RECORDING' || dto.serviceType === 'FULL_PACKAGE') {
      const t = this.taskRepo.create({
        taskType: 'RECORDING',
        status: 'PENDING',
      });
      tasks.push(t);
    }

    workOrder.tasks = tasks;

    const saved = await this.workOrderRepo.save(workOrder);
    
    // Invalidate cache
    await this.cacheManager.del(`workorder:${saved.id}`);
    if (dto.customerId) {
      await this.cacheManager.del(`workorder:customer:${dto.customerId}`);
    }
    await this.cacheManager.del('workorder:all');

    // Publish MQTT notification
    this.mqttService.publish('coordinator/work-order/created', {
      workOrderId: saved.id,
      orderId: saved.orderId,
      customerId: saved.customerId,
      serviceType: saved.serviceType,
      status: saved.status,
      timestamp: new Date().toISOString(),
    });

    return saved;
  }

  async listWorkOrders(customerId?: string) {
    // Try to get from cache first
    const cacheKey = customerId ? `workorder:customer:${customerId}` : 'workorder:all';
    const cached = await this.cacheManager.get<WorkOrder[]>(cacheKey);
    if (cached) {
      this.logger.debug(`Cache hit for work orders ${customerId ? `by customer ${customerId}` : 'all'}`);
      return cached;
    }

    let workOrders: WorkOrder[];
    if (customerId) {
      // Lấy theo khách hàng cụ thể
      workOrders = await this.workOrderRepo.find({
        where: { customerId },
        relations: ['tasks'],
      });
    } else {
      workOrders = await this.workOrderRepo.find({ relations: ['tasks'] });
    }

    // Store in cache
    await this.cacheManager.set(cacheKey, workOrders, this.CACHE_TTL);
    return workOrders;
  }

  async getWorkOrder(id: string) {
    // Try to get from cache first
    const cacheKey = `workorder:${id}`;
    const cached = await this.cacheManager.get<WorkOrder>(cacheKey);
    if (cached) {
      this.logger.debug(`Cache hit for work order ${id}`);
      return cached;
    }

    const wo = await this.workOrderRepo.findOne({
      where: { id },
      relations: ['tasks'],
    });
    if (!wo) throw new NotFoundException('WorkOrder not found');
    
    // Store in cache
    await this.cacheManager.set(cacheKey, wo, this.CACHE_TTL);
    return wo;
  }

  async assignTask(taskId: string, dto: AssignTaskDto) {
    const task = await this.taskRepo.findOne({
      where: { id: taskId },
      relations: ['workOrder'],
    });
    if (!task) throw new NotFoundException('Task not found');

    task.assignedTo = dto.assignedTo;
    task.status = 'ASSIGNED';
    task.dueDate = dto.dueDate ? new Date(dto.dueDate) : null;
    task.notes = dto.notes ?? task.notes;

    const saved = await this.taskRepo.save(task);

    // Invalidate cache
    await this.cacheManager.del(`workorder:${task.workOrder.id}`);
    await this.cacheManager.del(`task:${saved.id}`);

    // Publish MQTT notification
    this.mqttService.publish('coordinator/task/assigned', {
      taskId: saved.id,
      workOrderId: task.workOrder.id,
      assignedTo: saved.assignedTo,
      taskType: saved.taskType,
      status: saved.status,
      timestamp: new Date().toISOString(),
    });

    return saved;
  }

  async updateTaskStatus(taskId: string, dto: UpdateTaskStatusDto) {
    const task = await this.taskRepo.findOne({
      where: { id: taskId },
      relations: ['workOrder'],
    });
    if (!task) throw new NotFoundException('Task not found');

    task.status = dto.status;
    const savedTask = await this.taskRepo.save(task);

    // Nếu tất cả task của WorkOrder đã COMPLETED → set WorkOrder COMPLETED
    const workOrder = await this.workOrderRepo.findOne({
      where: { id: task.workOrder.id },
      relations: ['tasks'],
    });

    if (workOrder && workOrder.tasks.every((t) => t.status === 'COMPLETED')) {
      workOrder.status = 'COMPLETED';
      await this.workOrderRepo.save(workOrder);

      // Invalidate cache
      await this.cacheManager.del(`workorder:${workOrder.id}`);
      if (workOrder.customerId) {
        await this.cacheManager.del(`workorder:customer:${workOrder.customerId}`);
      }

      // Publish MQTT notification for completed work order
      this.mqttService.publish('coordinator/work-order/completed', {
        workOrderId: workOrder.id,
        orderId: workOrder.orderId,
        customerId: workOrder.customerId,
        serviceType: workOrder.serviceType,
        status: workOrder.status,
        timestamp: new Date().toISOString(),
      });
    }

    // Invalidate cache for task
    await this.cacheManager.del(`task:${savedTask.id}`);
    await this.cacheManager.del(`workorder:${task.workOrder.id}`);

    // Publish MQTT notification for task status update
    this.mqttService.publish('coordinator/task/status-updated', {
      taskId: savedTask.id,
      workOrderId: task.workOrder.id,
      taskType: savedTask.taskType,
      status: savedTask.status,
      timestamp: new Date().toISOString(),
    });

    return savedTask;
  }
}
