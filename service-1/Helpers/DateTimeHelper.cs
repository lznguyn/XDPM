using System;
using System.Globalization;

namespace MuTraProAPI.Helpers
{
    /// <summary>
    /// Helper class để xử lý DateTime với timezone UTC+7 (Vietnam Time)
    /// </summary>
    public static class DateTimeHelper
    {
        private static readonly TimeZoneInfo VietnamTimeZone;

        static DateTimeHelper()
        {
            try
            {
                // Thử tìm timezone "SE Asia Standard Time" (UTC+7) trên Windows
                VietnamTimeZone = TimeZoneInfo.FindSystemTimeZoneById("SE Asia Standard Time");
            }
            catch
            {
                try
                {
                    // Thử tìm trên Linux/Mac
                    VietnamTimeZone = TimeZoneInfo.FindSystemTimeZoneById("Asia/Ho_Chi_Minh");
                }
                catch
                {
                    // Nếu không tìm thấy, tạo custom timezone UTC+7
                    VietnamTimeZone = TimeZoneInfo.CreateCustomTimeZone(
                        "Vietnam Standard Time",
                        TimeSpan.FromHours(7),
                        "Vietnam Standard Time",
                        "Vietnam Standard Time"
                    );
                }
            }
        }

        /// <summary>
        /// Lấy thời gian hiện tại theo timezone UTC+7
        /// </summary>
        public static DateTime Now => TimeZoneInfo.ConvertTimeFromUtc(DateTime.UtcNow, VietnamTimeZone);

        /// <summary>
        /// Chuyển đổi DateTime từ UTC sang UTC+7
        /// </summary>
        public static DateTime ToVietnamTime(DateTime utcDateTime)
        {
            if (utcDateTime.Kind == DateTimeKind.Unspecified)
            {
                // Nếu là Unspecified, giả sử là UTC
                return TimeZoneInfo.ConvertTimeFromUtc(DateTime.SpecifyKind(utcDateTime, DateTimeKind.Utc), VietnamTimeZone);
            }
            if (utcDateTime.Kind == DateTimeKind.Utc)
            {
                return TimeZoneInfo.ConvertTimeFromUtc(utcDateTime, VietnamTimeZone);
            }
            // Nếu đã là Local, chuyển sang UTC rồi sang Vietnam time
            return TimeZoneInfo.ConvertTimeFromUtc(utcDateTime.ToUniversalTime(), VietnamTimeZone);
        }

        /// <summary>
        /// Chuyển đổi DateTime từ UTC+7 sang UTC
        /// </summary>
        public static DateTime ToUtc(DateTime vietnamDateTime)
        {
            if (vietnamDateTime.Kind == DateTimeKind.Utc)
            {
                return vietnamDateTime;
            }
            return TimeZoneInfo.ConvertTimeToUtc(vietnamDateTime, VietnamTimeZone);
        }

        /// <summary>
        /// Format DateTime theo định dạng Việt Nam
        /// </summary>
        public static string FormatVietnam(DateTime dateTime, string format = "dd/MM/yyyy HH:mm:ss")
        {
            return dateTime.ToString(format, new CultureInfo("vi-VN"));
        }
    }
}

