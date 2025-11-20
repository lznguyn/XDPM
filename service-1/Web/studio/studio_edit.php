<?php
session_start();
// Kiểm tra đăng nhập - chỉ cho phép Studio role
$studio_id = $_SESSION['user']['id'] ?? null;
$studio_role = $_SESSION['user']['role'] ?? '';

if (!$studio_id || strtolower($studio_role) !== 'studio') {
    header('location:../login.php?error=studio_only');
    exit();
}

$edit_id = $_GET['id'] ?? null;
if (!$edit_id) {
    header('location:studio_page.php');
    exit();
}

$message = [];
$api_url = "http://localhost:8000/studios"; // API endpoint qua gateway

// Load studio data
$studio_data = null;
if ($edit_id) {
    $ch = curl_init("$api_url/$edit_id");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $result = json_decode($response, true);
        if ($result['status'] === 'success' && isset($result['data'])) {
            $studio_data = $result['data'];
        }
    }
}

if (!$studio_data) {
    header('location:studio_page.php?error=not_found');
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa Studio - MuTraPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
<?php include 'studio_header.php'; ?>

<div class="min-h-screen pt-20 max-w-4xl mx-auto px-4 py-8">
    <div class="bg-white rounded-2xl shadow-sm border p-6 mb-8">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-900">Sửa Studio</h2>
            <a href="studio_page.php" class="text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left mr-2"></i>Quay lại
            </a>
        </div>
        
        <form id="editStudioForm" class="space-y-4">
            <input type="hidden" name="id" value="<?= htmlspecialchars($studio_data['id']) ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tên Studio *</label>
                    <input type="text" name="name" placeholder="Tên" required
                           value="<?= htmlspecialchars($studio_data['name'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Địa điểm *</label>
                    <input type="text" name="location" placeholder="Địa điểm" required
                           value="<?= htmlspecialchars($studio_data['location'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Giá (VNĐ) *</label>
                    <input type="number" name="price" id="editPriceInput" placeholder="Giá (VNĐ)" required min="0" step="0.01"
                           value="<?= htmlspecialchars($studio_data['price'] ?? 0) ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Trạng thái *</label>
                    <select name="status" class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                        <?php
                        $statuses = [
                            0 => 'Còn chỗ',
                            1 => 'Đã có người đặt',
                            2 => 'Chưa đặt'
                        ];
                        $current_status = $studio_data['status'] ?? 0;
                        foreach ($statuses as $value => $label) {
                            $selected = $current_status == $value ? 'selected' : '';
                            echo "<option value=\"$value\" $selected>$label</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Hình ảnh (URL)</label>
                    <input type="text" name="image" placeholder="URL hình ảnh"
                           value="<?= htmlspecialchars($studio_data['image'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                    <?php if (!empty($studio_data['image'])): ?>
                    <div class="mt-2">
                        <img src="<?= htmlspecialchars($studio_data['image']) ?>" 
                             alt="Studio image" 
                             class="w-32 h-32 object-cover rounded-lg">
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="flex gap-4 mt-6">
                <button type="submit" class="bg-blue-500 text-white px-8 py-3 rounded-xl hover:bg-blue-700">
                    <i class="fas fa-save mr-2"></i>Lưu thay đổi
                </button>
                <a href="studio_page.php" class="bg-gray-300 text-gray-700 px-8 py-3 rounded-xl hover:bg-gray-400">
                    <i class="fas fa-times mr-2"></i>Hủy
                </a>
            </div>
        </form>
        
        <div id="editMessage" class="mt-4"></div>
    </div>
</div>

<script>
const apiUrl = '<?php echo $api_url; ?>';
const studioId = <?php echo $studio_data['id']; ?>;

// ===== Sửa Studio =====
document.getElementById('editStudioForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    // Lấy giá trị price từ input trực tiếp để đảm bảo lấy được giá trị
    const priceInput = document.getElementById('editPriceInput');
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
        status: parseInt(formData.get('status')),
        image: formData.get('image') || null
    };

    console.log('Updating studio data:', obj); // Debug

    const messageDiv = document.getElementById('editMessage');
    messageDiv.innerHTML = '<div class="text-blue-600"><i class="fas fa-spinner fa-spin mr-2"></i>Đang cập nhật...</div>';

    try {
        const res = await fetch(`${apiUrl}/${studioId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(obj)
        });

        const data = await res.json();
        
        if (res.ok && data.status === 'success') {
            messageDiv.innerHTML = `<div class="text-green-600"><i class="fas fa-check-circle mr-2"></i>${data.message || 'Cập nhật thành công!'}</div>`;
            setTimeout(() => {
                window.location.href = 'studio_page.php';
            }, 1500);
        } else {
            messageDiv.innerHTML = `<div class="text-red-600"><i class="fas fa-exclamation-circle mr-2"></i>${data.message || data.detail || 'Lỗi cập nhật!'}</div>`;
        }
    } catch (error) {
        messageDiv.innerHTML = `<div class="text-red-600"><i class="fas fa-exclamation-circle mr-2"></i>Lỗi: ${error.message}</div>`;
    }
});
</script>
</body>
</html>

