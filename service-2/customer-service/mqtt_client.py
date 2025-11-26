"""
MQTT Client for Customer Service
"""
import os
import json
import paho.mqtt.client as mqtt
from typing import Callable, Optional
import logging
import threading

logger = logging.getLogger(__name__)

class MqttClient:
    def __init__(self):
        self.broker = os.getenv('MQTT_BROKER', 'localhost')
        self.port = int(os.getenv('MQTT_PORT', 1883))
        self.client: Optional[mqtt.Client] = None
        self.connected = False
        self.lock = threading.Lock()

    def connect(self):
        """Connect to MQTT broker"""
        try:
            # Use stable client ID based on process ID only (thread ID changes)
            client_id = f"customer-service-{os.getpid()}"
            self.client = mqtt.Client(client_id=client_id, clean_session=True)
            
            self.client.on_connect = self._on_connect
            self.client.on_disconnect = self._on_disconnect
            self.client.on_publish = self._on_publish
            self.client.on_subscribe = self._on_subscribe
            self.client.on_message = self._on_message
            
            # Set connection options
            self.client.connect(self.broker, self.port, keepalive=60)
            self.client.loop_start()
            
            logger.info(f"Connecting to MQTT broker at {self.broker}:{self.port} with client ID: {client_id}")
        except Exception as e:
            logger.error(f"Failed to connect to MQTT broker: {e}")
            self.client = None

    def _on_connect(self, client, userdata, flags, rc):
        """Callback when connected to MQTT broker"""
        if rc == 0:
            self.connected = True
            logger.info(f"Connected to MQTT broker at {self.broker}:{self.port}")
            # Subscribe to relevant topics
            self.subscribe('customer/#')
        else:
            logger.error(f"Failed to connect to MQTT broker with code {rc}")
            self.connected = False

    def _on_disconnect(self, client, userdata, rc):
        """Callback when disconnected from MQTT broker"""
        self.connected = False
        logger.warning(f"Disconnected from MQTT broker (rc={rc})")

    def _on_publish(self, client, userdata, mid):
        """Callback when message is published"""
        logger.debug(f"Message published with mid {mid}")

    def _on_subscribe(self, client, userdata, mid, granted_qos):
        """Callback when subscribed to topic"""
        logger.debug(f"Subscribed to topic with mid {mid}")

    def _on_message(self, client, userdata, msg):
        """Callback when message is received"""
        logger.debug(f"Received message on topic {msg.topic}: {msg.payload.decode()}")

    def publish(self, topic: str, message: dict, qos: int = 0):
        """Publish message to topic"""
        if not self.client or not self.connected:
            logger.warning("MQTT client not connected, message not published")
            return False
        
        try:
            payload = json.dumps(message, default=str)
            result = self.client.publish(topic, payload, qos)
            if result.rc == mqtt.MQTT_ERR_SUCCESS:
                logger.debug(f"Published message to topic {topic}")
                return True
            else:
                logger.error(f"Failed to publish message to topic {topic}")
                return False
        except Exception as e:
            logger.error(f"Error publishing message: {e}")
            return False

    def subscribe(self, topic: str, qos: int = 0):
        """Subscribe to topic"""
        if not self.client or not self.connected:
            logger.warning("MQTT client not connected, subscription failed")
            return False
        
        try:
            result = self.client.subscribe(topic, qos)
            if result[0] == mqtt.MQTT_ERR_SUCCESS:
                logger.info(f"Subscribed to topic {topic}")
                return True
            else:
                logger.error(f"Failed to subscribe to topic {topic}")
                return False
        except Exception as e:
            logger.error(f"Error subscribing to topic: {e}")
            return False

    def disconnect(self):
        """Disconnect from MQTT broker"""
        if self.client:
            self.client.loop_stop()
            self.client.disconnect()
            logger.info("Disconnected from MQTT broker")

# Global MQTT client instance
mqtt_client = MqttClient()

