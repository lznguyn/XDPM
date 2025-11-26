import { Module, Global } from '@nestjs/common';
import { CacheModule } from '@nestjs/cache-manager';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { redisStore } from 'cache-manager-redis-store';

@Global()
@Module({
  imports: [
    CacheModule.registerAsync({
      imports: [ConfigModule],
      inject: [ConfigService],
      useFactory: async (configService: ConfigService) => {
        const store = await redisStore({
          socket: {
            host: configService.get<string>('REDIS_HOST', 'localhost'),
            port: parseInt(configService.get<string>('REDIS_PORT', '6379'), 10),
          },
        }) as any;

        return {
          store,
          ttl: 3600, // 1 hour default TTL
        };
      },
    }),
  ],
  exports: [CacheModule],
})
export class RedisCacheModule {}

