using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using MuTraProAPI.Models;
using MuTraProAPI.Data;
using BCrypt.Net;
using MuTraProAPI.Helpers;
using System.Net.Http.Json;
using System.Net.Http;

namespace MuTraProAPI.Controllers
{
    [ApiController]
    [Route("api/[controller]")]
    public class CustomerController : ControllerBase
    {
        private readonly MuTraProDbContext _context;
        private readonly IConfiguration _configuration;
        private readonly IHttpClientFactory _httpClientFactory;

        public CustomerController(MuTraProDbContext context, IConfiguration configuration, IHttpClientFactory httpClientFactory)
        {
            _context = context;
            _configuration = configuration;
            _httpClientFactory = httpClientFactory;
        }

        // Helper method to invalidate customer cache
        private async Task InvalidateCustomerCache(int customerId)
        {
            await RedisHelper.DeleteAsync($"customer:{customerId}");
            await RedisHelper.DeletePatternAsync($"customer:*");
        }

        // Helper method to invalidate request cache
        private async Task InvalidateRequestCache(int? requestId = null, int? customerId = null)
        {
            if (requestId.HasValue)
            {
                await RedisHelper.DeleteAsync($"request:{requestId.Value}");
            }
            if (customerId.HasValue)
            {
                await RedisHelper.DeletePatternAsync($"request:customer:{customerId.Value}*");
            }
            await RedisHelper.DeletePatternAsync($"requests:*");
            // Invalidate admin cache khi có request mới để admin thấy ngay
            await RedisHelper.DeletePatternAsync("admin:service-requests*");
        }

        // Helper method to invalidate payment cache
        private async Task InvalidatePaymentCache(int? customerId = null, int? requestId = null)
        {
            if (customerId.HasValue)
            {
                await RedisHelper.DeletePatternAsync($"payment:customer:{customerId.Value}*");
            }
            if (requestId.HasValue)
            {
                await RedisHelper.DeleteAsync($"payment:request:{requestId.Value}");
            }
            await RedisHelper.DeletePatternAsync($"payments:*");
        }

        // POST: api/Customer
        [HttpPost]
        public async Task<IActionResult> CreateCustomer([FromBody] CreateCustomerDto dto)
        {
            // Check if customer with email already exists
            var existingCustomer = await _context.Customers
                .FirstOrDefaultAsync(c => c.Email == dto.Email);
            
            if (existingCustomer != null)
                return BadRequest(new { message = "Email already exists" });

            // Nếu không có UserId được cung cấp, tự động tạo User record
            int? userId = dto.UserId;
            if (!userId.HasValue)
            {
                // Kiểm tra xem User với email này đã tồn tại chưa
                var existingUser = await _context.Users
                    .FirstOrDefaultAsync(u => u.Email == dto.Email);
                
                if (existingUser == null)
                {
                    // Tạo User mới với role User và password mặc định (có thể cần đổi sau)
                    // Tạo password hash mặc định (khách hàng sẽ cần đổi password khi đăng nhập lần đầu)
                    var defaultPassword = BCrypt.Net.BCrypt.HashPassword("TempPassword123!");
                    
                    var newUser = new User
                    {
                        Name = dto.Name,
                        Email = dto.Email,
                        PasswordHash = defaultPassword,
                        Role = UserRole.User
                    };
                    
                    _context.Users.Add(newUser);
                    await _context.SaveChangesAsync();
                    userId = newUser.Id;
                }
                else
                {
                    // Nếu User đã tồn tại, sử dụng UserId đó
                    userId = existingUser.Id;
                }
            }

            var customer = new Customer
            {
                Name = dto.Name,
                Email = dto.Email,
                Phone = dto.Phone,
                Address = dto.Address,
                AccountCreated = DateTimeHelper.Now,
                IsActive = true,
                UserId = userId
            };

            _context.Customers.Add(customer);
            await _context.SaveChangesAsync();

            return Ok(new
            {
                id = customer.Id,
                name = customer.Name,
                email = customer.Email,
                phone = customer.Phone,
                address = customer.Address,
                account_created = customer.AccountCreated,
                is_active = customer.IsActive,
                user_id = customer.UserId
            });
        }

        // GET: api/Customer
        [HttpGet]
        public async Task<IActionResult> GetAllCustomers()
        {
            var customers = await _context.Customers
                .Select(c => new
                {
                    id = c.Id,
                    name = c.Name,
                    email = c.Email,
                    phone = c.Phone,
                    address = c.Address,
                    account_created = c.AccountCreated,
                    is_active = c.IsActive
                })
                .ToListAsync();

            return Ok(customers);
        }

        // GET: api/Customer/{id}
        [HttpGet("{id}")]
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
                .FirstOrDefaultAsync(c => c.Id == id);

            if (customer == null)
                return NotFound(new { message = "Customer not found" });

            var result = new
            {
                id = customer.Id,
                name = customer.Name,
                email = customer.Email,
                phone = customer.Phone,
                address = customer.Address,
                account_created = customer.AccountCreated,
                is_active = customer.IsActive
            };

            // Store in cache with 1 hour TTL
            await RedisHelper.SetAsync(cacheKey, result, TimeSpan.FromHours(1));

            return Ok(result);
        }

        // PUT: api/Customer/{id}
        [HttpPut("{id}")]
        public async Task<IActionResult> UpdateCustomer(int id, [FromBody] UpdateCustomerDto dto)
        {
            var customer = await _context.Customers.FindAsync(id);
            if (customer == null)
                return NotFound(new { message = "Customer not found" });

            if (!string.IsNullOrEmpty(dto.Name))
                customer.Name = dto.Name;
            if (dto.Phone != null)
                customer.Phone = dto.Phone;
            if (dto.Address != null)
                customer.Address = dto.Address;

            await _context.SaveChangesAsync();

            // Invalidate cache
            await InvalidateCustomerCache(id);

            return Ok(new
            {
                id = customer.Id,
                name = customer.Name,
                email = customer.Email,
                phone = customer.Phone,
                address = customer.Address,
                account_created = customer.AccountCreated,
                is_active = customer.IsActive
            });
        }

        // POST: api/Customer/requests
        [HttpPost("requests")]
        public async Task<IActionResult> CreateServiceRequest([FromBody] CreateServiceRequestDto dto)
        {
            // Verify customer exists
            var customer = await _context.Customers.FindAsync(dto.CustomerId);
            if (customer == null)
                return NotFound(new { message = "Customer not found" });

            // Parse status từ DTO, mặc định là Pending nếu không có hoặc không hợp lệ
            RequestStatus requestStatus = RequestStatus.Pending;
            if (!string.IsNullOrEmpty(dto.Status))
            {
                if (Enum.TryParse<RequestStatus>(dto.Status, true, out var parsedStatus))
                {
                    requestStatus = parsedStatus;
                }
            }

            var request = new ServiceRequest
            {
                CustomerId = dto.CustomerId,
                ServiceType = Enum.Parse<ServiceType>(dto.ServiceType, true),
                Title = dto.Title,
                Description = dto.Description,
                FileName = dto.FileName,
                Status = requestStatus, // Sử dụng status từ DTO hoặc mặc định là Pending
                CreatedDate = DateTimeHelper.Now,
                DueDate = dto.DueDate,
                Priority = dto.Priority ?? "normal",
                Paid = false
            };

            _context.ServiceRequests.Add(request);
            await _context.SaveChangesAsync();

            // Invalidate cache
            await InvalidateRequestCache(requestId: request.Id, customerId: request.CustomerId);

            // Kiểm tra nếu đây là yêu cầu đặt studio (Recording service với [STUDIO_BOOKING] tag)
            if (request.ServiceType == ServiceType.Recording && 
                !string.IsNullOrEmpty(request.Description) &&
                request.Description.Contains("[STUDIO_BOOKING]"))
            {
                try
                {
                    // Parse booking info từ description
                    var startTag = "[STUDIO_BOOKING]";
                    var endTag = "[/STUDIO_BOOKING]";
                    var startIndex = request.Description.IndexOf(startTag);
                    var endIndex = request.Description.IndexOf(endTag);

                    if (startIndex >= 0 && endIndex > startIndex)
                    {
                        var jsonStr = request.Description.Substring(
                            startIndex + startTag.Length,
                            endIndex - startIndex - startTag.Length
                        );

                        // Parse JSON để lấy booking info
                        var bookingInfo = System.Text.Json.JsonSerializer.Deserialize<System.Text.Json.JsonElement>(jsonStr);
                        
                        if (bookingInfo.TryGetProperty("studio_id", out var studioIdElement) &&
                            bookingInfo.TryGetProperty("booking_date", out var bookingDateElement) &&
                            bookingInfo.TryGetProperty("booking_time", out var bookingTimeElement))
                        {
                            var studioId = studioIdElement.GetInt32();
                            var bookingDateStr = bookingDateElement.GetString();
                            var bookingTime = bookingTimeElement.GetString() ?? "";

                            // Parse booking date
                            if (DateTime.TryParse(bookingDateStr, out var bookingDate))
                            {
                                // Kiểm tra studio có tồn tại không
                                var studio = await _context.Studios.FindAsync(studioId);
                                if (studio != null)
                                {
                                    // Kiểm tra studio status - không cho phép đặt nếu UnderMaintenance
                                    if (studio.Status == StudioStatus.UnderMaintenance)
                                    {
                                        request.Status = RequestStatus.Cancelled;
                                        await _context.SaveChangesAsync();
                                        return BadRequest(new { message = "Studio này đang bảo trì. Vui lòng chọn studio khác." });
                                    }

                                    // Kiểm tra xem studio có đang bị occupied vào ngày đặt không
                                    var hasActiveBooking = await _context.StudioBookings
                                        .AnyAsync(b => b.StudioId == studioId &&
                                                      b.BookingDate.Date == bookingDate.Date &&
                                                      b.Status == BookingStatus.Approved);

                                    // Kiểm tra xem studio có status là Occupied vào ngày đặt không
                                    // (Nếu booking date là hôm nay và studio đang Occupied)
                                    var today = DateTimeHelper.Now.Date;
                                    if (bookingDate.Date == today && studio.Status == StudioStatus.Occupied)
                                    {
                                        // Studio đã bị occupied hôm nay
                                        request.Status = RequestStatus.Cancelled;
                                        await _context.SaveChangesAsync();
                                        return BadRequest(new { message = "Studio này đã được đặt vào ngày hôm nay. Vui lòng chọn ngày khác." });
                                    }

                                    if (hasActiveBooking)
                                    {
                                        // Đã có booking approved cho ngày này
                                        request.Status = RequestStatus.Cancelled;
                                        await _context.SaveChangesAsync();
                                        return BadRequest(new { message = "Studio này đã được đặt vào ngày bạn chọn. Vui lòng chọn ngày khác." });
                                    }

                                    // Tạo StudioBooking với status Pending
                                    var studioBooking = new StudioBooking
                                    {
                                        StudioId = studioId,
                                        ServiceRequestId = request.Id,
                                        CustomerId = request.CustomerId,
                                        BookingDate = bookingDate.Date,
                                        BookingTime = bookingTime,
                                        Status = BookingStatus.Pending,
                                        CreatedDate = DateTimeHelper.Now,
                                        Notes = bookingInfo.TryGetProperty("notes", out var notesElement) 
                                            ? notesElement.GetString() 
                                            : null
                                    };

                                    _context.StudioBookings.Add(studioBooking);
                                    
                                    // Giữ nguyên status là Pending (không cần đổi thành Requested)
                                    // Status sẽ được admin chuyển sang PendingReview khi duyệt
                                    
                                    await _context.SaveChangesAsync();
                                }
                            }
                        }
                    }
                }
                catch (Exception ex)
                {
                    // Log error nhưng không fail request
                    // Có thể log vào file hoặc console
                    Console.WriteLine($"Error creating StudioBooking: {ex.Message}");
                }
            }

            return Ok(new
            {
                id = request.Id,
                customer_id = request.CustomerId,
                service_type = request.ServiceType.ToString(),
                title = request.Title,
                description = request.Description,
                file_name = request.FileName,
                status = request.Status.ToString(), // Sẽ trả về "Pending" hoặc "Requested" nếu là studio booking
                created_date = request.CreatedDate,
                due_date = request.DueDate,
                priority = request.Priority,
                paid = request.Paid,
                preferred_specialist_id = request.PreferredSpecialistId,
                scheduled_date = request.ScheduledDate,
                scheduled_time_slot = request.ScheduledTimeSlot,
                meeting_notes = request.MeetingNotes
            });
        }

        // GET: api/Customer/requests/customer/{customerId}
        [HttpGet("requests/customer/{customerId}")]
        public async Task<IActionResult> GetCustomerRequests(int customerId)
        {
            // Try to get from cache first
            var cacheKey = $"request:customer:{customerId}";
            var cached = await RedisHelper.GetAsync<List<object>>(cacheKey);
            if (cached != null)
            {
                return Ok(cached);
            }

            var requests = await _context.ServiceRequests
                .Where(r => r.CustomerId == customerId)
                .Select(r => new
                {
                    id = r.Id,
                    customer_id = r.CustomerId,
                    service_type = r.ServiceType.ToString(),
                    title = r.Title,
                    description = r.Description,
                    file_name = r.FileName,
                    status = r.Status.ToString(),
                    created_date = r.CreatedDate,
                    due_date = r.DueDate,
                    assigned_specialist_id = r.AssignedSpecialistId,
                    priority = r.Priority,
                    paid = r.Paid
                })
                .ToListAsync();

            if (!requests.Any())
                return NotFound(new { message = "No requests found" });

            // Store in cache with 30 minutes TTL
            await RedisHelper.SetAsync(cacheKey, requests, TimeSpan.FromMinutes(30));

            return Ok(requests);
        }

        // GET: api/Customer/requests/{id}
        [HttpGet("requests/{id}")]
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
                .FirstOrDefaultAsync(r => r.Id == id);

            if (request == null)
                return NotFound(new { message = "Request not found" });

            var result = new
            {
                id = request.Id,
                customer_id = request.CustomerId,
                service_type = request.ServiceType.ToString(),
                title = request.Title,
                description = request.Description,
                file_name = request.FileName,
                status = request.Status.ToString(),
                created_date = request.CreatedDate,
                due_date = request.DueDate,
                assigned_specialist_id = request.AssignedSpecialistId,
                priority = request.Priority,
                paid = request.Paid
            };

            // Store in cache with 30 minutes TTL
            await RedisHelper.SetAsync(cacheKey, result, TimeSpan.FromMinutes(30));

            return Ok(result);
        }

        // PUT: api/Customer/requests/{id}/status
        [HttpPut("requests/{id}/status")]
        public async Task<IActionResult> UpdateRequestStatus(int id, [FromBody] UpdateRequestStatusDto dto)
        {
            var request = await _context.ServiceRequests.FindAsync(id);
            if (request == null)
                return NotFound(new { message = "Request not found" });

            if (Enum.TryParse(dto.Status, true, out RequestStatus status))
            {
                request.Status = status;
                await _context.SaveChangesAsync();

                // Invalidate cache
                await InvalidateRequestCache(requestId: id, customerId: request.CustomerId);

                return Ok(new
                {
                    id = request.Id,
                    status = request.Status.ToString()
                });
            }

            return BadRequest(new { message = "Invalid status" });
        }

        // POST: api/Customer/requests/{id}/select-expert
        [HttpPost("requests/{id}/select-expert")]
        public async Task<IActionResult> SelectExpertAndSchedule(int id, [FromBody] SelectExpertAndScheduleDto dto)
        {
            var request = await _context.ServiceRequests.FindAsync(id);
            if (request == null)
                return NotFound(new { message = "Request not found" });

            // Chỉ cho phép khi request ở trạng thái PendingReview
            if (request.Status != RequestStatus.PendingReview)
                return BadRequest(new { message = "Chỉ có thể chọn chuyên gia khi yêu cầu ở trạng thái PendingReview." });

            // Kiểm tra chuyên gia có tồn tại không
            var specialist = await _context.Users.FindAsync(dto.SpecialistId);
            if (specialist == null)
                return NotFound(new { message = "Chuyên gia không tồn tại." });

            // Kiểm tra lịch làm việc của chuyên gia
            var schedule = await _context.SpecialistSchedules
                .FirstOrDefaultAsync(s => s.SpecialistId == dto.SpecialistId && 
                                         s.Date.Date == dto.ScheduledDate.Date);

            // Kiểm tra ngày có phải là ngày làm việc của chuyên gia không
            // (Có thể mở rộng logic này để kiểm tra working days)
            // Tạm thời chỉ kiểm tra time slot có available không

            bool isTimeSlotAvailable = false;
            if (schedule != null)
            {
                // Kiểm tra time slot có trống không
                isTimeSlotAvailable = dto.TimeSlot switch
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
                // Không có schedule → tất cả time slot đều available
                isTimeSlotAvailable = true;
            }

            if (!isTimeSlotAvailable)
                return BadRequest(new { message = "Không thể gặp chuyên gia vào ngày/giờ này, vui lòng chọn lại." });

            // Cập nhật request
            request.AssignedSpecialistId = dto.SpecialistId;
            request.ScheduledDate = dto.ScheduledDate;
            request.ScheduledTimeSlot = dto.TimeSlot;
            request.MeetingNotes = dto.MeetingNotes;
            request.Status = RequestStatus.PendingMeetingConfirmation; // Chờ chuyên gia xác nhận
            await _context.SaveChangesAsync();

            // Cập nhật hoặc tạo specialist schedule
            if (schedule == null)
            {
                schedule = new SpecialistSchedule
                {
                    SpecialistId = dto.SpecialistId,
                    Date = dto.ScheduledDate.Date
                };
                _context.SpecialistSchedules.Add(schedule);
            }

            // Đánh dấu time slot đã được đặt
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

            // Invalidate cache
            await InvalidateRequestCache(requestId: request.Id, customerId: request.CustomerId);

            return Ok(new
            {
                message = "Đã chọn chuyên gia và ngày gặp. Đang chờ chuyên gia xác nhận.",
                id = request.Id,
                status = request.Status.ToString()
            });
        }

        // POST: api/Customer/feedback
        [HttpPost("feedback")]
        public async Task<IActionResult> SubmitFeedback([FromBody] CreateFeedbackDto dto)
        {
            var request = await _context.ServiceRequests.FindAsync(dto.RequestId);
            if (request == null)
                return NotFound(new { message = "Request not found" });

            var feedback = new CustomerFeedback
            {
                RequestId = dto.RequestId,
                FeedbackText = dto.FeedbackText,
                RevisionNeeded = dto.RevisionNeeded ?? false,
                CreatedDate = DateTime.Now
            };

            _context.CustomerFeedbacks.Add(feedback);

            // If revision requested, update request status
            if (dto.RevisionNeeded == true)
            {
                request.Status = RequestStatus.RevisionRequested;
            }

            await _context.SaveChangesAsync();

            return Ok(new
            {
                id = feedback.Id,
                request_id = feedback.RequestId,
                feedback_text = feedback.FeedbackText,
                revision_needed = feedback.RevisionNeeded,
                created_date = feedback.CreatedDate
            });
        }

        // POST: api/Customer/payments
        [HttpPost("payments")]
        public async Task<IActionResult> CreatePayment([FromBody] CreatePaymentDto dto)
        {
            try
            {
                var customer = await _context.Customers.FindAsync(dto.CustomerId);
                if (customer == null)
                    return NotFound(new { message = "Customer not found" });

                var request = await _context.ServiceRequests.FindAsync(dto.ServiceRequestId);
                if (request == null)
                    return NotFound(new { message = "Service request not found" });

                // Tạo payment trong service-3 (payment-service) thay vì service-1
                var paymentServiceUrl = _configuration["PaymentService:BaseUrl"] ?? "http://kong:8000/api/payments";
                var httpClient = _httpClientFactory.CreateClient();
                httpClient.Timeout = TimeSpan.FromSeconds(10);

                // Map payment method từ service-1 format sang service-3 format
                var paymentMethod = dto.PaymentMethod.ToUpper();
                if (paymentMethod == "BANK_TRANSFER" || paymentMethod == "CHUYEN_KHOAN")
                    paymentMethod = "BANK_TRANSFER";
                else if (paymentMethod == "CREDIT_CARD" || paymentMethod == "THE_TIN_DUNG")
                    paymentMethod = "CREDIT_CARD";
                else if (paymentMethod == "MOMO" || paymentMethod == "VI_DIEN_TU")
                    paymentMethod = "MOMO";
                else if (paymentMethod == "CASH" || paymentMethod == "TIEN_MAT")
                    paymentMethod = "CASH";

                var paymentData = new
                {
                    orderId = dto.ServiceRequestId.ToString(),
                    customerId = dto.CustomerId.ToString(),
                    amount = dto.Amount,
                    currency = "VND",
                    method = paymentMethod
                };

                var paymentResponse = await httpClient.PostAsJsonAsync(paymentServiceUrl, paymentData);
                
                if (!paymentResponse.IsSuccessStatusCode)
                {
                    var errorContent = await paymentResponse.Content.ReadAsStringAsync();
                    Console.WriteLine($"Error creating payment in service-3: {paymentResponse.StatusCode} - {errorContent}");
                    return StatusCode((int)paymentResponse.StatusCode, new { 
                        message = "Lỗi khi tạo payment trong payment-service", 
                        error = errorContent 
                    });
                }

                var paymentResult = await paymentResponse.Content.ReadFromJsonAsync<System.Text.Json.JsonElement>();
                var paymentId = paymentResult.TryGetProperty("id", out var idProp) ? idProp.GetString() : null;

                // Xác nhận payment ngay (trong môi trường thực tế, cần xác nhận từ ngân hàng)
                if (!string.IsNullOrEmpty(paymentId))
                {
                    try
                    {
                        var confirmResponse = await httpClient.PostAsJsonAsync(
                            $"{paymentServiceUrl}/{paymentId}/confirm",
                            new { result = "SUCCESS" }
                        );
                        
                        if (confirmResponse.IsSuccessStatusCode)
                        {
                            // Cập nhật paid status trong service-1
                            request.Paid = true;
                            await _context.SaveChangesAsync();
                            
                            // Invalidate cache
                            await InvalidateRequestCache(requestId: dto.ServiceRequestId, customerId: dto.CustomerId);
                        }
                    }
                    catch (Exception confirmEx)
                    {
                        Console.WriteLine($"Warning: Failed to confirm payment: {confirmEx.Message}");
                        // Vẫn trả về success vì payment đã được tạo
                    }
                }

                // Trả về kết quả từ service-3
                return Ok(new
                {
                    id = paymentId,
                    customerId = dto.CustomerId,
                    serviceRequestId = dto.ServiceRequestId,
                    amount = dto.Amount,
                    paymentMethod = paymentMethod,
                    status = "PENDING",
                    message = "Payment created successfully in payment-service"
                });
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Error in CreatePayment: {ex.Message}");
                Console.WriteLine($"Stack trace: {ex.StackTrace}");
                return StatusCode(500, new
                {
                    message = "Lỗi khi tạo payment",
                    error = ex.Message
                });
            }
        }

        // GET: api/Customer/transactions/{customerId}
        [HttpGet("transactions/{customerId}")]
        public async Task<IActionResult> GetCustomerTransactions(int customerId)
        {
            var transactions = await _context.CustomerTransactions
                .Where(t => t.CustomerId == customerId)
                .Select(t => new
                {
                    id = t.Id,
                    customer_id = t.CustomerId,
                    description = t.Description,
                    amount = t.Amount,
                    transaction_type = t.TransactionType.ToString(),
                    date = t.Date,
                    payment_id = t.PaymentId
                })
                .ToListAsync();

            if (!transactions.Any())
                return NotFound(new { message = "No transactions found" });

            return Ok(transactions);
        }

        // DTOs
        public class CreateCustomerDto
        {
            public string Name { get; set; } = string.Empty;
            public string Email { get; set; } = string.Empty;
            public string? Phone { get; set; }
            public string? Address { get; set; }
            public int? UserId { get; set; }
        }

        public class UpdateCustomerDto
        {
            public string? Name { get; set; }
            public string? Phone { get; set; }
            public string? Address { get; set; }
        }

        public class CreateServiceRequestDto
        {
            public int CustomerId { get; set; }
            public string ServiceType { get; set; } = string.Empty;
            public string Title { get; set; } = string.Empty;
            public string? Description { get; set; }
            public string? FileName { get; set; }
            public DateTime? DueDate { get; set; }
            public string? Priority { get; set; }
            public string? Status { get; set; } // Thêm field Status
        }

        public class UpdateRequestStatusDto
        {
            public string Status { get; set; } = string.Empty;
        }

        public class CreateFeedbackDto
        {
            public int RequestId { get; set; }
            public string FeedbackText { get; set; } = string.Empty;
            public bool? RevisionNeeded { get; set; }
        }

        public class CreatePaymentDto
        {
            public int CustomerId { get; set; }
            public int ServiceRequestId { get; set; }
            public decimal Amount { get; set; }
            public string PaymentMethod { get; set; } = string.Empty;
        }

        public class SelectExpertAndScheduleDto
        {
            public int SpecialistId { get; set; }
            public DateTime ScheduledDate { get; set; }
            public string TimeSlot { get; set; } = string.Empty; // "0-4", "6-10", "12-16", "18-22"
            public string? MeetingNotes { get; set; }
        }

        // PATCH: api/Customer/requests/{id}/paid
        [HttpPatch("requests/{id}/paid")]
        public async Task<IActionResult> UpdateRequestPaidStatus(int id, [FromBody] UpdatePaidStatusDto dto)
        {
            var request = await _context.ServiceRequests.FindAsync(id);
            if (request == null)
                return NotFound(new { message = "Request not found" });

            request.Paid = dto.Paid;
            await _context.SaveChangesAsync();

            // Invalidate cache để customer dashboard thấy thay đổi ngay lập tức
            await InvalidateRequestCache(requestId: id, customerId: request.CustomerId);

            return Ok(new
            {
                id = request.Id,
                paid = request.Paid,
                message = "Paid status updated successfully"
            });
        }

        public class UpdatePaidStatusDto
        {
            public bool Paid { get; set; }
        }
    }
}

