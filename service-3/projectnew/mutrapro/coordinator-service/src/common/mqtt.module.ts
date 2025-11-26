import { Module, Global, Injectable, OnModuleInit, OnModuleDestroy, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import * as mqtt from 'mqtt';

@Injectable()
export class MqttService implements OnModuleInit, OnModuleDestroy {
  private readonly logger = new Logger(MqttService.name);
  private client: mqtt.MqttClient | null = null;

  constructor(private configService: ConfigService) {}

  async onModuleInit() {
    const broker = this.configService.get<string>('MQTT_BROKER', 'localhost');
    const port = parseInt(this.configService.get<string>('MQTT_PORT', '1883'), 10);

    // Use stable client ID with process ID to avoid conflicts
    const clientId = `coordinator-service-${process.pid || 'default'}`;

    this.client = mqtt.connect(`mqtt://${broker}:${port}`, {
      clientId: clientId,
      reconnectPeriod: 5000,
      clean: true,
      connectTimeout: 30 * 1000,
      keepalive: 60,
    });

    this.client.on('connect', () => {
      this.logger.log(`Connected to MQTT broker at ${broker}:${port}`);
      // Subscribe to relevant topics
      this.subscribe('coordinator/#');
    });

    this.client.on('error', (error) => {
      this.logger.error(`MQTT error: ${error.message}`);
    });

    this.client.on('offline', () => {
      this.logger.warn('MQTT client offline');
    });

    this.client.on('reconnect', () => {
      this.logger.log('Reconnecting to MQTT broker...');
    });

    this.client.on('close', () => {
      this.logger.warn('MQTT connection closed');
    });
  }

  async onModuleDestroy() {
    if (this.client) {
      this.client.end();
      this.logger.log('MQTT client disconnected');
    }
  }

  publish(topic: string, message: string | object, options?: mqtt.IClientPublishOptions): void {
    if (!this.client || !this.client.connected) {
      this.logger.warn('MQTT client not connected, message not published');
      return;
    }

    const payload = typeof message === 'string' ? message : JSON.stringify(message);
    this.client.publish(topic, payload, options || {}, (error) => {
      if (error) {
        this.logger.error(`Failed to publish to topic ${topic}: ${error.message}`);
      } else {
        this.logger.debug(`Published message to topic ${topic}`);
      }
    });
  }

  subscribe(topic: string | string[], options?: mqtt.IClientSubscribeOptions): void {
    if (!this.client || !this.client.connected) {
      this.logger.warn('MQTT client not connected, subscription failed');
      return;
    }

    this.client.subscribe(topic, options || {}, (error) => {
      if (error) {
        this.logger.error(`Failed to subscribe to topic ${topic}: ${error.message}`);
      } else {
        this.logger.log(`Subscribed to topic ${Array.isArray(topic) ? topic.join(', ') : topic}`);
      }
    });
  }

  onMessage(callback: (topic: string, message: Buffer) => void): void {
    if (this.client) {
      this.client.on('message', callback);
    }
  }

  isConnected(): boolean {
    return this.client?.connected || false;
  }
}

@Global()
@Module({
  providers: [MqttService],
  exports: [MqttService],
})
export class MqttModule {
  constructor(private mqttService: MqttService) {}
}

