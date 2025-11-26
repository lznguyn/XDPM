import { Body, Controller, Get, Param, Patch, Post, Query } from '@nestjs/common';
import { CoordinatorService } from './coordinator.service';
import { CreateWorkOrderDto } from './dto/create-work-order.dto';
import { AssignTaskDto } from './dto/assign-task.dto';
import { UpdateTaskStatusDto } from './dto/update-task-status.dto';

@Controller('coordinator')
export class CoordinatorController {
  constructor(private readonly coordinatorService: CoordinatorService) {}

  @Post('work-orders')
  createWorkOrder(@Body() dto: CreateWorkOrderDto) {
    return this.coordinatorService.createWorkOrder(dto);
  }

  @Get('work-orders')
  listWorkOrders(@Query('customerId') customerId?: string) {
    return this.coordinatorService.listWorkOrders(customerId);
  }

  @Get('work-orders/:id')
  getWorkOrder(@Param('id') id: string) {
    return this.coordinatorService.getWorkOrder(id);
  }

  @Post('tasks/:id/assign')
  assignTask(@Param('id') id: string, @Body() dto: AssignTaskDto) {
    return this.coordinatorService.assignTask(id, dto);
  }

  @Patch('tasks/:id/status')
  updateTaskStatus(@Param('id') id: string, @Body() dto: UpdateTaskStatusDto) {
    return this.coordinatorService.updateTaskStatus(id, dto);
  }
}
