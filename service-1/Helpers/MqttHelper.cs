using MQTTnet;
using MQTTnet.Client;
using System;
using System.Text;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;
using Microsoft.Extensions.Configuration;

namespace MuTraProAPI.Helpers
{
    public class MqttHelper
    {
        private static IMqttClient? _client;
        private static bool _connected = false;
        private static bool _isDisposing = false;
        private static bool _isReconnecting = false;
        private static bool _isInitialized = false;
        private static readonly SemaphoreSlim _semaphore = new SemaphoreSlim(1, 1);
        private static readonly SemaphoreSlim _reconnectSemaphore = new SemaphoreSlim(1, 1);
        private static readonly SemaphoreSlim _initSemaphore = new SemaphoreSlim(1, 1);
        private static IConfiguration? _configuration;
        private static CancellationTokenSource? _reconnectCts;

        public static async Task InitializeAsync(IConfiguration configuration)
        {
            // Prevent multiple initializations
            if (_isInitialized)
            {
                return;
            }

            await _initSemaphore.WaitAsync();
            try
            {
                // Double check after acquiring lock
                if (_isInitialized)
                {
                    return;
                }

                _configuration = configuration;
                _reconnectCts = new CancellationTokenSource();
                await ConnectAsync();
                _isInitialized = true;
            }
            finally
            {
                _initSemaphore.Release();
            }
        }

        private static async Task ConnectAsync()
        {
            if (_isDisposing) return;

            _isReconnecting = false; // Reset flag when connection succeeds

            try
            {
                // Read from environment variables first, then from appsettings.json
                var broker = _configuration?["MQTT_BROKER"] 
                    ?? _configuration?["MQTT:Broker"] 
                    ?? "localhost";
                
                var portString = _configuration?["MQTT_PORT"] 
                    ?? _configuration?["MQTT:Port"] 
                    ?? "1883";
                
                if (!int.TryParse(portString, out int port))
                {
                    port = 1883; // Default port if parsing fails
                    Console.WriteLine($"Warning: Invalid MQTT_PORT value '{portString}', using default port 1883");
                }

                // Generate unique client ID to avoid conflicts on reconnection
                var baseClientId = _configuration?["MQTT:ClientId"] ?? "auth-service";
                var clientId = $"{baseClientId}-{Environment.ProcessId}-{Guid.NewGuid():N}";
                
                var keepAliveString = _configuration?["MQTT:KeepAlivePeriod"] ?? "60";
                if (!int.TryParse(keepAliveString, out int keepAlive))
                {
                    keepAlive = 60;
                }

                var factory = new MqttFactory();
                
                // Dispose old client if exists
                if (_client != null)
                {
                    try
                    {
                        _client.Dispose();
                    }
                    catch { }
                }

                _client = factory.CreateMqttClient();

                _client.ConnectedAsync += async e =>
                {
                    _connected = true;
                    Console.WriteLine($"✅ Connected to MQTT broker at {broker}:{port}");
                    await Task.CompletedTask;
                };

                _client.DisconnectedAsync += async e =>
                {
                    _connected = false;
                    Console.WriteLine($"⚠️ Disconnected from MQTT broker: {e.Reason}");
                    
                    // Auto-reconnect if not disposing and not a clean disconnect
                    if (!_isDisposing && !_isReconnecting && e.Reason != MqttClientDisconnectReason.NormalDisconnection)
                    {
                        _ = Task.Run(async () => await HandleReconnectAsync()); // Fire and forget để không block
                    }
                    
                    await Task.CompletedTask;
                };

                var options = new MqttClientOptionsBuilder()
                    .WithTcpServer(broker, port)
                    .WithClientId(clientId)
                    .WithKeepAlivePeriod(TimeSpan.FromSeconds(keepAlive))
                    .WithCleanSession(true)
                    .Build();

                await _client.ConnectAsync(options, CancellationToken.None);

                // Subscribe to relevant topics
                var subscribeOptions = factory.CreateSubscribeOptionsBuilder()
                    .WithTopicFilter("auth/#")
                    .Build();

                await _client.SubscribeAsync(subscribeOptions, CancellationToken.None);
                Console.WriteLine("✅ Subscribed to MQTT topic: auth/#");
            }
            catch (Exception ex)
            {
                Console.WriteLine($"❌ Failed to connect to MQTT broker: {ex.Message}");
                _connected = false;
                
                // Dispose failed client
                if (_client != null)
                {
                    try
                    {
                        _client.Dispose();
                    }
                    catch { }
                    _client = null;
                }
                
                // Retry connection after delay (only if not disposing and initialized)
                if (!_isDisposing && _isInitialized)
                {
                    _ = Task.Run(async () => await HandleReconnectAsync()); // Fire and forget
                }
            }
        }

        private static async Task HandleReconnectAsync()
        {
            if (_isDisposing || _reconnectCts?.Token.IsCancellationRequested == true)
                return;

            // Prevent multiple simultaneous reconnect attempts
            if (!await _reconnectSemaphore.WaitAsync(0))
            {
                return; // Another reconnect is already in progress
            }

            try
            {
                _isReconnecting = true;
                
                // Read reconnect delay from config, default to 5 seconds
                var reconnectDelayString = _configuration?["MQTT:ReconnectDelay"] ?? "5";
                if (!int.TryParse(reconnectDelayString, out int reconnectDelay))
                {
                    reconnectDelay = 5;
                }
                
                // Wait before reconnecting
                await Task.Delay(TimeSpan.FromSeconds(reconnectDelay), _reconnectCts!.Token);
                
                if (!_isDisposing && _reconnectCts?.Token.IsCancellationRequested != true)
                {
                    Console.WriteLine("🔄 Attempting to reconnect to MQTT broker...");
                    await ConnectAsync();
                }
            }
            catch (OperationCanceledException)
            {
                // Reconnect was cancelled (shutdown)
            }
            catch (Exception ex)
            {
                Console.WriteLine($"❌ Error during MQTT reconnection: {ex.Message}");
            }
            finally
            {
                _isReconnecting = false;
                _reconnectSemaphore.Release();
            }
        }

        public static async Task PublishAsync(string topic, object message, int qos = 0)
        {
            // Silently fail if not initialized or disposing
            if (!_isInitialized || _isDisposing)
            {
                return;
            }

            if (_client == null || !_connected)
            {
                // Don't log every time - only log occasionally to avoid spam
                return;
            }

            try
            {
                await _semaphore.WaitAsync();
                try
                {
                    // Double check after acquiring semaphore
                    if (_client == null || !_connected || _isDisposing)
                    {
                        return;
                    }

                    var payload = JsonSerializer.Serialize(message);
                    var messageBuilder = new MqttApplicationMessageBuilder()
                        .WithTopic(topic)
                        .WithPayload(payload)
                        .WithQualityOfServiceLevel((MQTTnet.Protocol.MqttQualityOfServiceLevel)qos)
                        .Build();

                    await _client.PublishAsync(messageBuilder, CancellationToken.None);
                }
                finally
                {
                    _semaphore.Release();
                }
            }
            catch (Exception ex)
            {
                // Only log if it's not a connection/disposal issue
                if (!_isDisposing && _client != null)
                {
                    Console.WriteLine($"Error publishing MQTT message to topic '{topic}': {ex.Message}");
                }
            }
        }

        public static void Dispose()
        {
            _isDisposing = true;
            _isInitialized = false;
            _reconnectCts?.Cancel();
            _reconnectCts?.Dispose();

            if (_client != null)
            {
                try
                {
                    _client.DisconnectAsync(
                        new MqttClientDisconnectOptions 
                        { 
                            Reason = MqttClientDisconnectOptionsReason.NormalDisconnection 
                        }, 
                        CancellationToken.None
                    ).ConfigureAwait(false).GetAwaiter().GetResult();
                    Console.WriteLine("✅ MQTT client disconnected cleanly");
                }
                catch (Exception ex)
                {
                    Console.WriteLine($"⚠️ Error disconnecting MQTT client: {ex.Message}");
                }
                finally
                {
                    _client.Dispose();
                    _client = null;
                    _connected = false;
                }
            }
        }
    }
}

