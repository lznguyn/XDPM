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

    }
}
