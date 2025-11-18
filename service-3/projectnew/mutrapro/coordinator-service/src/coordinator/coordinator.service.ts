import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { WorkOrder } from './entities/work-order.entity';
import { Task } from './entities/task.entity';
import { CreateWorkOrderDto } from './dto/create-work-order.dto';
import { AssignTaskDto } from './dto/assign-task.dto';
import { UpdateTaskStatusDto } from './dto/update-task-status.dto';

@Injectable()
export class CoordinatorService {
  constructor(
    @InjectRepository(WorkOrder)
    private workOrderRepo: Repository<WorkOrder>,
    @InjectRepository(Task)
    private taskRepo: Repository<Task>,
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
    return saved;
  }

  async listWorkOrders(customerId?: string) {
    if (customerId) {
      // Lấy theo khách hàng cụ thể
      return this.workOrderRepo.find({
        where: { customerId },
        relations: ['tasks'],
      });
    }

    return this.workOrderRepo.find({ relations: ['tasks'] });
  }

  async getWorkOrder(id: string) {
    const wo = await this.workOrderRepo.findOne({
      where: { id },
      relations: ['tasks'],
    });
    if (!wo) throw new NotFoundException('WorkOrder not found');
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

    // TODO: gọi Notification Service gửi thông báo (sau này)
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
      // TODO: gọi Notification Service báo cho Customer
    }

    return savedTask;
  }
}
