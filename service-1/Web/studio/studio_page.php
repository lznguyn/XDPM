<?php
session_start();
$studio_id = $_SESSION['user']['id'] ?? null;
if (!$studio_id) {
    header('location:login.php');
    exit();
}
$message = [];
$api_url = "http://localhost:5200/api/Experts"; // API endpoint của .NET
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
                <input type="number" name="price" placeholder="Giá (VNĐ)" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl">       
                 <select name="status" onchange="toggleAdminCodeField(this)" class="w-full px-4 py-4 bg-white bg-opacity-10 border border-white border-opacity-20 rounded-xl text-white focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                    <option value="available">Còn chỗ</option>
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

    <div class="bg-white rounded-2xl shadow-sm border p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Danh sách chuyên gia</h2>
        <div id="expertsList" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <!-- Danh sách chuyên gia sẽ load bằng JS -->
        </div>
    </div>
</div>

<script>
const apiUrl = '<?php echo $api_url; ?>';

// ===== Load danh sách chuyên gia =====
async function loadExperts() {
    const res = await fetch(apiUrl);
    const data = await res.json();
    const container = document.getElementById('expertsList');
    container.innerHTML = '';
      data.data.forEach(exp => {
        const statusText = ['Còn chỗ', 'Đã có người đặt', 'Chưa đặt'][exp.status] || 'Không xác định';
        container.innerHTML += `
        <div class="bg-gray-50 rounded-2xl p-4 hover:shadow-lg transition-all">
            <div class="relative mb-4">
                <img src="${exp.image ?? 'uploaded_img/default.png'}" alt="${exp.name}" class="w-full h-48 object-cover rounded-xl">
            </div>
            <h3 class="font-bold text-gray-900 text-lg mb-2">${exp.name}</h3>
            <h3 class="font-bold text-gray-900 text-lg mb-2">${exp.location}</h3>
            <span class="text-sm text-gray-500 mb-2 block">${statusText}</span>
            <p class="text-gray-600 text-sm mb-2">Giá: ${exp.price} VNĐ</p>
            <div class="flex space-x-2">
                <button onclick="editExpert(${exp.id})" class="flex-1 bg-warning text-white py-2 rounded-lg text-sm font-medium bg-red-500">Sửa</button>
                <button onclick="deleteExpert(${exp.id})" class="flex-1 bg-danger text-white py-2 rounded-lg text-sm font-medium bg-red-500">Xóa</button>
            </div>
        </div>
        `;
    });

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

    const obj = {
        name: formData.get('name'),
        location: formData.get('location'),
        price: parseFloat(formData.get('price')),
        status: statusMap[formData.get('status')] || 0,
        image: null
    };

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
    // có thể redirect sang page edit với id
    window.location.href = `admin_expert_edit.php?id=${id}`;
}

// Load khi trang được mở
loadExperts();
</script>
</body>
</html>
