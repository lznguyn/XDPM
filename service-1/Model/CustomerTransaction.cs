using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace MuTraProAPI.Models
{
    public class CustomerTransaction
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
        [Column("description")]
        public string Description { get; set; } = string.Empty;

        [Required]
        [Column("amount")]
        public decimal Amount { get; set; }

        [Required]
        [Column("transaction_type")]
        public TransactionType TransactionType { get; set; }

        [Column("date")]
        public DateTime Date { get; set; } = DateTime.Now;

        [Column("payment_id")]
        public int? PaymentId { get; set; }

        [ForeignKey("PaymentId")]
        public CustomerPayment? Payment { get; set; }
    }

    public enum TransactionType
    {
        Payment,
        Refund,
        Credit
    }
}

