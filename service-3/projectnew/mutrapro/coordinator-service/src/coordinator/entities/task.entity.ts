import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  ManyToOne,
  CreateDateColumn,
  UpdateDateColumn,
} from 'typeorm';
import { WorkOrder } from './work-order.entity';

@Entity('tasks')
export class Task {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @ManyToOne(() => WorkOrder, (wo) => wo.tasks, {
    onDelete: 'CASCADE',
  })
  workOrder: WorkOrder;

  @Column()
  taskType: string; // TRANSCRIPTION / ARRANGEMENT / RECORDING

  @Column({ nullable: true })
  assignedTo: string; // specialistId

  @Column({ default: 'PENDING' })
  status: string; // PENDING / ASSIGNED / IN_PROGRESS / COMPLETED / CANCELED

  @Column({ type: 'timestamp', nullable: true })
  dueDate: Date | null;

  @Column({ type: 'text', nullable: true })
  notes: string;

  @CreateDateColumn()
  createdAt: Date;

  @UpdateDateColumn()
  updatedAt: Date;
}
