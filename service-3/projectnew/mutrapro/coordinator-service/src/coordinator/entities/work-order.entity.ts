import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  OneToMany,
  CreateDateColumn,
  UpdateDateColumn,
} from 'typeorm';
import { Task } from './task.entity';

@Entity({ name: 'work_orders', schema: 'coordinator' })
export class WorkOrder {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Column()
  orderId: string; 

  @Column()
  customerId: string;

  @Column()
  serviceType: string; // TRANSCRIPTION / ARRANGEMENT / RECORDING / FULL_PACKAGE

  @Column({ default: 'NEW' })
  status: string; // NEW / IN_PROGRESS / COMPLETED / CANCELED

  @Column({ default: 'MEDIUM' })
  priority: string; // LOW / MEDIUM / HIGH

  @OneToMany(() => Task, (task) => task.workOrder, { cascade: true })
  tasks: Task[];

  @CreateDateColumn()
  createdAt: Date;

  @UpdateDateColumn()
  updatedAt: Date;
}
