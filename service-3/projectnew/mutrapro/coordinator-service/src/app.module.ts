import { Module } from '@nestjs/common';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { TypeOrmModule } from '@nestjs/typeorm';
import { CoordinatorModule } from './coordinator/coordinator.module';
import { WorkOrder } from './coordinator/entities/work-order.entity';
import { Task } from './coordinator/entities/task.entity';


@Module({
  imports: [
    ConfigModule.forRoot({
      isGlobal: true,
    }),
    TypeOrmModule.forRootAsync({
      inject: [ConfigService],
      useFactory: (config: ConfigService) => ({
        type: 'postgres',
        host: config.get('DB_HOST'),
        port: parseInt(config.get('DB_PORT') ?? '5432', 10),
        username: config.get('DB_USER'),
        password: config.get('DB_PASS'),
        database: config.get('DB_NAME'),
        entities: [WorkOrder, Task],
        synchronize: true, 
      }),
    }),
    CoordinatorModule,
  ],
})
export class AppModule {}
