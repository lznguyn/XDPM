using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;
namespace MuTraProAPI.Models
{
    public class Studio
    {
        [Key]
        [Column("id")]
        public int Id { get; set; }

        [Required]
        [Column("name")]
        public string Name { get; set; } = string.Empty;

        [Required]
        [Column("location")]
        public string Location { get; set; } = string.Empty;

        [Column("image")]
        public string? Image { get; set; }

        [Required]
        [Column("price")]
        public decimal Price { get; set; }

        [Required]
        [Column("status")]
        public StudioStatus Status { get; set; } = StudioStatus.Available; 
    }
    public enum StudioStatus
    {
        Available,
        Occupied,
        UnderMaintenance
    }
}