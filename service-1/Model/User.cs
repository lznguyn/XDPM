using System.ComponentModel.DataAnnotations;

namespace MuTraProAPI.Models
{
    public class User
    {
        [Key]
        public int Id { get; set; }
        [Required]
        public string Name { get; set; } = string.Empty;
        [Required]
        public string Email { get; set; } = string.Empty;
        [Required]
        public string PasswordHash { get; set; } = string.Empty;
        [Required]
        public UserRole Role { get; set; }
    }
    public enum UserRole
    {
        Admin,
        User,
        Coordinator,
        Arrangement,
        Transcription,
        Recorder,
        Studio
    }
}