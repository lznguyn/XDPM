using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;


namespace MuTraProAPI.Models
{
    public class MusicSubmission
    {
        [Key]
        [Column("id")]
        public int Id { get; set; }
        [Required]
        [Column("user_id")]
        public int UserId { get; set; }
        [Required]
        [Column("title")]
        public string Title { get; set; } = string.Empty;
        [Required]
        [Column("file_name")]
        public string File_Name { get; set; } = string.Empty;
        [Required]
        [Column("note")]
        public string Note { get; set; } = string.Empty;
        [Required]
        [Column("status")]
        public MusicStatus Status { get; set; } = MusicStatus.Pending;
        [Required]
        [Column("create_at")]
        public DateTime Create_at { get; set; } = DateTime.Now;
        [Required]
        [Column("target_role")]
        public string TargetRole { get; set; } = string.Empty;
    }
    public enum MusicStatus
    {
        Pending,
        Completed,
        Rejected
    }
}