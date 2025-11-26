using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using MuTraProAPI.Models;
using MuTraProAPI.Data;
using Microsoft.AspNetCore.Authorization;
using static MuTraProAPI.Models.ServiceRequest;
using static MuTraProAPI.Models.Order;
using static MuTraProAPI.Models.MusicSubmission;
using static MuTraProAPI.Models.User;
using static MuTraProAPI.Models.CustomerPayment;
using MuTraProAPI.Helpers;
using System.Net.Http.Json;
using System.Net.Http;

namespace MuTraProAPI.Controllers
{
    [ApiController]
    [Route("api/[controller]")]
    public class AdminController : ControllerBase
    {
        private readonly MuTraProDbContext _context;
        private readonly IConfiguration _configuration;
        private readonly IHttpClientFactory _httpClientFactory;

        public AdminController(MuTraProDbContext context, IConfiguration configuration, IHttpClientFactory httpClientFactory)
        {
            _context = context;
            _configuration = configuration;
            _httpClientFactory = httpClientFactory;
        }

        // Helper method to invalidate cache
        private async Task InvalidateAdminCache(string? pattern = null)
        {
            if (string.IsNullOrEmpty(pattern))
            {
                await RedisHelper.DeletePatternAsync("admin:*");
            }
            else
            {
                await RedisHelper.DeletePatternAsync($"admin:{pattern}");
            }
        }

        // Helper method to invalidate request cache
        private async Task InvalidateRequestCache(int? requestId = null)
        {
            if (requestId.HasValue)
            {
                await RedisHelper.DeleteAsync($"request:{requestId.Value}");
            }
            await RedisHelper.DeletePatternAsync($"request:*");
            await RedisHelper.DeletePatternAsync("admin:service-requests*");
        }

        // Helper method to invalidate customer cache
        private async Task InvalidateCustomerCache(int? customerId = null)
        {
            if (customerId.HasValue)
            {
                await RedisHelper.DeleteAsync($"customer:{customerId.Value}");
            }
            await RedisHelper.DeletePatternAsync("customer:*");
            await RedisHelper.DeletePatternAsync("admin:customers*");
        }

        // Helper method to invalidate specialist schedule cache
        private async Task InvalidateScheduleCache(int? specialistId = null)
        {
            if (specialistId.HasValue)
            {
                await RedisHelper.DeletePatternAsync($"schedule:specialist:{specialistId.Value}*");
            }
            await RedisHelper.DeletePatternAsync("schedule:*");
            await RedisHelper.DeletePatternAsync("admin:specialists*");
        }

        // GET: api/Admin/stats
        [HttpGet("stats")]
        public async Task<IActionResult> GetStats()
        {
            try
            {
                // Try to get from cache first
                var cacheKey = "admin:stats";
                var cached = await RedisHelper.GetAsync<object>(cacheKey);
                if (cached != null)
                {
                    return Ok(cached);
                }

                // Giả sử bạn đã xác thực JWT và role Admin
                // Nếu muốn, thêm [Authorize(Roles="Admin")] để giới hạn
                var totalPendings = await _context.Orders
                    .Where(o => o.PaymentStatus == Status.Pending)
                    .SumAsync(o => (decimal?)o.TotalPrice) ?? 0;

                var totalCompleted = await _context.Orders
                    .Where(o => o.PaymentStatus == Status.Completed)
                    .SumAsync(o => (decimal?)o.TotalPrice) ?? 0;

                var ordersCount = await _context.Orders.CountAsync();
                var productsCount = await _context.Products.CountAsync();
                var musicsubPendingCount = await _context.MusicSubmissions
                    .Where(m => m.Status == MusicStatus.Pending).CountAsync();
                var musicsubCompletedCount = await _context.MusicSubmissions
                    .Where(m => m.Status == MusicStatus.Completed).CountAsync();
                var expertsCount = await _context.Users
                    .Where(u => u.Role == UserRole.Arrangement ||   u.Role == UserRole.Transcription || u.Role == UserRole.Recorder)
                    .CountAsync();
                var pendingOrdersCount = await _context.Orders
                    .Where(o => o.PaymentStatus == Status.Pending).CountAsync();
                var completedOrdersCount = await _context.Orders
                    .Where(o => o.PaymentStatus == Status.Completed).CountAsync();
                var usersCount = await _context.Users
                    .Where(u => u.Role == UserRole.User)
                    .CountAsync();
                var adminsCount = await _context.Users
                    .Where(u => u.Role == UserRole.Admin)
                    .CountAsync();
                var staffCount = await _context.Users
                    .Where(u => u.Role == UserRole.Coordinator)
                    .CountAsync();
                var studiosCount = await _context.Studios.CountAsync();

                var result = new
                {
                    total_pendings = totalPendings,
                    total_completed = totalCompleted,
                    orders_count = ordersCount,
                    products_count = productsCount,
                    musicsub_pending_count = musicsubPendingCount,
                    musicsub_completed_count = musicsubCompletedCount,
                    experts_count = expertsCount,
                    pending_orders_count = pendingOrdersCount,
                    completed_orders_count = completedOrdersCount,
                    users_count = usersCount,
                    admins_count = adminsCount,
                    staff_count = staffCount,
                    studios_count = studiosCount
                };

                // Store in cache with 5 minutes TTL (stats change frequently)
                await RedisHelper.SetAsync(cacheKey, result, TimeSpan.FromMinutes(5));

                return Ok(result);
            }
            catch (Exception ex)
            {
                return StatusCode(500, new
                {
                    message = "Lỗi khi lấy thống kê",
                    error = ex.Message,
                    stackTrace = ex.StackTrace
                });
            }
        }
        // ✅ Lấy danh sách đơn hàng từ service-3 (payment-service) và merge với service-1
        [HttpGet("orders")]
        public async Task<IActionResult> GetAllOrders([FromQuery] bool? refresh = false)
        {
            try
            {
                var cacheKey = "admin:orders";
                
                // Nếu có refresh=true, bypass cache và lấy dữ liệu mới từ database
                if (refresh != true)
                {
                    // Try to get from cache first
                    try
                    {
                        var cached = await RedisHelper.GetAsync<List<object>>(cacheKey);
                        if (cached != null)
                        {
                            return Ok(cached);
                        }
                    }
                    catch (Exception cacheEx)
                    {
                        // Log cache error nhưng tiếp tục lấy từ database
                        Console.WriteLine($"Cache error (continuing with DB): {cacheEx.Message}");
                    }
                }

                // Lấy orders từ service-1 (database local)
                List<dynamic> localOrders = new List<dynamic>();
                try
                {
                    var orders = await _context.Orders
                        .OrderByDescending(o => o.PlacedOn)
                        .Select(o => new
                        {
                            o.Id,
                            o.UserId,
                            o.Name,
                            o.Number,
                            o.Email,
                            o.Method,
                            o.TotalProducts,
                            o.TotalPrice,
                            o.PlacedOn,
                            PaymentStatus = o.PaymentStatus.ToString(),
                            Source = "service-1"
                        })
                        .ToListAsync();
                    localOrders = orders.Cast<dynamic>().ToList();
                }
                catch (Exception localOrderEx)
                {
                    // Nếu bảng Orders không tồn tại hoặc có lỗi, chỉ log warning và tiếp tục với payments từ service-3
                    Console.WriteLine($"Warning: Failed to fetch local orders (table may not exist): {localOrderEx.Message}");
                    Console.WriteLine("Continuing with payments from service-3 only.");
                }

                // Lấy payments từ service-3 (payment-service)
                List<dynamic> payments = new List<dynamic>();
                try
                {
                    var paymentServiceUrl = _configuration["PaymentService:BaseUrl"] ?? "http://kong:8000/api/payments";
                    var httpClient = _httpClientFactory.CreateClient();
                    httpClient.Timeout = TimeSpan.FromSeconds(10);
                    
                    Console.WriteLine($"Fetching payments from: {paymentServiceUrl}");
                    var paymentResponse = await httpClient.GetAsync(paymentServiceUrl);
                    Console.WriteLine($"Payment service response status: {paymentResponse.StatusCode}");
                    
                    if (paymentResponse.IsSuccessStatusCode)
                    {
                        var jsonString = await paymentResponse.Content.ReadAsStringAsync();
                        var jsonLength = jsonString != null ? jsonString.Length : 0;
                        Console.WriteLine($"Payment service response length: {jsonLength}");
                        if (jsonString != null && jsonString.Length > 0)
                        {
                            var previewLength = Math.Min(200, jsonString.Length);
                            Console.WriteLine($"Payment service response preview: {jsonString.Substring(0, previewLength)}");
                        }
                        
                        var paymentData = jsonString != null ? 
                            System.Text.Json.JsonSerializer.Deserialize<List<System.Text.Json.JsonElement>>(jsonString) : null;
                        
                        if (paymentData != null)
                        {
                            Console.WriteLine($"Parsed {paymentData.Count} payments from service-3");
                            foreach (var payment in paymentData)
                            {
                                try
                                {
                                    var id = payment.TryGetProperty("id", out var idProp) ? idProp.GetString() : null;
                                    var customerId = payment.TryGetProperty("customerId", out var cidProp) ? cidProp.GetString() : null;
                                    var orderId = payment.TryGetProperty("orderId", out var oidProp) ? oidProp.GetString() : null;
                                    
                                    // Parse amount - có thể là string hoặc number
                                    decimal amount = 0;
                                    if (payment.TryGetProperty("amount", out var amtProp))
                                    {
                                        if (amtProp.ValueKind == System.Text.Json.JsonValueKind.String)
                                        {
                                            var amountStr = amtProp.GetString();
                                            if (!string.IsNullOrEmpty(amountStr) && decimal.TryParse(amountStr, System.Globalization.NumberStyles.Any, System.Globalization.CultureInfo.InvariantCulture, out var parsedAmount))
                                            {
                                                amount = parsedAmount;
                                            }
                                        }
                                        else if (amtProp.ValueKind == System.Text.Json.JsonValueKind.Number)
                                        {
                                            amount = amtProp.GetDecimal();
                                        }
                                    }
                                    
                                    var method = payment.TryGetProperty("method", out var methodProp) ? methodProp.GetString() : "N/A";
                                    var status = payment.TryGetProperty("status", out var statusProp) ? statusProp.GetString() : "PENDING";
                                    DateTime createdAt;
                                    if (payment.TryGetProperty("createdAt", out var createdProp))
                                    {
                                        var createdAtStr = createdProp.GetString();
                                        createdAt = !string.IsNullOrEmpty(createdAtStr) ? DateTime.Parse(createdAtStr) : DateTime.Now;
                                    }
                                    else
                                    {
                                        createdAt = DateTime.Now;
                                    }
                                    
                                    // Log để debug
                                    Console.WriteLine($"Payment {id}: amount={amount}, status={status}");
                                    
                                    payments.Add(new
                                    {
                                        Id = id ?? Guid.NewGuid().ToString(),
                                        UserId = customerId ?? "0",
                                        Name = $"Customer {customerId ?? "N/A"}",
                                        Number = orderId ?? "N/A",
                                        Email = $"customer{customerId ?? "0"}@example.com",
                                        Method = method,
                                        TotalProducts = "1",
                                        TotalPrice = (int)Math.Round(amount), // Round thay vì cast trực tiếp
                                        PlacedOn = createdAt,
                                        PaymentStatus = status,
                                        Source = "service-3"
                                    });
                                }
                                catch (Exception ex)
                                {
                                    Console.WriteLine($"Error parsing payment: {ex.Message}");
                                    Console.WriteLine($"Payment JSON: {payment.ToString()}");
                                }
                            }
                        }
                        else
                        {
                            Console.WriteLine("Warning: Payment data is null after deserialization");
                        }
                    }
                    else
                    {
                        var errorContent = await paymentResponse.Content.ReadAsStringAsync();
                        Console.WriteLine($"Warning: Payment service returned {paymentResponse.StatusCode}. Error: {errorContent}");
                    }
                }
                catch (Exception paymentEx)
                {
                    Console.WriteLine($"Warning: Failed to fetch payments from service-3: {paymentEx.Message}");
                    Console.WriteLine($"Stack trace: {paymentEx.StackTrace}");
                    if (paymentEx.InnerException != null)
                    {
                        Console.WriteLine($"Inner exception: {paymentEx.InnerException.Message}");
                    }
                }
                
                Console.WriteLine($"Total payments fetched from service-3: {payments.Count}");

                // Merge orders từ service-1 và payments từ service-3
                var allOrders = new List<object>();
                if (localOrders != null && localOrders.Count > 0)
                {
                    allOrders.AddRange(localOrders.Cast<object>());
                    Console.WriteLine($"Added {localOrders.Count} local orders");
                }
                if (payments != null && payments.Count > 0)
                {
                    allOrders.AddRange(payments.Cast<object>());
                    Console.WriteLine($"Added {payments.Count} payments from service-3");
                }
                
                Console.WriteLine($"Total orders to return: {allOrders.Count}");
                
                // Sort by date descending
                allOrders = allOrders.OrderByDescending(o => {
                    try
                    {
                        var json = System.Text.Json.JsonSerializer.Serialize(o);
                        var element = System.Text.Json.JsonSerializer.Deserialize<System.Text.Json.JsonElement>(json);
                        
                        if (element.TryGetProperty("PlacedOn", out var placedOn))
                        {
                            if (placedOn.ValueKind == System.Text.Json.JsonValueKind.String)
                            {
                                return DateTime.Parse(placedOn.GetString() ?? DateTime.MinValue.ToString());
                            }
                        }
                        if (element.TryGetProperty("placedOn", out var placedOn2))
                        {
                            if (placedOn2.ValueKind == System.Text.Json.JsonValueKind.String)
                            {
                                return DateTime.Parse(placedOn2.GetString() ?? DateTime.MinValue.ToString());
                            }
                        }
                    }
                    catch { }
                    return DateTime.MinValue;
                }).ToList();

                // Store in cache with 2 minutes TTL
                try
                {
                    await RedisHelper.SetAsync(cacheKey, allOrders, TimeSpan.FromMinutes(2));
                }
                catch (Exception cacheEx)
                {
                    Console.WriteLine($"Cache set error (returning data anyway): {cacheEx.Message}");
                }

                return Ok(allOrders);
            }
            catch (Exception ex)
            {
                // Log chi tiết lỗi
                Console.WriteLine($"Error in GetAllOrders: {ex.Message}");
                Console.WriteLine($"Stack trace: {ex.StackTrace}");
                if (ex.InnerException != null)
                {
                    Console.WriteLine($"Inner exception: {ex.InnerException.Message}");
                }

                return StatusCode(500, new
                {
                    message = "Lỗi khi lấy danh sách đơn hàng",
                    error = ex.Message,
                    stackTrace = ex.StackTrace,
                    innerException = ex.InnerException?.Message
                });
            }
        }
        [HttpGet("orders/{id}")]
        public async Task<IActionResult> GetOrderById(int id)
        {
            var order = await _context.Orders
                .FirstOrDefaultAsync(o => o.Id == id);

            if (order == null)
                return NotFound(new { message = "Order not found" });

            return Ok(new
            {
                order.Id,
                order.UserId,
                order.Name,
                order.Number,
                order.Email,
                order.Method,
                order.TotalProducts,
                order.TotalPrice,
                order.PlacedOn,
                PaymentStatus = order.PaymentStatus.ToString()
            });
        }
        [HttpPatch("orders/{id}/status")]
        public async Task<IActionResult> UpdateOrderStatus(int id, [FromBody] OrderStatusDto dto)
        {
            var order = await _context.Orders.FindAsync(id);
            if (order == null) return NotFound();

            if (Enum.TryParse(dto.PaymentStatus, true, out Status status))
            {
                order.PaymentStatus = status;
                await _context.SaveChangesAsync();
                
                // Invalidate cache để đảm bảo dữ liệu mới được hiển thị
                await InvalidateAdminCache("orders");
                
                return Ok(new { message = "Payment status updated successfully." });
            }

            return BadRequest(new { message = "Invalid payment status." });
        }

        public class OrderStatusDto
        {
            public string PaymentStatus { get; set; } = string.Empty;
        }
        [HttpDelete("orders/{id}")]
        public async Task<IActionResult> DeleteOrder(int id)
        {
            var order = await _context.Orders.FindAsync(id);
            if (order == null)
                return NotFound();

            _context.Orders.Remove(order);
            await _context.SaveChangesAsync();
            
            // Invalidate cache để đảm bảo dữ liệu mới được hiển thị
            await InvalidateAdminCache("orders");

            return Ok(new { message = "Order deleted successfully." });
        }

        // =====================================================
        // CUSTOMER MANAGEMENT ENDPOINTS
        // =====================================================

        [HttpGet("customers")]
        public async Task<IActionResult> GetAllCustomers()
        {
            // Try to get from cache first
            var cacheKey = "admin:customers";
            var cached = await RedisHelper.GetAsync<List<object>>(cacheKey);
            if (cached != null)
            {
                return Ok(cached);
            }

            var customers = await _context.Customers
                .Include(c => c.User)
                .OrderByDescending(c => c.AccountCreated)
                .Select(c => new
                {
                    c.Id,
                    c.Name,
                    c.Email,
                    c.Phone,
                    c.Address,
                    c.AccountCreated,
                    c.IsActive,
                    UserId = c.UserId,
                    UserName = c.User != null ? c.User.Name : null,
                    UserRole = c.User != null ? c.User.Role.ToString() : null
                })
                .ToListAsync();

            // Store in cache with 15 minutes TTL
            await RedisHelper.SetAsync(cacheKey, customers, TimeSpan.FromMinutes(15));

            return Ok(customers);
        }

        [HttpGet("customers/{id}")]
        public async Task<IActionResult> GetCustomerById(int id)
        {
            // Try to get from cache first
            var cacheKey = $"customer:{id}";
            var cached = await RedisHelper.GetAsync<object>(cacheKey);
            if (cached != null)
            {
                return Ok(cached);
            }

            var customer = await _context.Customers
                .Include(c => c.User)
                .FirstOrDefaultAsync(c => c.Id == id);

            if (customer == null)
                return NotFound(new { message = "Customer not found" });

            var result = new
            {
                customer.Id,
                customer.Name,
                customer.Email,
                customer.Phone,
                customer.Address,
                customer.AccountCreated,
                customer.IsActive,
                UserId = customer.UserId,
                UserName = customer.User != null ? customer.User.Name : null,
                UserRole = customer.User != null ? customer.User.Role.ToString() : null
            };

            // Store in cache with 1 hour TTL
            await RedisHelper.SetAsync(cacheKey, result, TimeSpan.FromHours(1));

            return Ok(result);
        }

        // =====================================================
        // SERVICE REQUEST MANAGEMENT ENDPOINTS
        // =====================================================

        [HttpGet("service-requests")]
        public async Task<IActionResult> GetAllServiceRequests()
        {
            try
            {
                // Try to get from cache first
                var cacheKey = "admin:service-requests";
                var cached = await RedisHelper.GetAsync<List<object>>(cacheKey);
                if (cached != null)
                {
                    Console.WriteLine($"[CACHE HIT] Returning cached service requests (count: {cached.Count})");
                    return Ok(cached);
                }

                Console.WriteLine("[DB QUERY] Fetching service requests from database...");
                var startTime = DateTime.UtcNow;

                // Query với left join để tránh lỗi khi Customer không tồn tại
                // Sử dụng AsNoTracking() để giảm overhead và tăng performance
                var requests = await _context.ServiceRequests
                    .AsNoTracking() // Tối ưu: không track changes, giảm memory usage
                    .Include(r => r.Customer)
                    .Include(r => r.AssignedSpecialist)
                    .Include(r => r.PreferredSpecialist)
                    .OrderByDescending(r => r.CreatedDate) // Index idx_created_date sẽ được sử dụng
                    .Select(r => new
                    {
                        r.Id,
                        r.CustomerId,
                        CustomerName = r.Customer != null ? r.Customer.Name : "N/A",
                        CustomerEmail = r.Customer != null ? r.Customer.Email : "N/A",
                        ServiceType = r.ServiceType.ToString(),
                        r.Title,
                        r.Description,
                        r.FileName,
                        // Trả về status dưới dạng string với format đúng (PascalCase)
                        Status = r.Status.ToString(),
                        r.CreatedDate,
                        r.DueDate,
                        r.AssignedSpecialistId,
                        AssignedSpecialistName = r.AssignedSpecialist != null ? r.AssignedSpecialist.Name : null,
                        r.PreferredSpecialistId,
                        PreferredSpecialistName = r.PreferredSpecialist != null ? r.PreferredSpecialist.Name : null,
                        r.ScheduledDate,
                        r.ScheduledTimeSlot,
                        r.MeetingNotes,
                        r.Priority,
                        r.Paid
                    })
                    .ToListAsync();

                var queryTime = (DateTime.UtcNow - startTime).TotalMilliseconds;
                Console.WriteLine($"[DB QUERY] Fetched {requests.Count} service requests in {queryTime:F2}ms");

                // Store in cache with 2 minutes TTL (giảm từ 10 phút để cập nhật nhanh hơn)
                try
                {
                    await RedisHelper.SetAsync(cacheKey, requests, TimeSpan.FromMinutes(2));
                    Console.WriteLine("[CACHE] Stored service requests in cache");
                }
                catch (Exception cacheEx)
                {
                    Console.WriteLine($"[CACHE WARNING] Failed to cache results: {cacheEx.Message}");
                    // Continue without cache - không fail request
                }

                return Ok(requests);
            }
            catch (Microsoft.EntityFrameworkCore.DbUpdateException dbEx)
            {
                // Database specific errors
                Console.WriteLine($"Database error getting service requests: {dbEx.Message}");
                Console.WriteLine($"Inner exception: {dbEx.InnerException?.Message}");
                return StatusCode(500, new { message = "Database error occurred", error = dbEx.Message });
            }
            catch (Exception ex)
            {
                // Log error and return empty array instead of crashing
                Console.WriteLine($"Error getting service requests: {ex.Message}");
                Console.WriteLine($"Stack trace: {ex.StackTrace}");
                if (ex.InnerException != null)
                {
                    Console.WriteLine($"Inner exception: {ex.InnerException.Message}");
                }
                
                // Đảm bảo luôn trả về JSON hợp lệ (empty array)
                // Điều này giúp tránh lỗi "invalid response from upstream server"
                try
                {
                    return Ok(new List<object>()); // Return empty list instead of error
                }
                catch
                {
                    // Fallback: trả về empty JSON array string nếu có lỗi serialize
                    return new ContentResult
                    {
                        Content = "[]",
                        ContentType = "application/json",
                        StatusCode = 200
                    };
                }
            }
        }

        [HttpGet("service-requests/{id}")]
        public async Task<IActionResult> GetServiceRequestById(int id)
        {
            // Try to get from cache first
            var cacheKey = $"request:{id}";
            var cached = await RedisHelper.GetAsync<object>(cacheKey);
            if (cached != null)
            {
                return Ok(cached);
            }

            var request = await _context.ServiceRequests
                .Include(r => r.Customer)
                .Include(r => r.AssignedSpecialist)
                .FirstOrDefaultAsync(r => r.Id == id);

            if (request == null)
                return NotFound(new { message = "Service request not found" });

            var result = new
            {
                request.Id,
                request.CustomerId,
                CustomerName = request.Customer != null ? request.Customer.Name : null,
                CustomerEmail = request.Customer != null ? request.Customer.Email : null,
                request.ServiceType,
                request.Title,
                request.Description,
                request.FileName,
                request.Status,
                request.CreatedDate,
                request.DueDate,
                request.AssignedSpecialistId,
                AssignedSpecialistName = request.AssignedSpecialist != null ? request.AssignedSpecialist.Name : null,
                request.Priority,
                request.Paid
            };

            // Store in cache with 30 minutes TTL
            await RedisHelper.SetAsync(cacheKey, result, TimeSpan.FromMinutes(30));

            return Ok(result);
        }

        [HttpPatch("service-requests/{id}/status")]
        public async Task<IActionResult> UpdateServiceRequestStatus(int id, [FromBody] ServiceRequestStatusDto dto)
        {
            var request = await _context.ServiceRequests
                .Include(r => r.Customer)
                .FirstOrDefaultAsync(r => r.Id == id);
            if (request == null)
                return NotFound();

            if (Enum.TryParse(dto.Status, true, out RequestStatus status))
            {
                var oldStatus = request.Status;
                request.Status = status;
                await _context.SaveChangesAsync();
                
                // Invalidate cache
                await InvalidateRequestCache(requestId: id);
                
                // Tạo thông báo cho khách hàng nếu trạng thái thay đổi
                if (oldStatus != status)
                {
                    await NotificationHelper.NotifyStatusChangeAsync(_context, request, oldStatus, status);
                }
                
                return Ok(new { 
                    message = "Service request status updated successfully.",
                    status = request.Status.ToString()
                });
            }

            return BadRequest(new { message = "Invalid status." });
        }

        [HttpPatch("service-requests/{id}/assign")]
        public async Task<IActionResult> AssignServiceRequest(int id, [FromBody] AssignServiceRequestDto dto)
        {
            var request = await _context.ServiceRequests
                .Include(r => r.Customer)
                .FirstOrDefaultAsync(r => r.Id == id);
            if (request == null)
                return NotFound();

            var specialist = await _context.Users.FindAsync(dto.SpecialistId);
            if (specialist == null)
                return NotFound(new { message = "Specialist not found" });

            var oldStatus = request.Status;
            request.AssignedSpecialistId = dto.SpecialistId;
            request.Status = RequestStatus.Assigned;
            await _context.SaveChangesAsync();

            // Invalidate cache
            await InvalidateRequestCache(requestId: id);

            // Tạo thông báo cho khách hàng
            await NotificationHelper.NotifyStatusChangeAsync(_context, request, oldStatus, request.Status);

            return Ok(new { message = "Service request assigned successfully." });
        }

        // POST: api/Admin/service-requests/{id}/accept
        [HttpPost("service-requests/{id}/accept")]
        public async Task<IActionResult> AcceptServiceRequest(int id)
        {
            var request = await _context.ServiceRequests
                .Include(r => r.Customer)
                .FirstOrDefaultAsync(r => r.Id == id);
            if (request == null)
                return NotFound();

            if (request.Status != RequestStatus.Requested && request.Status != RequestStatus.Pending)
                return BadRequest(new { message = "Only Requested or Pending requests can be accepted." });

            // Admin chấp nhận → chuyển sang PendingReview
            var oldStatus = request.Status;
            request.Status = RequestStatus.PendingReview;
            await _context.SaveChangesAsync();

            // Invalidate cache
            await InvalidateRequestCache(requestId: id);

            // Tạo thông báo cho khách hàng
            await NotificationHelper.NotifyStatusChangeAsync(_context, request, oldStatus, request.Status);

            return Ok(new { 
                message = "Yêu cầu của bạn đã được chấp nhận. Vui lòng chọn ngày gặp chuyên gia.", 
                status = request.Status.ToString() 
            });
        }

        // POST: api/Admin/service-requests/{id}/reject
        [HttpPost("service-requests/{id}/reject")]
        public async Task<IActionResult> RejectServiceRequest(int id, [FromBody] RejectServiceRequestDto? dto = null)
        {
            var request = await _context.ServiceRequests
                .Include(r => r.Customer)
                .FirstOrDefaultAsync(r => r.Id == id);
            if (request == null)
                return NotFound();

            if (request.Status != RequestStatus.Requested && request.Status != RequestStatus.Pending)
                return BadRequest(new { message = "Only Requested or Pending requests can be rejected." });

            // Admin từ chối → chuyển sang Cancelled
            var oldStatus = request.Status;
            request.Status = RequestStatus.Cancelled;
            if (!string.IsNullOrEmpty(dto?.Reason))
            {
                request.MeetingNotes = $"Lý do từ chối: {dto.Reason}";
            }
            await _context.SaveChangesAsync();

            // Invalidate cache
            await InvalidateRequestCache(requestId: id);

            // Tạo thông báo cho khách hàng
            await NotificationHelper.NotifyStatusChangeAsync(_context, request, oldStatus, request.Status, dto?.Reason);

            return Ok(new { 
                message = "Yêu cầu đã bị từ chối.", 
                status = request.Status.ToString() 
            });
        }

        // POST: api/Admin/service-requests/{id}/schedule (Legacy - kept for backward compatibility)
        [HttpPost("service-requests/{id}/schedule")]
        public async Task<IActionResult> ScheduleServiceRequest(int id, [FromBody] ScheduleServiceRequestDto dto)
        {
            var request = await _context.ServiceRequests
                .Include(r => r.Customer)
                .FirstOrDefaultAsync(r => r.Id == id);
            if (request == null)
                return NotFound();

            if (request.Status != RequestStatus.PendingReview)
                return BadRequest(new { message = "Only PendingReview requests can be scheduled." });

            // Check if specialist schedule is available
            var schedule = await _context.SpecialistSchedules
                .FirstOrDefaultAsync(s => s.SpecialistId == dto.SpecialistId && 
                                         s.Date.Date == dto.ScheduledDate.Date);

            bool isAvailable = false;
            if (schedule != null)
            {
                // Check if the time slot is available
                isAvailable = dto.TimeSlot switch
                {
                    "0-4" => !schedule.TimeSlot1,
                    "6-10" => !schedule.TimeSlot2,
                    "12-16" => !schedule.TimeSlot3,
                    "18-22" => !schedule.TimeSlot4,
                    _ => false
                };
            }
            else
            {
                // No schedule exists, so all slots are available
                isAvailable = true;
            }

            if (!isAvailable)
                return BadRequest(new { message = "Selected time slot is not available. Specialist schedule is full." });

            // Update request - chuyển sang PendingMeetingConfirmation
            var oldStatus = request.Status;
            request.AssignedSpecialistId = dto.SpecialistId;
            request.ScheduledDate = dto.ScheduledDate;
            request.ScheduledTimeSlot = dto.TimeSlot;
            request.MeetingNotes = dto.MeetingNotes;
            request.Status = RequestStatus.PendingMeetingConfirmation;
            await _context.SaveChangesAsync();

            // Invalidate cache
            await InvalidateRequestCache(requestId: id);
            await InvalidateScheduleCache(specialistId: dto.SpecialistId);

            // Tạo thông báo cho khách hàng
            await NotificationHelper.NotifyStatusChangeAsync(_context, request, oldStatus, request.Status);

            // Update or create specialist schedule
            if (schedule == null)
            {
                schedule = new SpecialistSchedule
                {
                    SpecialistId = dto.SpecialistId,
                    Date = dto.ScheduledDate.Date
                };
                _context.SpecialistSchedules.Add(schedule);
            }

            // Mark the time slot as booked
            switch (dto.TimeSlot)
            {
                case "0-4":
                    schedule.TimeSlot1 = true;
                    break;
                case "6-10":
                    schedule.TimeSlot2 = true;
                    break;
                case "12-16":
                    schedule.TimeSlot3 = true;
                    break;
                case "18-22":
                    schedule.TimeSlot4 = true;
                    break;
            }
            schedule.UpdatedAt = DateTimeHelper.Now;
            await _context.SaveChangesAsync();

            return Ok(new { message = "Service request scheduled successfully. Waiting for expert confirmation." });
        }

        // GET: api/Admin/specialists/{id}/schedule
        [HttpGet("specialists/{id}/schedule")]
        public async Task<IActionResult> GetSpecialistSchedule(int id, [FromQuery] DateTime? startDate, [FromQuery] DateTime? endDate)
        {
            // Try to get from cache first
            var cacheKey = $"schedule:specialist:{id}:{startDate?.ToString("yyyy-MM-dd") ?? "all"}:{endDate?.ToString("yyyy-MM-dd") ?? "all"}";
            var cached = await RedisHelper.GetAsync<List<object>>(cacheKey);
            if (cached != null)
            {
                return Ok(cached);
            }

            var specialist = await _context.Users.FindAsync(id);
            if (specialist == null)
                return NotFound(new { message = "Specialist not found" });

            var query = _context.SpecialistSchedules.Where(s => s.SpecialistId == id);

            if (startDate.HasValue)
                query = query.Where(s => s.Date >= startDate.Value.Date);
            if (endDate.HasValue)
                query = query.Where(s => s.Date <= endDate.Value.Date);

            var schedules = await query.OrderBy(s => s.Date).ToListAsync();

            var result = schedules.Select(s => new
            {
                s.Id,
                s.SpecialistId,
                s.Date,
                timeSlots = new
                {
                    slot1 = s.TimeSlot1, // 0-4h
                    slot2 = s.TimeSlot2, // 6-10h
                    slot3 = s.TimeSlot3, // 12-16h
                    slot4 = s.TimeSlot4  // 18-22h
                },
                s.CreatedAt,
                s.UpdatedAt
            }).ToList();

            // Store in cache with 30 minutes TTL
            await RedisHelper.SetAsync(cacheKey, result, TimeSpan.FromMinutes(30));

            return Ok(result);
        }

        // =====================================================
        // USER MANAGEMENT ENDPOINTS
        // =====================================================

        [HttpGet("users")]
        public async Task<IActionResult> GetAllUsers()
        {
            var users = await _context.Users
                .OrderByDescending(u => u.Id)
                .Select(u => new
                {
                    u.Id,
                    u.Name,
                    u.Email,
                    Role = u.Role.ToString()
                })
                .ToListAsync();

            return Ok(users);
        }

        [HttpGet("users/{id}")]
        public async Task<IActionResult> GetUserById(int id)
        {
            var user = await _context.Users
                .FirstOrDefaultAsync(u => u.Id == id);

            if (user == null)
                return NotFound(new { message = "User not found" });

            return Ok(new
            {
                user.Id,
                user.Name,
                user.Email,
                Role = user.Role.ToString()
            });
        }

        // =====================================================
        // PAYMENT MANAGEMENT ENDPOINTS
        // =====================================================

        [HttpGet("customer-payments")]
        public async Task<IActionResult> GetAllCustomerPayments()
        {
            var payments = await _context.CustomerPayments
                .Include(p => p.Customer)
                .Include(p => p.ServiceRequest)
                .OrderByDescending(p => p.PaymentDate)
                .Select(p => new
                {
                    p.Id,
                    p.CustomerId,
                    CustomerName = p.Customer != null ? p.Customer.Name : null,
                    CustomerEmail = p.Customer != null ? p.Customer.Email : null,
                    p.ServiceRequestId,
                    ServiceRequestTitle = p.ServiceRequest != null ? p.ServiceRequest.Title : null,
                    p.Amount,
                    p.PaymentMethod,
                    p.PaymentStatus,
                    p.PaymentDate,
                    p.TransactionId
                })
                .ToListAsync();

            return Ok(payments);
        }

        [HttpGet("customer-transactions")]
        public async Task<IActionResult> GetAllCustomerTransactions()
        {
            var transactions = await _context.CustomerTransactions
                .Include(t => t.Customer)
                .OrderByDescending(t => t.Date)
                .Select(t => new
                {
                    t.Id,
                    t.CustomerId,
                    CustomerName = t.Customer != null ? t.Customer.Name : null,
                    CustomerEmail = t.Customer != null ? t.Customer.Email : null,
                    t.Description,
                    t.Amount,
                    t.TransactionType,
                    t.Date,
                    t.PaymentId
                })
                .ToListAsync();

            return Ok(transactions);
        }

        // =====================================================
        // COMPREHENSIVE STATS (Including Customer Service Data)
        // =====================================================

        [HttpGet("comprehensive-stats")]
        public async Task<IActionResult> GetComprehensiveStats()
        {
            // Existing stats
            var totalPendings = await _context.Orders
                .Where(o => o.PaymentStatus == Status.Pending)
                .SumAsync(o => (decimal?)o.TotalPrice) ?? 0;

            var totalCompleted = await _context.Orders
                .Where(o => o.PaymentStatus == Status.Completed)
                .SumAsync(o => (decimal?)o.TotalPrice) ?? 0;

            var ordersCount = await _context.Orders.CountAsync();
            var productsCount = await _context.Products.CountAsync();
            var musicsubPendingCount = await _context.MusicSubmissions
                .Where(m => m.Status == MusicStatus.Pending).CountAsync();
            var musicsubCompletedCount = await _context.MusicSubmissions
                .Where(m => m.Status == MusicStatus.Completed).CountAsync();
            var expertsCount = await _context.Users
                .Where(u => u.Role == UserRole.Arrangement || u.Role == UserRole.Transcription || u.Role == UserRole.Recorder)
                .CountAsync();
            var pendingOrdersCount = await _context.Orders
                .Where(o => o.PaymentStatus == Status.Pending).CountAsync();
            var completedOrdersCount = await _context.Orders
                .Where(o => o.PaymentStatus == Status.Completed).CountAsync();
            var usersCount = await _context.Users
                .Where(u => u.Role == UserRole.User)
                .CountAsync();
            var adminsCount = await _context.Users
                .Where(u => u.Role == UserRole.Admin)
                .CountAsync();
            var staffCount = await _context.Users
                .Where(u => u.Role == UserRole.Coordinator)
                .CountAsync();
            var studiosCount = await _context.Studios.CountAsync();

            // Customer Service Stats
            var customersCount = await _context.Customers.CountAsync();
            var activeCustomersCount = await _context.Customers
                .Where(c => c.IsActive).CountAsync();
            var serviceRequestsCount = await _context.ServiceRequests.CountAsync();
            var pendingServiceRequestsCount = await _context.ServiceRequests
                .Where(r => r.Status == RequestStatus.Submitted || r.Status == RequestStatus.Assigned).CountAsync();
            var inProgressServiceRequestsCount = await _context.ServiceRequests
                .Where(r => r.Status == RequestStatus.InProgress).CountAsync();
            var completedServiceRequestsCount = await _context.ServiceRequests
                .Where(r => r.Status == RequestStatus.Completed).CountAsync();
            var totalCustomerPayments = await _context.CustomerPayments
                .Where(p => p.PaymentStatus == CustomerPaymentStatus.Completed)
                .SumAsync(p => (decimal?)p.Amount) ?? 0;
            var pendingCustomerPayments = await _context.CustomerPayments
                .Where(p => p.PaymentStatus == CustomerPaymentStatus.Pending)
                .SumAsync(p => (decimal?)p.Amount) ?? 0;

            return Ok(new
            {
                // Original stats
                total_pendings = totalPendings,
                total_completed = totalCompleted,
                orders_count = ordersCount,
                products_count = productsCount,
                musicsub_pending_count = musicsubPendingCount,
                musicsub_completed_count = musicsubCompletedCount,
                experts_count = expertsCount,
                pending_orders_count = pendingOrdersCount,
                completed_orders_count = completedOrdersCount,
                users_count = usersCount,
                admins_count = adminsCount,
                staff_count = staffCount,
                studios_count = studiosCount,
                
                // Customer Service Stats
                customers_count = customersCount,
                active_customers_count = activeCustomersCount,
                service_requests_count = serviceRequestsCount,
                pending_service_requests_count = pendingServiceRequestsCount,
                in_progress_service_requests_count = inProgressServiceRequestsCount,
                completed_service_requests_count = completedServiceRequestsCount,
                total_customer_payments = totalCustomerPayments,
                pending_customer_payments = pendingCustomerPayments
            });
        }

        public class ServiceRequestStatusDto
        {
            public string Status { get; set; } = string.Empty;
        }

        public class AssignServiceRequestDto
        {
            public int SpecialistId { get; set; }
        }

        public class ScheduleServiceRequestDto
        {
            public int SpecialistId { get; set; }
            public DateTime ScheduledDate { get; set; }
            public string TimeSlot { get; set; } = string.Empty; // "0-4", "6-10", "12-16", "18-22"
            public string? MeetingNotes { get; set; }
        }

        public class RejectServiceRequestDto
        {
            public string? Reason { get; set; }
        }

        // =====================================================
        // SERVICE PRICE MANAGEMENT ENDPOINTS
        // =====================================================

        // GET: api/Admin/service-prices
        [HttpGet("service-prices")]
        public async Task<IActionResult> GetServicePrices()
        {
            var prices = await _context.ServicePrices
                .OrderBy(sp => sp.ServiceType)
                .Select(sp => new
                {
                    sp.Id,
                    ServiceType = sp.ServiceType.ToString(),
                    sp.Price,
                    sp.UpdatedAt,
                    sp.UpdatedBy
                })
                .ToListAsync();

            // Nếu chưa có giá nào, khởi tạo giá mặc định
            if (!prices.Any())
            {
                var defaultPrices = new List<ServicePrice>
                {
                    new ServicePrice { ServiceType = ServiceType.Transcription, Price = 50000 },
                    new ServicePrice { ServiceType = ServiceType.Arrangement, Price = 50000 }
                };
                
                _context.ServicePrices.AddRange(defaultPrices);
                await _context.SaveChangesAsync();

                return Ok(defaultPrices.Select(sp => new
                {
                    sp.Id,
                    ServiceType = sp.ServiceType.ToString(),
                    sp.Price,
                    sp.UpdatedAt,
                    sp.UpdatedBy
                }));
            }

            return Ok(prices);
        }

        // GET: api/Admin/service-prices/{serviceType}
        [HttpGet("service-prices/{serviceType}")]
        public async Task<IActionResult> GetServicePrice(string serviceType)
        {
            if (!Enum.TryParse<ServiceType>(serviceType, true, out var serviceTypeEnum))
            {
                return BadRequest(new { message = "Invalid service type" });
            }

            var price = await _context.ServicePrices
                .FirstOrDefaultAsync(sp => sp.ServiceType == serviceTypeEnum);

            if (price == null)
            {
                // Tạo giá mặc định nếu chưa có
                price = new ServicePrice
                {
                    ServiceType = serviceTypeEnum,
                    Price = 50000 // Giá mặc định
                };
                _context.ServicePrices.Add(price);
                await _context.SaveChangesAsync();
            }

            return Ok(new
            {
                price.Id,
                ServiceType = price.ServiceType.ToString(),
                price.Price,
                price.UpdatedAt,
                price.UpdatedBy
            });
        }

        // PUT: api/Admin/service-prices/{serviceType}
        [HttpPut("service-prices/{serviceType}")]
        public async Task<IActionResult> UpdateServicePrice(string serviceType, [FromBody] UpdateServicePriceDto dto)
        {
            if (!Enum.TryParse<ServiceType>(serviceType, true, out var serviceTypeEnum))
            {
                return BadRequest(new { message = "Invalid service type" });
            }

            if (dto.Price < 0)
            {
                return BadRequest(new { message = "Price must be greater than or equal to 0" });
            }

            var price = await _context.ServicePrices
                .FirstOrDefaultAsync(sp => sp.ServiceType == serviceTypeEnum);

            if (price == null)
            {
                // Tạo mới nếu chưa có
                price = new ServicePrice
                {
                    ServiceType = serviceTypeEnum,
                    Price = dto.Price,
                    UpdatedAt = DateTimeHelper.Now
                };
                _context.ServicePrices.Add(price);
            }
            else
            {
                price.Price = dto.Price;
                price.UpdatedAt = DateTimeHelper.Now;
                if (dto.UpdatedBy.HasValue)
                {
                    price.UpdatedBy = dto.UpdatedBy.Value;
                }
            }

            await _context.SaveChangesAsync();

            return Ok(new
            {
                message = "Service price updated successfully",
                price.Id,
                ServiceType = price.ServiceType.ToString(),
                price.Price,
                price.UpdatedAt,
                price.UpdatedBy
            });
        }

        public class UpdateServicePriceDto
        {
            public decimal Price { get; set; }
            public int? UpdatedBy { get; set; }
        }
    }
}
