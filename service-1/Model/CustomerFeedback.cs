using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace MuTraProAPI.Models
{
    public class CustomerFeedback
    {
        [Key]
        [Column("id")]
        public int Id { get; set; }

        [Required]
        [Column("request_id")]
        public int RequestId { get; set; }

        [ForeignKey("RequestId")]
        public ServiceRequest? ServiceRequest { get; set; }

        [Required]
        [Column("feedback_text")]
        public string FeedbackText { get; set; } = string.Empty;

        [Column("revision_needed")]
        public bool RevisionNeeded { get; set; } = false;

        [Column("created_date")]
        public DateTime CreatedDate { get; set; } = DateTime.Now;
    }
}

