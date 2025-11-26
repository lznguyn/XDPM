import { Module, Global } from '@nestjs/common';
import { CacheModule } from '@nestjs/cache-manager';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { redisStore } from 'cache-manager-redis-store';
import type { RedisClientOptions } from 'redis';

@Global()
@Module({
  imports: [
    CacheModule.registerAsync<RedisClientOptions>({
      imports: [ConfigModule],
      inject: [ConfigService],
      useFactory: async (configService: ConfigService) => {
        try {
          const store = await redisStore({
            socket: {
              host: configService.get<string>('REDIS_HOST', 'localhost'),
              port: parseInt(configService.get<string>('REDIS_PORT', '6379'), 10),
            },
          });

          return {
            store: store as any,
            ttl: 3600, // 1 hour default TTL
          };
        } catch (error) {
          // Fallback to memory cache if Redis is not available
          console.warn('Redis connection failed, using memory cache:', error);
          return {
            ttl: 3600,
          };
        }
      },
    }),
  ],
  exports: [CacheModule],
})
export class RedisCacheModule {}

