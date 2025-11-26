using MuTraProAPI.Data;
using Microsoft.EntityFrameworkCore;
using System.Text.Json.Serialization; // THÊM DÒNG NÀY
using System.Text.Json;                   // Cho JsonNamingPolicy
using Microsoft.AspNetCore.Http;
using Microsoft.OpenApi.Models;
using System.Globalization;
using MuTraProAPI.Helpers;

var builder = WebApplication.CreateBuilder(args);

// === Cấu hình Timezone UTC+7 (Vietnam Time) ===
// Set default culture cho toàn bộ ứng dụng
CultureInfo.DefaultThreadCurrentCulture = new CultureInfo("vi-VN");
CultureInfo.DefaultThreadCurrentUICulture = new CultureInfo("vi-VN");

// === CORS ===
builder.Services.AddCors(options =>
{
    options.AddDefaultPolicy(policy =>
    {
        policy
            .WithOrigins("http://localhost:8080", "http://localhost:8082", "http://localhost:8000", "http://localhost")
            .AllowAnyHeader()
            .AllowAnyMethod()
            .AllowCredentials();
    });
});

// === HttpClient for calling external services ===
builder.Services.AddHttpClient();

// === Services ===
builder.Services.AddControllers()
.AddJsonOptions(options =>
    {
        options.JsonSerializerOptions.PropertyNamingPolicy = JsonNamingPolicy.CamelCase;
        options.JsonSerializerOptions.Converters.Add(
            new JsonStringEnumConverter(JsonNamingPolicy.CamelCase)
        );
    });
builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen(c =>
{
    c.SwaggerDoc("v1", new OpenApiInfo
    {
        Title = "MuTraPro API",
        Version = "v1",
        Description = "API for MuTraPro Music Service Platform"
    });
    // Ignore null reference warnings for Swagger generation
    c.CustomSchemaIds(type => type.FullName);
    
    // Map IFormFile to file upload in Swagger
    c.MapType<IFormFile>(() => new OpenApiSchema
    {
        Type = "string",
        Format = "binary"
    });
});

// === Session & Cache ===
builder.Services.AddDistributedMemoryCache();
builder.Services.AddSession(options =>
{
    options.IdleTimeout = TimeSpan.FromMinutes(30);
    options.Cookie.HttpOnly = true;
    options.Cookie.IsEssential = true;
});

// === Kiểm tra Connection String ===
var connectionString = builder.Configuration.GetConnectionString("DefaultConnection");
if (string.IsNullOrEmpty(connectionString))
{
    throw new InvalidOperationException("Connection string 'DefaultConnection' not found.");
}

// === EF Core + MySQL + Retry ===
builder.Services.AddDbContext<MuTraProDbContext>(options =>
    options.UseMySql(
        connectionString,
        new MySqlServerVersion(new Version(8, 0, 33)),
        mysqlOptions => mysqlOptions.EnableRetryOnFailure(
            maxRetryCount: 5,
            maxRetryDelay: TimeSpan.FromSeconds(10),
            errorNumbersToAdd: null
        )
    )
);

var app = builder.Build();

// === Ensure database tables exist ===
try
{
    using (var scope = app.Services.CreateScope())
    {
        var context = scope.ServiceProvider.GetRequiredService<MuTraProDbContext>();
        
        // Tạo bảng Orders nếu chưa tồn tại (sử dụng IF NOT EXISTS để an toàn)
        try
        {
            await context.Database.ExecuteSqlRawAsync(@"
                CREATE TABLE IF NOT EXISTS `Orders` (
                    `Id` INT NOT NULL AUTO_INCREMENT,
                    `user_id` INT NOT NULL,
                    `name` LONGTEXT NOT NULL,
                    `number` LONGTEXT NOT NULL,
                    `email` LONGTEXT NOT NULL,
                    `method` LONGTEXT NOT NULL,
                    `total_products` LONGTEXT NOT NULL,
                    `total_price` INT NOT NULL,
                    `placed_on` DATETIME(6) NOT NULL,
                    `payment_status` LONGTEXT NOT NULL DEFAULT 'Pending',
                    PRIMARY KEY (`Id`),
                    INDEX `idx_user_id` (`user_id`),
                    INDEX `idx_placed_on` (`placed_on`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            Console.WriteLine("✅ Đã đảm bảo bảng Orders tồn tại!");
        }
        catch (Exception createEx)
        {
            Console.WriteLine($"⚠️ Không thể tự động tạo bảng Orders: {createEx.Message}");
            Console.WriteLine("💡 Vui lòng chạy script SQL thủ công hoặc truy cập: http://localhost:8082/admin/create_orders_table.php");
        }

        // Đảm bảo bảng Studios có đầy đủ các cột (price, image)
        try
        {
            // Kiểm tra và thêm cột price nếu chưa tồn tại
            var priceColumnExists = await context.Database.ExecuteSqlRawAsync(@"
                SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'Studios' 
                AND COLUMN_NAME = 'price'
            ");
            
            // Sử dụng cách đơn giản: thử thêm cột và bỏ qua lỗi nếu đã tồn tại
            try
            {
                await context.Database.ExecuteSqlRawAsync(@"
                    ALTER TABLE Studios ADD COLUMN price DECIMAL(18,2) NOT NULL DEFAULT 0
                ");
                Console.WriteLine("✅ Đã thêm cột price vào bảng Studios!");
            }
            catch (Exception alterEx)
            {
                // Bỏ qua lỗi nếu cột đã tồn tại (lỗi 1060)
                if (!alterEx.Message.Contains("Duplicate column name"))
                {
                    throw;
                }
                Console.WriteLine("ℹ️ Cột price đã tồn tại trong bảng Studios.");
            }
        }
        catch (Exception ex)
        {
            Console.WriteLine($"⚠️ Không thể kiểm tra/thêm cột price: {ex.Message}");
        }

        try
        {
            // Kiểm tra và thêm cột image nếu chưa tồn tại
            try
            {
                await context.Database.ExecuteSqlRawAsync(@"
                    ALTER TABLE Studios ADD COLUMN image LONGTEXT NULL
                ");
                Console.WriteLine("✅ Đã thêm cột image vào bảng Studios!");
            }
            catch (Exception alterEx)
            {
                // Bỏ qua lỗi nếu cột đã tồn tại (lỗi 1060)
                if (!alterEx.Message.Contains("Duplicate column name"))
                {
                    throw;
                }
                Console.WriteLine("ℹ️ Cột image đã tồn tại trong bảng Studios.");
            }
        }
        catch (Exception ex)
        {
            Console.WriteLine($"⚠️ Không thể kiểm tra/thêm cột image: {ex.Message}");
        }

        // Kiểm tra cột status trong Studios - migration đã chạy bằng SQL script
        try
        {
            // Chỉ kiểm tra để log, không migrate nữa vì đã chạy SQL script
            var statusColumnInfo = await context.Database.SqlQueryRaw<dynamic>(@"
                SELECT DATA_TYPE 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'Studios' 
                AND COLUMN_NAME = 'status'
            ").FirstOrDefaultAsync();
            
            string? statusColumnType = null;
            if (statusColumnInfo != null)
            {
                var typeProperty = statusColumnInfo.GetType().GetProperty("DATA_TYPE");
                if (typeProperty != null)
                {
                    statusColumnType = typeProperty.GetValue(statusColumnInfo)?.ToString();
                }
            }
            
            Console.WriteLine($"ℹ️ Studios.status column type: {statusColumnType ?? "unknown"}");
            if (statusColumnType == "varchar" || statusColumnType == "VARCHAR")
            {
                Console.WriteLine("✅ Studios.status is VARCHAR - ready for enum conversion!");
            }
        }
        catch (Exception ex)
        {
            Console.WriteLine($"⚠️ Không thể kiểm tra cột status: {ex.Message}");
            // Không throw exception, để service vẫn chạy được
        }
    }
}
catch (Exception ex)
{
    Console.WriteLine($"⚠️ Lỗi khi kiểm tra database: {ex.Message}");
    Console.WriteLine("💡 Ứng dụng vẫn sẽ chạy, nhưng một số tính năng có thể không hoạt động.");
}

// === Initialize Redis and MQTT ===
try
{
    RedisHelper.Initialize(app.Configuration);
}
catch (Exception ex)
{
    Console.WriteLine($"Warning: Failed to initialize Redis: {ex.Message}. Cache will be disabled.");
}

try
{
    await MqttHelper.InitializeAsync(app.Configuration);
}
catch (Exception ex)
{
    Console.WriteLine($"Warning: Failed to initialize MQTT: {ex.Message}. MQTT notifications will be disabled.");
    Console.WriteLine("Note: Application will continue to run without MQTT. Make sure MQTT broker is running for full functionality.");
}

// Cleanup on shutdown
app.Lifetime.ApplicationStopping.Register(() =>
{
    RedisHelper.Dispose();
    MqttHelper.Dispose();
});

// === Middleware ===
// Enable Swagger in all environments for easier API testing
app.UseSwagger();
app.UseSwaggerUI(c =>
{
    c.SwaggerEndpoint("/swagger/v1/swagger.json", "MuTraPro API V1");
    c.RoutePrefix = "swagger"; // Swagger UI at /swagger
});

app.UseCors();
// app.UseHttpsRedirection();
app.UseSession();

// Error handling middleware - phải đặt trước UseAuthorization
app.UseExceptionHandler(errorApp =>
{
    errorApp.Run(async context =>
    {
        context.Response.StatusCode = 500;
        context.Response.ContentType = "application/json";
        
        var exceptionHandlerPathFeature = context.Features.Get<Microsoft.AspNetCore.Diagnostics.IExceptionHandlerPathFeature>();
        var exception = exceptionHandlerPathFeature?.Error;
        
        var errorResponse = new
        {
            message = "Đã xảy ra lỗi trên server",
            error = exception?.Message ?? "Unknown error",
            stackTrace = exception?.StackTrace
        };
        
        await context.Response.WriteAsJsonAsync(errorResponse);
    });
});

app.UseAuthorization();

// === Health Check (BẮT BUỘC CHO DOCKER) ===
app.MapGet("/health", () => Results.Ok(new { 
    status = "Healthy", 
    time = DateTimeHelper.Now 
}));

app.MapControllers();

app.Run();