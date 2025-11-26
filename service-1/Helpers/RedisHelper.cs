using StackExchange.Redis;
using System.Text.Json;

namespace MuTraProAPI.Helpers
{
    public class RedisHelper
    {
        private static IConnectionMultiplexer? _redis;
        private static IDatabase? _db;
        private static readonly TimeSpan DefaultTtl = TimeSpan.FromHours(1);

        public static void Initialize(IConfiguration configuration)
        {
            try
            {
                var host = configuration["REDIS_HOST"] ?? "localhost";
                var port = configuration["REDIS_PORT"] ?? "6379";
                var connectionString = $"{host}:{port}";

                _redis = ConnectionMultiplexer.Connect(connectionString);
                _db = _redis.GetDatabase();

                Console.WriteLine($"Connected to Redis at {connectionString}");
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Failed to connect to Redis: {ex.Message}. Cache will be disabled.");
                _redis = null;
                _db = null;
            }
        }

        public static async Task<T?> GetAsync<T>(string key)
        {
            if (_db == null) return default(T);

            try
            {
                var value = await _db.StringGetAsync(key);
                if (value.HasValue)
                {
                    return JsonSerializer.Deserialize<T>(value!);
                }
                return default(T);
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Error getting from cache: {ex.Message}");
                return default(T);
            }
        }

        public static async Task SetAsync<T>(string key, T value, TimeSpan? ttl = null)
        {
            if (_db == null) return;

            try
            {
                var serialized = JsonSerializer.Serialize(value);
                await _db.StringSetAsync(key, serialized, ttl ?? DefaultTtl);
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Error setting cache: {ex.Message}");
            }
        }

        public static async Task DeleteAsync(string key)
        {
            if (_db == null) return;

            try
            {
                await _db.KeyDeleteAsync(key);
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Error deleting from cache: {ex.Message}");
            }
        }

        public static async Task DeletePatternAsync(string pattern)
        {
            if (_redis == null || _db == null) return;

            try
            {
                var server = _redis.GetServer(_redis.GetEndPoints().First());
                var keys = server.Keys(pattern: pattern);
                
                var keysArray = keys.ToArray();
                if (keysArray.Length > 0)
                {
                    await _db.KeyDeleteAsync(keysArray.Select(k => (RedisKey)k.ToString()).ToArray());
                }
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Error deleting pattern from cache: {ex.Message}");
            }
        }

        public static void Dispose()
        {
            _redis?.Dispose();
        }
    }
}

