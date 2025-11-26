import { Module } from '@nestjs/common';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { TypeOrmModule } from '@nestjs/typeorm';
import { CoordinatorModule } from './coordinator/coordinator.module';
import { WorkOrder } from './coordinator/entities/work-order.entity';
import { Task } from './coordinator/entities/task.entity';
import { SnakeNamingStrategy } from './common/snake-naming.strategy';
import { RedisCacheModule } from './common/redis.module';
import { MqttModule } from './common/mqtt.module';


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
        synchronize: false, // Disable to use existing schema from init-db.sql
        namingStrategy: new SnakeNamingStrategy(), // Convert camelCase to snake_case
      }),
    }),
    // Redis Cache Module
    RedisCacheModule,
    // MQTT Module
    MqttModule,
    CoordinatorModule,
  ],
})
export class AppModule {}
