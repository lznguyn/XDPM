using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace MuTraProAPI.Models
{
    public class StudioBooking
    {
        [Key]
        [Column("id")]
        public int Id { get; set; }

        [Required]
        [Column("studio_id")]
        public int StudioId { get; set; }

        [ForeignKey("StudioId")]
        public Studio? Studio { get; set; }

        [Required]
        [Column("service_request_id")]
        public int ServiceRequestId { get; set; }

        [ForeignKey("ServiceRequestId")]
        public ServiceRequest? ServiceRequest { get; set; }

        [Required]
        [Column("customer_id")]
        public int CustomerId { get; set; }

        [ForeignKey("CustomerId")]
        public Customer? Customer { get; set; }

        [Required]
        [Column("booking_date")]
        public DateTime BookingDate { get; set; }

        [Required]
        [Column("booking_time")]
        public string BookingTime { get; set; } = string.Empty;

        [Required]
        [Column("status")]
        public BookingStatus Status { get; set; } = BookingStatus.Pending;

        [Column("created_date")]
        public DateTime CreatedDate { get; set; } = DateTime.UtcNow;

        [Column("approved_date")]
        public DateTime? ApprovedDate { get; set; }

        [Column("notes")]
        public string? Notes { get; set; }
    }

    public enum BookingStatus
    {
        Pending,      // Đang chờ studio phê duyệt
        Approved,     // Studio đã phê duyệt
        Rejected,     // Studio từ chối
        Completed     // Đã hoàn thành (sau khi đến ngày đặt)
    }
}

