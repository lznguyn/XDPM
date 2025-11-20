using MuTraProAPI.Data;
using Microsoft.EntityFrameworkCore;
using System.Text.Json.Serialization; // THÊM DÒNG NÀY
using System.Text.Json;                   // Cho JsonNamingPolicy
using Microsoft.AspNetCore.Http;
using Microsoft.OpenApi.Models;

var builder = WebApplication.CreateBuilder(args);

// === CORS ===
builder.Services.AddCors(options =>
{
    options.AddDefaultPolicy(policy =>
    {
        policy
            .WithOrigins("http://localhost")
            .AllowAnyHeader()
            .AllowAnyMethod();
    });
});

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
app.UseAuthorization();

// === Health Check (BẮT BUỘC CHO DOCKER) ===
app.MapGet("/health", () => Results.Ok(new { 
    status = "Healthy", 
    time = DateTime.UtcNow 
}));

app.MapControllers();

app.Run();