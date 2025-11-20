using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace MuTraProAPI.Models
{
    public class Notification
    {
        [Key]
        [Column("id")]
        public int Id { get; set; }

        [Required]
        [Column("customer_id")]
        public int CustomerId { get; set; }

        [ForeignKey("CustomerId")]
        public Customer? Customer { get; set; }

        [Column("service_request_id")]
        public int? ServiceRequestId { get; set; }

        [ForeignKey("ServiceRequestId")]
        public ServiceRequest? ServiceRequest { get; set; }

        [Required]
        [Column("title")]
        public string Title { get; set; } = string.Empty;

        [Required]
        [Column("message")]
        public string Message { get; set; } = string.Empty;

        [Required]
        [Column("type")]
        public NotificationType Type { get; set; } = NotificationType.Info;

        [Required]
        [Column("is_read")]
        public bool IsRead { get; set; } = false;

        [Required]
        [Column("created_at")]
        public DateTime CreatedAt { get; set; } = DateTime.UtcNow;

        [Column("read_at")]
        public DateTime? ReadAt { get; set; }
    }

    public enum NotificationType
    {
        Info,           // Thông tin chung
        Success,        // Thành công (yêu cầu được chấp nhận, hoàn thành)
        Warning,        // Cảnh báo (cần chú ý)
        Error,          // Lỗi (yêu cầu bị từ chối)
        StatusChange    // Thay đổi trạng thái
    }
}

