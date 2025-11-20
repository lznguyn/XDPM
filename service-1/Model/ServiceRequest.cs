using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace MuTraProAPI.Models
{
    public class ServiceRequest
    {
        [Key]
        [Column("id")]
        public int Id { get; set; }

        [Required]
        [Column("customer_id")]
        public int CustomerId { get; set; }

        [ForeignKey("CustomerId")]
        public Customer? Customer { get; set; }

        [Required]
        [Column("service_type")]
        public ServiceType ServiceType { get; set; }

        [Required]
        [Column("title")]
        public string Title { get; set; } = string.Empty;

        [Column("description")]
        public string? Description { get; set; }

        [Column("file_name")]
        public string? FileName { get; set; }

        [Required]
        [Column("status")]
        public RequestStatus Status { get; set; } = RequestStatus.Pending;

        [Column("created_date")]
        public DateTime CreatedDate { get; set; } = DateTime.Now;

        [Column("due_date")]
        public DateTime? DueDate { get; set; }

        [Column("assigned_specialist_id")]
        public int? AssignedSpecialistId { get; set; }

        [ForeignKey("AssignedSpecialistId")]
        public User? AssignedSpecialist { get; set; }

        [Column("priority")]
        public string Priority { get; set; } = "normal";

        [Column("paid")]
        public bool Paid { get; set; } = false;

        [Column("preferred_specialist_id")]
        public int? PreferredSpecialistId { get; set; }

        [ForeignKey("PreferredSpecialistId")]
        public User? PreferredSpecialist { get; set; }

        [Column("scheduled_date")]
        public DateTime? ScheduledDate { get; set; }

        [Column("scheduled_time_slot")]
        public string? ScheduledTimeSlot { get; set; }

        [Column("meeting_notes")]
        public string? MeetingNotes { get; set; }
    }

    public enum ServiceType
    {
        Transcription,
        Arrangement,
        Recording
    }

    public enum RequestStatus
    {
        Requested,              // Khách hàng gửi yêu cầu dịch vụ
        PendingReview,         // Admin đã duyệt yêu cầu, chờ người dùng chọn ngày/chuyên gia
        Cancelled,              // Yêu cầu bị từ chối hoặc bị hủy
        PendingMeetingConfirmation, // Người dùng đã chọn ngày + khung giờ, đang chờ chuyên gia xác nhận
        Completed,             // Chuyên gia chấp nhận gặp → yêu cầu hoàn thành, chờ thanh toán
        RejectedByExpert,      // Chuyên gia từ chối gặp (với lý do)
        // Legacy states (kept for backward compatibility)
        Pending,
        Submitted,
        Assigned,
        InProgress,
        RevisionRequested
    }
}

