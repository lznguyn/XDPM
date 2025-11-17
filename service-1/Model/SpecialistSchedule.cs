using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace MuTraProAPI.Models
{
    public class SpecialistSchedule
    {
        [Key]
        [Column("id")]
        public int Id { get; set; }

        [Required]
        [Column("specialist_id")]
        public int SpecialistId { get; set; }

        [ForeignKey("SpecialistId")]
        public User? Specialist { get; set; }

        [Required]
        [Column("date")]
        public DateTime Date { get; set; }

        [Column("time_slot_1")]
        public bool TimeSlot1 { get; set; } = false; // 0h-4h

        [Column("time_slot_2")]
        public bool TimeSlot2 { get; set; } = false; // 6h-10h

        [Column("time_slot_3")]
        public bool TimeSlot3 { get; set; } = false; // 12h-16h

        [Column("time_slot_4")]
        public bool TimeSlot4 { get; set; } = false; // 18h-22h

        [Column("created_at")]
        public DateTime CreatedAt { get; set; } = DateTime.Now;

        [Column("updated_at")]
        public DateTime UpdatedAt { get; set; } = DateTime.Now;
    }
}

