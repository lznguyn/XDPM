import { Module } from '@nestjs/common';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { TypeOrmModule } from '@nestjs/typeorm';
import { PaymentModule } from './payment/payment.module';
import { Payment } from './payment/entities/payment.entity';
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
        host: config.get<string>('DB_HOST'),
        port: parseInt(config.get<string>('DB_PORT') ?? '5432', 10),
        username: config.get<string>('DB_USER'),
        password: config.get<string>('DB_PASS'),
        database: config.get<string>('DB_NAME'),
        entities: [Payment],   
        synchronize: false, // Disable to use existing schema from init-db.sql
        namingStrategy: new SnakeNamingStrategy(), // Convert camelCase to snake_case
      }),
    }),

    // Redis Cache Module
    RedisCacheModule,

    // MQTT Module
    MqttModule,

   
    PaymentModule,
  ],
})
export class AppModule {}
