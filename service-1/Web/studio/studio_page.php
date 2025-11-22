<?php
session_start();
// Kiểm tra đăng nhập - chỉ cho phép Studio role
$studio_id = $_SESSION['user']['id'] ?? null;
$studio_role = $_SESSION['user']['role'] ?? '';

if (!$studio_id || strtolower($studio_role) !== 'studio') {
    header('location:../login.php?error=studio_only');
    exit();
}
$message = [];
// Gọi qua Kong Gateway đến customer-service
$api_url = "http://localhost:8000/studios"; // API endpoint qua gateway
$booking_api_url = "http://localhost:8000/api/StudioBooking"; // API endpoint cho booking
$token = $_SESSION['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý chuyên gia - MuTraPro Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
<?php include 'studio_header.php'; ?>

<div class="min-h-screen pt-20 max-w-7xl mx-auto px-4 py-8">
    <div class="bg-white rounded-2xl shadow-sm border p-6 mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Thêm studio mới</h2>
        <form id="addExpertForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <input type="text" name="name" placeholder="Tên" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                <input type="text" name="location" placeholder="Địa điểm" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                <input type="number" name="price" id="priceInput" placeholder="Giá (VNĐ)" required min="0" step="0.01"
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl">       
                 <select name="status" class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                    <option value="available" selected>Còn chỗ</option>
                    <option value="occupied">Đã có người đặt</option>
                    <option value="underMaintenance">Chưa đặt</option>
                </select>
                <input type="file" name="image" accept="image/*"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl">
            </div>
            <button type="submit" class="bg-red-500 text-white px-8 py-3 rounded-xl hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>Thêm studio
            </button>
        </form>
        <div id="addMessage" class="mt-2 text-green-600"></div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border p-6 mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Danh sách studio</h2>
        <div id="expertsList" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <!-- Danh sách studio sẽ load bằng JS -->
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-900">Yêu cầu đặt phòng</h2>
            <button onclick="loadBookingRequests()" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                <i class="fas fa-sync-alt mr-2"></i>Làm mới
            </button>
        </div>
        <div id="bookingRequestsList" class="space-y-4">
            <!-- Danh sách yêu cầu đặt phòng sẽ load bằng JS -->
        </div>
    </div>
</div>

<script>
const apiUrl = '<?php echo $api_url; ?>';
const bookingApiUrl = '<?php echo $booking_api_url; ?>';
const token = '<?php echo $token; ?>';

// ===== Load danh sách chuyên gia =====
async function loadExperts() {
    try {
        // Kiểm tra và cập nhật trạng thái studio trước khi load
        try {
            await fetch(bookingApiUrl + '/check-dates');
        } catch (e) {
            console.warn('Không thể gọi check-dates:', e);
        }
        
        const res = await fetch(apiUrl);
        const data = await res.json();
        const container = document.getElementById('expertsList');
        container.innerHTML = '';
        
        if (!data.data || data.data.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-center py-8">Chưa có studio nào</p>';
            return;
        }
        
        data.data.forEach(exp => {
            // Convert status: có thể là số (0,1,2) hoặc string ("Available", "Occupied", "UnderMaintenance")
            let statusNum = exp.status;
            if (typeof exp.status === 'string') {
                const statusMap = {
                    'Available': 0,
                    'Occupied': 1,
                    'UnderMaintenance': 2,
                    'available': 0,
                    'occupied': 1,
                    'underMaintenance': 2
                };
                statusNum = statusMap[exp.status] ?? 0;
            }
            statusNum = Number(statusNum) || 0;
            
            const statusText = ['Còn chỗ', 'Đã có người đặt', 'Chưa đặt'][statusNum] || 'Không xác định';
            const statusColor = statusNum === 0 ? 'text-green-600' : statusNum === 1 ? 'text-red-600' : 'text-yellow-600';
            
            container.innerHTML += `
            <div class="bg-gray-50 rounded-2xl p-4 hover:shadow-lg transition-all">
                <div class="relative mb-4">
                    <img src="${exp.image ?? 'uploaded_img/default.png'}" alt="${exp.name}" class="w-full h-48 object-cover rounded-xl">
                </div>
                <h3 class="font-bold text-gray-900 text-lg mb-2">${exp.name}</h3>
                <h3 class="font-bold text-gray-900 text-lg mb-2">${exp.location}</h3>
                <span class="text-sm ${statusColor} mb-2 block font-semibold">${statusText}</span>
                <p class="text-gray-600 text-sm mb-2">Giá: ${new Intl.NumberFormat('vi-VN').format(exp.price || 0)} VNĐ</p>
                <div class="flex space-x-2">
                    <button onclick="editExpert(${exp.id})" class="flex-1 bg-warning text-white py-2 rounded-lg text-sm font-medium bg-red-500">Sửa</button>
                    <button onclick="deleteExpert(${exp.id})" class="flex-1 bg-danger text-white py-2 rounded-lg text-sm font-medium bg-red-500">Xóa</button>
                </div>
            </div>
            `;
        });
    } catch (error) {
        console.error('Error loading experts:', error);
        const container = document.getElementById('expertsList');
        container.innerHTML = '<p class="text-red-500 text-center py-8">Lỗi tải danh sách studio</p>';
    }
}

// ===== Thêm chuyên gia =====
document.getElementById('addExpertForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);

    const statusMap = {
        "available": 0,
        "occupied": 1,
        "underMaintenance": 2
    };

    // Lấy giá trị price từ input trực tiếp để đảm bảo lấy được giá trị
    const priceInput = document.getElementById('priceInput');
    const priceValue = priceInput ? priceInput.value : formData.get('price');
    const price = priceValue && priceValue.trim() !== '' ? parseFloat(priceValue) : 0;
    
    if (isNaN(price) || price < 0) {
        alert('Vui lòng nhập giá tiền hợp lệ (phải là số lớn hơn hoặc bằng 0)!');
        priceInput?.focus();
        return;
    }

    const obj = {
        name: formData.get('name'),
        location: formData.get('location'),
        price: price,
        status: statusMap[formData.get('status')] || 0,
        image: null
    };

    console.log('Sending studio data:', obj); // Debug

    const res = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(obj)
    });

    const data = await res.json();
    document.getElementById('addMessage').innerText = data.message;
    loadExperts();
});


// ===== Xóa chuyên gia =====
async function deleteExpert(id) {
    if (!confirm('Bạn có chắc muốn xóa chuyên gia này?')) return;
    const res = await fetch(`${apiUrl}/${id}`, { method: 'DELETE' });
    const data = await res.json();
    alert(data.message);
    loadExperts();
}

// ===== Sửa chuyên gia (mở form mới hoặc modal) =====
function editExpert(id) {
    // Redirect sang page edit với id
    window.location.href = `studio_edit.php?id=${id}`;
}

// ===== Load danh sách yêu cầu đặt phòng =====
async function loadBookingRequests() {
    try {
        // Kiểm tra và cập nhật trạng thái studio trước khi load
        try {
            await fetch(bookingApiUrl + '/check-dates');
        } catch (e) {
            console.warn('Không thể gọi check-dates:', e);
        }
        
        const res = await fetch(bookingApiUrl + '/requests', {
            headers: {
                'Authorization': 'Bearer ' + token
            }
        });
        const data = await res.json();
        const container = document.getElementById('bookingRequestsList');
        container.innerHTML = '';
        
        if (!data.data || data.data.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-center py-8">Chưa có yêu cầu đặt phòng nào</p>';
            return;
        }
        
        data.data.forEach(booking => {
            const statusMap = {
                'Pending': { text: 'Chờ phê duyệt', color: 'bg-yellow-100 text-yellow-800' },
                'Approved': { text: 'Đã phê duyệt', color: 'bg-green-100 text-green-800' },
                'Rejected': { text: 'Đã từ chối', color: 'bg-red-100 text-red-800' },
                'Completed': { text: 'Đã hoàn thành', color: 'bg-blue-100 text-blue-800' }
            };
            const statusInfo = statusMap[booking.status] || { text: booking.status, color: 'bg-gray-100 text-gray-800' };
            const bookingDate = new Date(booking.booking_date).toLocaleDateString('vi-VN');
            
            container.innerHTML += `
            <div class="border rounded-xl p-4 hover:shadow-md transition-all">
                <div class="flex justify-between items-start mb-3">
                    <div class="flex-1">
                        <h3 class="font-bold text-gray-900 text-lg mb-1">${booking.studio_name || 'Studio #' + booking.studio_id}</h3>
                        <p class="text-gray-600 text-sm mb-1"><i class="fas fa-user mr-2"></i>Khách hàng: ${booking.customer_name || 'N/A'} (${booking.customer_email || 'N/A'})</p>
                        <p class="text-gray-600 text-sm mb-1"><i class="fas fa-calendar mr-2"></i>Ngày đặt: ${bookingDate}</p>
                        <p class="text-gray-600 text-sm mb-1"><i class="fas fa-clock mr-2"></i>Giờ đặt: ${booking.booking_time || 'N/A'}</p>
                        ${booking.notes ? `<p class="text-gray-600 text-sm mb-1"><i class="fas fa-sticky-note mr-2"></i>Ghi chú: ${booking.notes}</p>` : ''}
                        ${booking.approved_date ? `<p class="text-gray-500 text-xs mt-1">Phê duyệt: ${new Date(booking.approved_date).toLocaleString('vi-VN')}</p>` : ''}
                    </div>
                    <div class="ml-4">
                        <span class="px-3 py-1 rounded-full text-sm font-semibold ${statusInfo.color}">${statusInfo.text}</span>
                    </div>
                </div>
                ${booking.status === 'Pending' ? `
                <div class="flex space-x-2 mt-3">
                    <button onclick="approveBooking(${booking.id})" class="flex-1 bg-green-500 text-white py-2 rounded-lg text-sm font-medium hover:bg-green-600">
                        <i class="fas fa-check mr-2"></i>Phê duyệt
                    </button>
                    <button onclick="rejectBooking(${booking.id})" class="flex-1 bg-red-500 text-white py-2 rounded-lg text-sm font-medium hover:bg-red-600">
                        <i class="fas fa-times mr-2"></i>Từ chối
                    </button>
                </div>
                ` : ''}
            </div>
            `;
        });
    } catch (error) {
        console.error('Error loading booking requests:', error);
        const container = document.getElementById('bookingRequestsList');
        container.innerHTML = '<p class="text-red-500 text-center py-8">Lỗi tải danh sách yêu cầu đặt phòng</p>';
    }
}

// ===== Phê duyệt yêu cầu đặt phòng =====
async function approveBooking(bookingId) {
    if (!confirm('Bạn có chắc muốn phê duyệt yêu cầu đặt phòng này?')) return;
    
    try {
        const res = await fetch(bookingApiUrl + '/' + bookingId + '/approve', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            }
        });
        const data = await res.json();
        
        if (res.ok) {
            alert(data.message || 'Đã phê duyệt yêu cầu thành công!');
            loadBookingRequests();
            loadExperts(); // Refresh danh sách studio
        } else {
            alert('Lỗi: ' + (data.message || 'Không thể phê duyệt yêu cầu'));
        }
    } catch (error) {
        console.error('Error approving booking:', error);
        alert('Lỗi: ' + error.message);
    }
}

// ===== Từ chối yêu cầu đặt phòng =====
async function rejectBooking(bookingId) {
    const reason = prompt('Vui lòng nhập lý do từ chối (tùy chọn):');
    if (reason === null) return; // User cancelled
    
    try {
        const res = await fetch(bookingApiUrl + '/' + bookingId + '/reject', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ reason: reason || '' })
        });
        const data = await res.json();
        
        if (res.ok) {
            alert(data.message || 'Đã từ chối yêu cầu thành công!');
            loadBookingRequests();
        } else {
            alert('Lỗi: ' + (data.message || 'Không thể từ chối yêu cầu'));
        }
    } catch (error) {
        console.error('Error rejecting booking:', error);
        alert('Lỗi: ' + error.message);
    }
}

// Load khi trang được mở
loadExperts();
loadBookingRequests();

// Auto-refresh danh sách studio và booking requests mỗi 10 giây để cập nhật status
setInterval(() => {
    loadExperts();
    loadBookingRequests();
}, 10000); // Refresh mỗi 10 giây
</script>
</body>
</html>
