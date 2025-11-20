using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using MuTraProAPI.Models;
using MuTraProAPI.Data;
using MuTraProAPI.Helpers;

namespace MuTraProAPI.Controllers
{
    [ApiController]
    [Route("api/[controller]")]
    public class NotificationController : ControllerBase
    {
        private readonly MuTraProDbContext _context;

        public NotificationController(MuTraProDbContext context)
        {
            _context = context;
        }

        // GET: api/Notification/customer/{customerId}
        [HttpGet("customer/{customerId}")]
        public async Task<IActionResult> GetCustomerNotifications(int customerId, [FromQuery] bool? unreadOnly = false)
        {
            IQueryable<Notification> query = _context.Notifications
                .Where(n => n.CustomerId == customerId)
                .Include(n => n.ServiceRequest);

            if (unreadOnly == true)
            {
                query = query.Where(n => !n.IsRead);
            }

            var notifications = await query
                .OrderByDescending(n => n.CreatedAt)
                .Select(n => new
                {
                    n.Id,
                    n.CustomerId,
                    ServiceRequestId = n.ServiceRequestId,
                    ServiceRequestTitle = n.ServiceRequest != null ? n.ServiceRequest.Title : null,
                    n.Title,
                    n.Message,
                    n.Type,
                    n.IsRead,
                    n.CreatedAt,
                    n.ReadAt
                })
                .ToListAsync();

            return Ok(notifications);
        }

        // GET: api/Notification/customer/{customerId}/unread-count
        [HttpGet("customer/{customerId}/unread-count")]
        public async Task<IActionResult> GetUnreadCount(int customerId)
        {
            var count = await _context.Notifications
                .Where(n => n.CustomerId == customerId && !n.IsRead)
                .CountAsync();

            return Ok(new { count });
        }

        // PATCH: api/Notification/{id}/read
        [HttpPatch("{id}/read")]
        public async Task<IActionResult> MarkAsRead(int id)
        {
            var notification = await _context.Notifications.FindAsync(id);
            if (notification == null)
                return NotFound();

            notification.IsRead = true;
            notification.ReadAt = DateTimeHelper.Now;
            await _context.SaveChangesAsync();

            return Ok(new { message = "Notification marked as read" });
        }

        // PATCH: api/Notification/customer/{customerId}/read-all
        [HttpPatch("customer/{customerId}/read-all")]
        public async Task<IActionResult> MarkAllAsRead(int customerId)
        {
            var notifications = await _context.Notifications
                .Where(n => n.CustomerId == customerId && !n.IsRead)
                .ToListAsync();

            foreach (var notification in notifications)
            {
                notification.IsRead = true;
                notification.ReadAt = DateTimeHelper.Now;
            }

            await _context.SaveChangesAsync();

            return Ok(new { message = "All notifications marked as read", count = notifications.Count });
        }

        // POST: api/Notification
        [HttpPost]
        public async Task<IActionResult> CreateNotification([FromBody] CreateNotificationDto dto)
        {
            var notification = new Notification
            {
                CustomerId = dto.CustomerId,
                ServiceRequestId = dto.ServiceRequestId,
                Title = dto.Title,
                Message = dto.Message,
                Type = dto.Type,
                CreatedAt = DateTimeHelper.Now
            };

            _context.Notifications.Add(notification);
            await _context.SaveChangesAsync();

            return Ok(new
            {
                notification.Id,
                notification.CustomerId,
                notification.ServiceRequestId,
                notification.Title,
                notification.Message,
                notification.Type,
                notification.IsRead,
                notification.CreatedAt
            });
        }
    }

    public class CreateNotificationDto
    {
        public int CustomerId { get; set; }
        public int? ServiceRequestId { get; set; }
        public string Title { get; set; } = string.Empty;
        public string Message { get; set; } = string.Empty;
        public NotificationType Type { get; set; } = NotificationType.Info;
    }
}

