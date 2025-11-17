using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using MuTraProAPI.Models;
using MuTraProAPI.Data;
using Microsoft.AspNetCore.Authorization;

namespace MuTraProAPI.Controllers
{
    [ApiController]
    [Route("api/[controller]")]
    public class AdminController : ControllerBase
    {
        private readonly MuTraProDbContext _context;

        public AdminController(MuTraProDbContext context)
        {
            _context = context;
        }

        // GET: api/Admin/stats
        [HttpGet("stats")]
        public async Task<IActionResult> GetStats()
        {
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

            return Ok(new
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
            });
        }
        // ✅ Lấy danh sách đơn hàng
        [HttpGet("orders")]
        public async Task<IActionResult> GetAllOrders()
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
                    PaymentStatus = o.PaymentStatus.ToString()
                })
                .ToListAsync();

            return Ok(orders);
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

            return Ok(new { message = "Order deleted successfully." });
        }

        // =====================================================
        // CUSTOMER MANAGEMENT ENDPOINTS
        // =====================================================

        [HttpGet("customers")]
        public async Task<IActionResult> GetAllCustomers()
        {
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

            return Ok(customers);
        }

        [HttpGet("customers/{id}")]
        public async Task<IActionResult> GetCustomerById(int id)
        {
            var customer = await _context.Customers
                .Include(c => c.User)
                .FirstOrDefaultAsync(c => c.Id == id);

            if (customer == null)
                return NotFound(new { message = "Customer not found" });

            return Ok(new
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
            });
        }

        // =====================================================
        // SERVICE REQUEST MANAGEMENT ENDPOINTS
        // =====================================================

        [HttpGet("service-requests")]
        public async Task<IActionResult> GetAllServiceRequests()
        {
            var requests = await _context.ServiceRequests
                .Include(r => r.Customer)
                .Include(r => r.AssignedSpecialist)
                .OrderByDescending(r => r.CreatedDate)
                .Select(r => new
                {
                    r.Id,
                    r.CustomerId,
                    CustomerName = r.Customer != null ? r.Customer.Name : null,
                    CustomerEmail = r.Customer != null ? r.Customer.Email : null,
                    r.ServiceType,
                    r.Title,
                    r.Description,
                    r.FileName,
                    r.Status,
                    r.CreatedDate,
                    r.DueDate,
                    r.AssignedSpecialistId,
                    AssignedSpecialistName = r.AssignedSpecialist != null ? r.AssignedSpecialist.Name : null,
                    r.Priority,
                    r.Paid
                })
                .ToListAsync();

            return Ok(requests);
        }

        [HttpGet("service-requests/{id}")]
        public async Task<IActionResult> GetServiceRequestById(int id)
        {
            var request = await _context.ServiceRequests
                .Include(r => r.Customer)
                .Include(r => r.AssignedSpecialist)
                .FirstOrDefaultAsync(r => r.Id == id);

            if (request == null)
                return NotFound(new { message = "Service request not found" });

            return Ok(new
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
            });
        }

        [HttpPatch("service-requests/{id}/status")]
        public async Task<IActionResult> UpdateServiceRequestStatus(int id, [FromBody] ServiceRequestStatusDto dto)
        {
            var request = await _context.ServiceRequests.FindAsync(id);
            if (request == null)
                return NotFound();

            if (Enum.TryParse(dto.Status, true, out RequestStatus status))
            {
                request.Status = status;
                await _context.SaveChangesAsync();
                return Ok(new { message = "Service request status updated successfully." });
            }

            return BadRequest(new { message = "Invalid status." });
        }

        [HttpPatch("service-requests/{id}/assign")]
        public async Task<IActionResult> AssignServiceRequest(int id, [FromBody] AssignServiceRequestDto dto)
        {
            var request = await _context.ServiceRequests.FindAsync(id);
            if (request == null)
                return NotFound();

            var specialist = await _context.Users.FindAsync(dto.SpecialistId);
            if (specialist == null)
                return NotFound(new { message = "Specialist not found" });

            request.AssignedSpecialistId = dto.SpecialistId;
            request.Status = RequestStatus.Assigned;
            await _context.SaveChangesAsync();

            return Ok(new { message = "Service request assigned successfully." });
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
    }
}
