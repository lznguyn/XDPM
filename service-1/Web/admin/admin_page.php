<?php
// Cấu hình timezone UTC+7 (Vietnam Time)
date_default_timezone_set('Asia/Ho_Chi_Minh');

session_start();
$admin_id = $_SESSION['user']['id'] ?? null;
if (!$admin_id) {
    header('location:login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng điều khiển Admin - MuTraPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e40af',
                        secondary: '#f59e0b',
                        accent: '#10b981',
                        danger: '#dc2626',
                        success: '#059669',
                        warning: '#d97706',
                        info: '#0284c7'
                    }
                }
            }
        }
    </script>
</head>
<?php include 'admin_header.php'; ?>
<body class="bg-gray-50">

<div class="min-h-screen pt-20">
    <!-- Chào mừng -->
    <div class="bg-gradient-to-r from-primary to-blue-600 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold mb-2">Chào mừng trở lại, Admin!</h1>
                <p class="text-blue-100">Tổng quan hoạt động hệ thống MuTraPro hôm nay</p>
            </div>
            <div class="hidden md:block bg-white bg-opacity-20 rounded-xl p-4 text-center">
                <div class="text-2xl font-bold" id="day"><?php echo date('d'); ?></div>
                <div class="text-sm" id="month"><?php echo date('M Y'); ?></div>
            </div>
        </div>
    </div>

    <!-- Thống kê -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Error Message -->
        <div id="error-message" class="hidden bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <div>
                    <p class="font-bold">Lỗi tải dữ liệu</p>
                    <p id="error-text" class="text-sm"></p>
                </div>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div id="loading-indicator" class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-4xl text-blue-600"></i>
            <p class="mt-4 text-gray-600">Đang tải dữ liệu thống kê...</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" id="stats-grid" style="display: none;">
            <!-- Cards sẽ được tạo bằng JS -->
        </div>
    </div>
</div>

<script>
// Hàm tạo card thống kê
function createStatCard(icon, title, value, colorClass, subtitle = '') {
    return `
    <div class="bg-white rounded-2xl shadow p-6 hover:shadow-lg transition">
        <div class="flex justify-between mb-4">
            <div class="${colorClass} p-3 rounded-xl">
                <i class="${icon} text-xl"></i>
            </div>
        </div>
        <h3 class="text-2xl font-bold text-gray-900">${value}</h3>
        <p class="text-gray-600 text-sm mt-1">${subtitle || title}</p>
    </div>
    `;
}

// Lấy dữ liệu API ASP.NET Core
async function loadStats() {
    const token = '<?php echo $_SESSION['token'] ?? ''; ?>'; 
    const errorDiv = document.getElementById('error-message');
    const errorText = document.getElementById('error-text');
    const loadingIndicator = document.getElementById('loading-indicator');
    const statsGrid = document.getElementById('stats-grid');

    // Ẩn error message ban đầu
    errorDiv.classList.add('hidden');

    // Kiểm tra token
    if (!token) {
        showError('Lỗi: Không tìm thấy token JWT trong Session. Vui lòng đăng nhập lại.');
        loadingIndicator.style.display = 'none';
        return;
    }

    try {
        console.log('Fetching stats from API with token:', token.substring(0, 20) + '...');
        
        const apiBase = '<?php require_once __DIR__ . "/../config.php"; echo getApiBaseUrl("Admin"); ?>';
        const res = await fetch(apiBase + '/stats', {
            method: 'GET',
            headers: { 
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });

        console.log('Response status:', res.status);
        console.log('Response statusText:', res.statusText);
        
        // Đọc response dưới dạng text trước (chỉ đọc 1 lần)
        const responseText = await res.text();
        console.log('Response text length:', responseText.length);
        console.log('Response text preview:', responseText.substring(0, 500));
        
        // Kiểm tra xem response có rỗng không
        if (!responseText || responseText.trim() === '') {
            showError(`Lỗi: Server trả về response rỗng (Status: ${res.status}). Vui lòng kiểm tra server logs hoặc xem Console để biết thêm chi tiết.`);
            loadingIndicator.style.display = 'none';
            return;
        }

        // Kiểm tra xem response có phải JSON không
        let data;
        try {
            data = JSON.parse(responseText);
            console.log('Parsed JSON data successfully:', data);
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.error('Full response text:', responseText);
            
            // Kiểm tra xem có phải HTML error page không
            if (responseText.includes('<!DOCTYPE') || responseText.includes('<html>') || responseText.includes('<head>')) {
                showError(`Lỗi ${res.status}: Server trả về HTML error page. API có thể không khả dụng hoặc có lỗi server. Vui lòng kiểm tra server logs.`);
            } else {
                showError(`Lỗi parse JSON: ${parseError.message}. Response preview: ${responseText.substring(0, 200)}...`);
            }
            loadingIndicator.style.display = 'none';
            return;
        }

        // Kiểm tra response status
        if (!res.ok) {
            // Nếu response không ok, hiển thị error từ server
            const errorMsg = data?.message || data?.error || `HTTP ${res.status}: ${res.statusText}`;
            showError(`Lỗi ${res.status}: ${errorMsg}`);
            if (data?.stackTrace) {
                console.error('Server stack trace:', data.stackTrace);
            }
            loadingIndicator.style.display = 'none';
            return;
        }

        // Ẩn loading indicator
        loadingIndicator.style.display = 'none';
        
        // Hiển thị stats grid
        statsGrid.style.display = 'grid';
        
        // Tạo các card thống kê
        statsGrid.innerHTML = `
            ${createStatCard('fas fa-clock text-warning', 'Tổng tiền chờ xử lý', new Intl.NumberFormat('vi-VN').format(data.total_pendings || 0) + ' VNĐ', 'bg-warning bg-opacity-10')}
            ${createStatCard('fas fa-check-circle text-success', 'Tổng tiền đã thanh toán', new Intl.NumberFormat('vi-VN').format(data.total_completed || 0) + ' VNĐ', 'bg-success bg-opacity-10')}
            ${createStatCard('fas fa-shopping-cart text-info', 'Tổng đơn hàng', data.orders_count || 0, 'bg-info bg-opacity-10')}
            ${createStatCard('fas fa-music text-purple-600', 'Dịch vụ âm nhạc', data.products_count || 0, 'bg-purple-100')}
            ${createStatCard('fas fa-music text-purple-600', 'Yêu cầu nhạc chưa hoàn tất', data.musicsub_pending_count || 0, 'bg-purple-100')}
            ${createStatCard('fas fa-music text-purple-600', 'Yêu cầu nhạc đã hoàn tất', data.musicsub_completed_count || 0, 'bg-purple-100')}
            ${createStatCard('fas fa-user text-purple-600', 'Chuyên gia', data.experts_count || 0, 'bg-purple-100')}
            ${createStatCard('fas fa-clock text-warning', 'Booking đang chờ xử lý', data.pending_orders_count || 0, 'bg-warning bg-opacity-10')}
            ${createStatCard('fas fa-check-circle text-success', 'Booking đã hoàn thành', data.completed_orders_count || 0, 'bg-success bg-opacity-10')}
            ${createStatCard('fas fa-users text-green-600', 'Người dùng', data.users_count || 0, 'bg-green-100')}
            ${createStatCard('fas fa-user-shield text-red-600', 'Quản trị viên', data.admins_count || 0, 'bg-red-100')}
            ${createStatCard('fas fa-user-tie text-green-600', 'Staff', data.staff_count || 0, 'bg-green-100')}
            ${createStatCard('fas fa-comments text-yellow-600', 'Phòng thu âm', data.studios_count || 0, 'bg-yellow-100')}
        `;
    } catch (err) {
        console.error('Error loading stats:', err);
        showError('Lỗi kết nối: ' + err.message + '. Vui lòng kiểm tra lại kết nối mạng hoặc thử lại sau.');
        loadingIndicator.style.display = 'none';
    }
}

function showError(message) {
    const errorDiv = document.getElementById('error-message');
    const errorText = document.getElementById('error-text');
    errorText.textContent = message;
    errorDiv.classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', loadStats);
</script>

</body>
</html>
