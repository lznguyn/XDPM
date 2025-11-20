<?php
// Cấu hình timezone UTC+7 (Vietnam Time)
date_default_timezone_set('Asia/Ho_Chi_Minh');

session_start();

// Kiểm tra đăng nhập Admin
$admin_id = $_SESSION['user']['id'] ?? null;
if (!$admin_id) {
    header('location:../login.php');
    exit();
}

// API base URL - Gọi qua Kong Gateway
$apiBase = "http://localhost:8000/api/Admin";
$token = $_SESSION['token'] ?? '';

// ✅ Hàm gọi API với JWT token
function callApi($url, $method = 'GET', $data = null, $token = '')
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

// ✅ Xử lý cập nhật giá dịch vụ
if (isset($_POST['update_price'])) {
    $serviceType = $_POST['service_type'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    
    if ($serviceType && $price >= 0) {
        $res = callApi("$apiBase/service-prices/$serviceType", "PUT", [
            'price' => $price,
            'updatedBy' => $admin_id
        ], $token);
        
        if ($res['code'] == 200) {
            $_SESSION['toast_message'] = "✅ Đã cập nhật giá dịch vụ thành công!";
        } else {
            $_SESSION['toast_message'] = "❌ Lỗi cập nhật giá: " . ($res['body']['message'] ?? 'Unknown error');
        }
        header('location:admin_service.php?tab=' . urlencode($_GET['tab'] ?? 'all'));
        exit();
    }
}

// ✅ Lấy danh sách giá dịch vụ
$priceRes = callApi("$apiBase/service-prices", "GET", null, $token);
$servicePrices = [];
if ($priceRes['code'] == 200 && is_array($priceRes['body'])) {
    foreach ($priceRes['body'] as $price) {
        $servicePrices[$price['serviceType'] ?? ''] = $price['price'] ?? 50000;
    }
}
// Giá mặc định nếu chưa có
$servicePrices['Transcription'] = $servicePrices['Transcription'] ?? 50000;
$servicePrices['Arrangement'] = $servicePrices['Arrangement'] ?? 50000;
$servicePrices['Recording'] = $servicePrices['Recording'] ?? 50000;

// ✅ Lấy danh sách service requests từ API
$res = callApi("$apiBase/service-requests", "GET", null, $token);
$allRequests = $res['body'] ?? [];

// Phân loại requests theo loại dịch vụ
$requestsByServiceType = [
    'all' => $allRequests,
    'Transcription' => array_filter($allRequests, fn($r) => ($r['serviceType'] ?? '') === 'Transcription'),
    'Arrangement' => array_filter($allRequests, fn($r) => ($r['serviceType'] ?? '') === 'Arrangement'),
    'Recording' => array_filter($allRequests, fn($r) => ($r['serviceType'] ?? '') === 'Recording')
];

// Lấy tab hiện tại từ URL
$currentTab = $_GET['tab'] ?? 'all';
$filteredRequests = $requestsByServiceType[$currentTab] ?? $allRequests;

// Thống kê
$stats = [
    'all' => count($allRequests),
    'Transcription' => count($requestsByServiceType['Transcription']),
    'Arrangement' => count($requestsByServiceType['Arrangement']),
    'Recording' => count($requestsByServiceType['Recording'])
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Dịch vụ - MuTraPro Admin</title>
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
<body class="bg-gray-50">
<?php include 'admin_header.php'; ?>

<div class="min-h-screen pt-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                <i class="fas fa-cogs text-primary mr-2"></i>
                Quản lý Dịch vụ
            </h1>
            <p class="text-gray-600">Quản lý các yêu cầu dịch vụ theo loại và điều chỉnh giá</p>
        </div>

        <!-- Service Prices Section -->
        <div class="bg-white rounded-lg shadow mb-6 p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-dollar-sign text-green-600 mr-2"></i>
                Quản lý Giá Dịch vụ
            </h2>
            <p class="text-gray-600 text-sm mb-4">Điều chỉnh giá cho các dịch vụ</p>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Transcription Price -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="service_type" value="Transcription">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-music text-green-600 mr-1"></i>
                                Phiên Âm (Transcription)
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="number" 
                                       name="price" 
                                       value="<?= number_format($servicePrices['Transcription'], 0, '', '') ?>"
                                       min="0" 
                                       step="1000"
                                       required
                                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <span class="text-gray-600 whitespace-nowrap">VNĐ</span>
                                <button type="submit" 
                                        name="update_price"
                                        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                                    <i class="fas fa-save mr-1"></i>Lưu
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Giá hiện tại: <?= number_format($servicePrices['Transcription'], 0, ',', '.') ?> VNĐ
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Arrangement Price -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="service_type" value="Arrangement">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-sliders-h text-purple-600 mr-1"></i>
                                Hòa Âm (Arrangement)
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="number" 
                                       name="price" 
                                       value="<?= number_format($servicePrices['Arrangement'], 0, '', '') ?>"
                                       min="0" 
                                       step="1000"
                                       required
                                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <span class="text-gray-600 whitespace-nowrap">VNĐ</span>
                                <button type="submit" 
                                        name="update_price"
                                        class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                                    <i class="fas fa-save mr-1"></i>Lưu
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Giá hiện tại: <?= number_format($servicePrices['Arrangement'], 0, ',', '.') ?> VNĐ
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Recording Price -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="service_type" value="Recording">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-microphone text-orange-600 mr-1"></i>
                                Thu Âm (Recording)
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="number" 
                                       name="price" 
                                       value="<?= number_format($servicePrices['Recording'], 0, '', '') ?>"
                                       min="0" 
                                       step="1000"
                                       required
                                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <span class="text-gray-600 whitespace-nowrap">VNĐ</span>
                                <button type="submit" 
                                        name="update_price"
                                        class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition">
                                    <i class="fas fa-save mr-1"></i>Lưu
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Giá hiện tại: <?= number_format($servicePrices['Recording'], 0, ',', '.') ?> VNĐ
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Tất cả</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['all'] ?></p>
                    </div>
                    <i class="fas fa-list text-blue-500 text-3xl"></i>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Phiên âm</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['Transcription'] ?></p>
                    </div>
                    <i class="fas fa-music text-green-500 text-3xl"></i>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Hòa âm</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['Arrangement'] ?></p>
                    </div>
                    <i class="fas fa-sliders-h text-purple-500 text-3xl"></i>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Thu âm</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['Recording'] ?></p>
                    </div>
                    <i class="fas fa-microphone text-orange-500 text-3xl"></i>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <a href="?tab=all" 
                       class="px-6 py-3 text-sm font-medium <?= $currentTab === 'all' ? 'border-b-2 border-primary text-primary' : 'text-gray-600 hover:text-primary' ?>">
                        <i class="fas fa-list mr-2"></i>Tất cả (<?= $stats['all'] ?>)
                    </a>
                    <a href="?tab=Transcription" 
                       class="px-6 py-3 text-sm font-medium <?= $currentTab === 'Transcription' ? 'border-b-2 border-green-500 text-green-600' : 'text-gray-600 hover:text-green-600' ?>">
                        <i class="fas fa-music mr-2"></i>Phiên âm (<?= $stats['Transcription'] ?>)
                    </a>
                    <a href="?tab=Arrangement" 
                       class="px-6 py-3 text-sm font-medium <?= $currentTab === 'Arrangement' ? 'border-b-2 border-purple-500 text-purple-600' : 'text-gray-600 hover:text-purple-600' ?>">
                        <i class="fas fa-sliders-h mr-2"></i>Hòa âm (<?= $stats['Arrangement'] ?>)
                    </a>
                    <a href="?tab=Recording" 
                       class="px-6 py-3 text-sm font-medium <?= $currentTab === 'Recording' ? 'border-b-2 border-orange-500 text-orange-600' : 'text-gray-600 hover:text-orange-600' ?>">
                        <i class="fas fa-microphone mr-2"></i>Thu âm (<?= $stats['Recording'] ?>)
                    </a>
                </nav>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <?php if (empty($filteredRequests)): ?>
                    <div class="p-8 text-center">
                        <i class="fas fa-inbox text-gray-400 text-5xl mb-4"></i>
                        <p class="text-gray-600 text-lg">Không có dịch vụ nào</p>
                        <p class="text-gray-500 text-sm mt-2">Chưa có yêu cầu dịch vụ nào cho loại này</p>
                    </div>
                <?php else: ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tiêu đề</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Loại dịch vụ</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Khách hàng</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày tạo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($filteredRequests as $req): 
                                $serviceType = $req['serviceType'] ?? 'N/A';
                                $status = $req['status'] ?? 'Pending';
                                $customerId = $req['customer_id'] ?? '';
                                
                                // Badge màu theo loại dịch vụ
                                $typeColors = [
                                    'Transcription' => 'bg-green-100 text-green-800',
                                    'Arrangement' => 'bg-purple-100 text-purple-800',
                                    'Recording' => 'bg-orange-100 text-orange-800'
                                ];
                                $typeColor = $typeColors[$serviceType] ?? 'bg-gray-100 text-gray-800';
                                
                                // Badge màu theo trạng thái
                                $statusColors = [
                                    'Pending' => 'bg-yellow-100 text-yellow-800',
                                    'Submitted' => 'bg-blue-100 text-blue-800',
                                    'Assigned' => 'bg-indigo-100 text-indigo-800',
                                    'InProgress' => 'bg-purple-100 text-purple-800',
                                    'PendingReview' => 'bg-pink-100 text-pink-800',
                                    'Completed' => 'bg-green-100 text-green-800',
                                    'RevisionRequested' => 'bg-orange-100 text-orange-800',
                                    'Cancelled' => 'bg-red-100 text-red-800'
                                ];
                                $statusColor = $statusColors[$status] ?? 'bg-gray-100 text-gray-800';
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        #<?= htmlspecialchars($req['id'] ?? 'N/A') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($req['title'] ?? 'Không có tiêu đề') ?>
                                        </div>
                                        <div class="text-sm text-gray-500 truncate max-w-xs">
                                            <?= htmlspecialchars(substr($req['description'] ?? '', 0, 50)) ?>...
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $typeColor ?>">
                                            <?= htmlspecialchars($serviceType) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        Customer #<?= htmlspecialchars($customerId) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusColor ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= isset($req['created_date']) ? date('d/m/Y', strtotime($req['created_date'])) : 'N/A' ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="admin_booking.php?id=<?= $req['id'] ?>" 
                                           class="text-primary hover:text-blue-800 mr-3">
                                            <i class="fas fa-eye mr-1"></i>Xem
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Toast Message -->
<?php if (isset($_SESSION['toast_message'])): ?>
    <div id="toast" class="fixed top-5 right-5 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-fade-in-down">
        <?= $_SESSION['toast_message'] ?>
    </div>
    <script>
        setTimeout(() => {
            document.getElementById('toast').remove();
        }, 3000);
    </script>
    <?php unset($_SESSION['toast_message']); ?>
<?php endif; ?>

<style>
@keyframes fade-in-down {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-down { animation: fade-in-down 0.3s ease-in-out; }
</style>

</body>
</html>

