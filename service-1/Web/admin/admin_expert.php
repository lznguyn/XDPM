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

// ✅ Xử lý phân công expert
if (isset($_POST['assign_expert'])) {
    $requestId = intval($_POST['request_id']);
    $expertId = intval($_POST['expert_id']);
    
    $assignRes = callApi("$apiBase/service-requests/$requestId/assign", "PATCH", [
        "specialistId" => $expertId
    ], $token);
    
    if ($assignRes['code'] == 200) {
        $_SESSION['toast_message'] = "✅ Đã phân công expert thành công!";
    } else {
        $_SESSION['toast_message'] = "❌ Lỗi phân công: " . ($assignRes['body']['message'] ?? 'Unknown error');
    }
    
    header('location:admin_expert.php');
    exit();
}

// ✅ Lấy danh sách users từ API
$res = callApi("$apiBase/users", "GET", null, $token);
$allUsers = $res['body'] ?? [];
$apiError = null;

// Debug: Log error nếu có
if ($res['code'] != 200) {
    $apiError = "API Error: HTTP " . $res['code'];
    if (isset($res['body']['message'])) {
        $apiError .= " - " . $res['body']['message'];
    } elseif (isset($res['body'])) {
        $apiError .= " - " . json_encode($res['body']);
    }
    error_log($apiError);
    
    // Nếu lỗi, thử gọi lại qua gateway
    if ($res['code'] == 404 || $res['code'] == 500) {
        $directRes = callApi("http://localhost:8000/api/Admin/users", "GET", null, $token);
        if ($directRes['code'] == 200) {
            $allUsers = $directRes['body'] ?? [];
            $apiError = null; // Clear error if direct call succeeded
        } else {
            $apiError .= " | Direct call also failed: HTTP " . $directRes['code'];
        }
    }
}

// ✅ Lấy danh sách service requests cần phân công
$requestsRes = callApi("$apiBase/service-requests", "GET", null, $token);
$allRequests = $requestsRes['body'] ?? [];
$requestsError = null;
$unassignedRequests = [];

if ($requestsRes['code'] != 200) {
    $requestsError = "API Error: HTTP " . $requestsRes['code'];
    if (isset($requestsRes['body']['message'])) {
        $requestsError .= " - " . $requestsRes['body']['message'];
    }
} else {
    // Hàm normalize status
    function normalizeStatusForExpert($status) {
        if (empty($status)) return '';
        $statusStr = (string)$status;
        if (is_numeric($status)) {
            $statusMap = [0 => 'Pending', 1 => 'Submitted', 2 => 'Assigned', 3 => 'InProgress', 4 => 'PendingReview', 5 => 'Completed', 6 => 'RevisionRequested', 7 => 'Cancelled'];
            $statusStr = $statusMap[(int)$status] ?? $statusStr;
        }
        // Normalize case
        $statusStr = trim($statusStr);
        $statusMap = [
            'pending' => 'Pending',
            'submitted' => 'Submitted',
            'assigned' => 'Assigned',
            'inprogress' => 'InProgress',
            'in_progress' => 'InProgress',
            'pendingreview' => 'PendingReview',
            'pending_review' => 'PendingReview',
            'completed' => 'Completed',
            'revisionrequested' => 'RevisionRequested',
            'revision_requested' => 'RevisionRequested',
            'cancelled' => 'Cancelled'
        ];
        $lowerStatus = strtolower($statusStr);
        return $statusMap[$lowerStatus] ?? ucfirst($statusStr);
    }
    
    // Lọc các requests ở trạng thái InProgress hoặc PendingReview (cần phân công hoặc sắp xếp lịch)
    $pendingReviewRequests = array_filter($allRequests, function($r) {
        $status = normalizeStatusForExpert($r['status'] ?? '');
        // Hiển thị cả InProgress và PendingReview
        return $status === 'InProgress' || $status === 'PendingReview';
    });
    // Reset array keys
    $pendingReviewRequests = array_values($pendingReviewRequests);
}

// Lọc chỉ các expert roles
$expertRoles = ['Coordinator', 'Arrangement', 'Transcription', 'Recorder', 'Studio'];
$experts = array_filter($allUsers, function($u) use ($expertRoles) {
    return in_array($u['role'] ?? '', $expertRoles);
});

// Phân loại experts theo role
$expertsByRole = [
    'all' => $experts,
    'Coordinator' => array_filter($experts, fn($e) => ($e['role'] ?? '') === 'Coordinator'),
    'Arrangement' => array_filter($experts, fn($e) => ($e['role'] ?? '') === 'Arrangement'),
    'Transcription' => array_filter($experts, fn($e) => ($e['role'] ?? '') === 'Transcription'),
    'Recorder' => array_filter($experts, fn($e) => ($e['role'] ?? '') === 'Recorder'),
    'Studio' => array_filter($experts, fn($e) => ($e['role'] ?? '') === 'Studio')
];

// Lấy tab hiện tại từ URL
$currentTab = $_GET['tab'] ?? 'all';
$filteredExperts = $expertsByRole[$currentTab] ?? $experts;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Experts - MuTraPro Admin</title>
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
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-6 py-6 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <div class="bg-purple-500 bg-opacity-10 p-3 rounded-xl">
                    <i class="fas fa-user-graduate text-purple-600 text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Quản lý Experts</h1>
                    <p class="text-gray-600 mt-1">Xem và quản lý các chuyên gia trong hệ thống</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="max-w-7xl mx-auto px-4 pt-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Tổng Experts</p>
                        <p class="text-2xl font-bold text-gray-900"><?= count($expertsByRole['all']) ?></p>
                    </div>
                    <i class="fas fa-users text-purple-500 text-3xl"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Coordinator</p>
                        <p class="text-2xl font-bold text-gray-900"><?= count($expertsByRole['Coordinator']) ?></p>
                    </div>
                    <i class="fas fa-user-tie text-green-500 text-3xl"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Arrangement</p>
                        <p class="text-2xl font-bold text-gray-900"><?= count($expertsByRole['Arrangement']) ?></p>
                    </div>
                    <i class="fas fa-music text-blue-500 text-3xl"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Transcription</p>
                        <p class="text-2xl font-bold text-gray-900"><?= count($expertsByRole['Transcription']) ?></p>
                    </div>
                    <i class="fas fa-microphone text-orange-500 text-3xl"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Recorder</p>
                        <p class="text-2xl font-bold text-gray-900"><?= count($expertsByRole['Recorder']) ?></p>
                    </div>
                    <i class="fas fa-headphones text-yellow-500 text-3xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="max-w-7xl mx-auto px-4 pt-2">
        <div class="bg-white rounded-xl shadow-sm p-2 flex flex-wrap gap-2 overflow-x-auto">
            <a href="?tab=all" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'all' ? 'bg-purple-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                <i class="fas fa-list mr-2"></i>Tất cả (<?= count($expertsByRole['all']) ?>)
            </a>
            <a href="?tab=Coordinator" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'Coordinator' ? 'bg-green-500 text-white' : 'bg-green-50 text-green-700 hover:bg-green-100' ?>">
                <i class="fas fa-user-tie mr-2"></i>Coordinator (<?= count($expertsByRole['Coordinator']) ?>)
            </a>
            <a href="?tab=Arrangement" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'Arrangement' ? 'bg-blue-500 text-white' : 'bg-blue-50 text-blue-700 hover:bg-blue-100' ?>">
                <i class="fas fa-music mr-2"></i>Arrangement (<?= count($expertsByRole['Arrangement']) ?>)
            </a>
            <a href="?tab=Transcription" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'Transcription' ? 'bg-orange-500 text-white' : 'bg-orange-50 text-orange-700 hover:bg-orange-100' ?>">
                <i class="fas fa-microphone mr-2"></i>Transcription (<?= count($expertsByRole['Transcription']) ?>)
            </a>
            <a href="?tab=Recorder" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'Recorder' ? 'bg-yellow-500 text-white' : 'bg-yellow-50 text-yellow-700 hover:bg-yellow-100' ?>">
                <i class="fas fa-headphones mr-2"></i>Recorder (<?= count($expertsByRole['Recorder']) ?>)
            </a>
            <a href="?tab=Studio" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'Studio' ? 'bg-indigo-500 text-white' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100' ?>">
                <i class="fas fa-building mr-2"></i>Studio (<?= count($expertsByRole['Studio']) ?>)
            </a>
        </div>
    </div>

    <!-- Phân công Experts cho Service Requests -->
    <div class="max-w-7xl mx-auto px-4 pt-6">
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-tasks text-purple-600 mr-3"></i>
                Phân công Nhạc sĩ cho Yêu cầu Dịch vụ
            </h2>
            
            <?php if ($requestsError): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
                    <p class="text-red-600 text-sm"><?= htmlspecialchars($requestsError) ?></p>
                </div>
            <?php elseif (empty($allRequests)): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 text-center">
                    <i class="fas fa-exclamation-triangle text-yellow-500 text-4xl mb-3"></i>
                    <p class="text-yellow-700 font-medium">Chưa có yêu cầu dịch vụ nào trong hệ thống.</p>
                </div>
            <?php elseif (empty($pendingReviewRequests)): ?>
                <div class="bg-green-50 border border-green-200 rounded-xl p-6 text-center">
                    <i class="fas fa-check-circle text-green-500 text-4xl mb-3"></i>
                    <p class="text-green-700 font-medium">Không có yêu cầu nào đang ở trạng thái "Đang xử lý" hoặc "Chờ xem xét"!</p>
                    <p class="text-green-600 text-sm mt-2">Tổng số yêu cầu: <?= count($allRequests) ?></p>
                </div>
            <?php else: ?>
                <!-- Debug info -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 mb-4 text-sm">
                    <p class="text-blue-700">
                        <i class="fas fa-info-circle mr-2"></i>
                        Tổng số yêu cầu: <strong><?= count($allRequests) ?></strong> | 
                        Cần phân công/Sắp xếp: <strong><?= count($pendingReviewRequests) ?></strong>
                        <span class="text-xs text-gray-500 ml-2">(InProgress + PendingReview)</span>
                    </p>
                </div>
                <div class="space-y-4">
                    <?php foreach ($pendingReviewRequests as $req): 
                        $serviceType = $req['serviceType'] ?? '';
                        // Lọc experts phù hợp với service type
                        $suitableExperts = [];
                        foreach ($experts as $expert) {
                            $expertRole = $expert['role'] ?? '';
                            // Logic phân công: Transcription -> Transcription, Arrangement -> Arrangement, Recording -> Recorder
                            $match = false;
                            if ($serviceType === 'Transcription' && $expertRole === 'Transcription') $match = true;
                            elseif ($serviceType === 'Arrangement' && $expertRole === 'Arrangement') $match = true;
                            elseif ($serviceType === 'Recording' && $expertRole === 'Recorder') $match = true;
                            // Coordinator và Studio có thể nhận tất cả
                            elseif ($expertRole === 'Coordinator' || $expertRole === 'Studio') $match = true;
                            
                            if ($match) {
                                $suitableExperts[] = $expert;
                            }
                        }
                    ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-900 mb-1">
                                    <?= htmlspecialchars($req['title'] ?? 'N/A') ?>
                                </h3>
                                <div class="text-sm text-gray-600 space-y-1">
                                    <p><i class="fas fa-user mr-2"></i><strong>Khách hàng:</strong> <?= htmlspecialchars($req['customerName'] ?? 'N/A') ?></p>
                                    <p><i class="fas fa-tag mr-2"></i><strong>Loại dịch vụ:</strong> 
                                        <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-medium">
                                            <?= htmlspecialchars($serviceType) ?>
                                        </span>
                                    </p>
                                    <p><i class="fas fa-calendar mr-2"></i><strong>Ngày tạo:</strong> <?= !empty($req['createdDate']) ? date('d/m/Y H:i', strtotime($req['createdDate'])) : 'N/A' ?></p>
                                    <?php if (!empty($req['description'])): ?>
                                    <p class="text-gray-500 mt-2"><i class="fas fa-file-alt mr-2"></i><?= htmlspecialchars(substr($req['description'], 0, 150)) ?><?= strlen($req['description']) > 150 ? '...' : '' ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium">
                                <?= htmlspecialchars($req['status'] ?? 'Submitted') ?>
                            </span>
                        </div>
                        
                        <!-- Hiển thị nghệ sĩ khách hàng mong muốn nếu có -->
                        <?php if (!empty($req['preferredSpecialistId'])): ?>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3">
                            <p class="text-sm text-blue-700">
                                <i class="fas fa-heart mr-2"></i>
                                <strong>Nghệ sĩ khách hàng mong muốn:</strong> 
                                <?php 
                                $preferredId = $req['preferredSpecialistId'];
                                $preferredExpert = array_filter($experts, fn($e) => $e['id'] == $preferredId);
                                if (!empty($preferredExpert)) {
                                    $pref = reset($preferredExpert);
                                    echo htmlspecialchars($pref['name'] . ' (' . $pref['role'] . ')');
                                } else {
                                    echo 'ID: ' . $preferredId;
                                }
                                ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" class="space-y-3 mt-4">
                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-user-tie mr-2"></i>Chọn Nhạc sĩ:
                                    </label>
                                    <select name="expert_id" id="expert_<?= $req['id'] ?>" required 
                                            onchange="loadSpecialistSchedule(<?= $req['id'] ?>, this.value)"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                        <option value="">-- Chọn nhạc sĩ --</option>
                                        <?php if (empty($suitableExperts)): ?>
                                            <?php foreach ($experts as $expert): ?>
                                            <option value="<?= $expert['id'] ?>" <?= !empty($req['preferredSpecialistId']) && $req['preferredSpecialistId'] == $expert['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($expert['name']) ?> (<?= htmlspecialchars($expert['role']) ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <?php foreach ($suitableExperts as $expert): ?>
                                            <option value="<?= $expert['id'] ?>" <?= !empty($req['preferredSpecialistId']) && $req['preferredSpecialistId'] == $expert['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($expert['name']) ?> (<?= htmlspecialchars($expert['role']) ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-calendar mr-2"></i>Ngày gặp mặt:
                                    </label>
                                    <input type="date" name="scheduled_date" required 
                                           min="<?= date('Y-m-d') ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-clock mr-2"></i>Ca làm việc:
                                    </label>
                                    <select name="time_slot" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                        <option value="">-- Chọn ca --</option>
                                        <option value="0-4">Ca 1: 0h - 4h</option>
                                        <option value="6-10">Ca 2: 6h - 10h</option>
                                        <option value="12-16">Ca 3: 12h - 16h</option>
                                        <option value="18-22">Ca 4: 18h - 22h</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-sticky-note mr-2"></i>Ghi chú:
                                    </label>
                                    <input type="text" name="meeting_notes" 
                                           placeholder="Ghi chú cuộc gặp mặt..."
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                </div>
                            </div>
                            <div id="schedule_info_<?= $req['id'] ?>" class="hidden bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-700">
                                <i class="fas fa-info-circle mr-2"></i>
                                <span id="schedule_text_<?= $req['id'] ?>"></span>
                            </div>
                            <button type="submit" name="schedule_request" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg font-medium transition flex items-center justify-center gap-2">
                                <i class="fas fa-calendar-check"></i>
                                Sắp xếp lịch gặp mặt
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Danh sách Experts -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <?php if ($apiError): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-6 mb-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl mr-4"></i>
                    <div>
                        <h3 class="text-red-800 font-semibold mb-1">Lỗi khi lấy dữ liệu experts</h3>
                        <p class="text-red-600 text-sm"><?= htmlspecialchars($apiError) ?></p>
                        <p class="text-red-500 text-xs mt-2">Vui lòng kiểm tra lại kết nối API hoặc liên hệ quản trị viên.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (empty($filteredExperts)): ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <i class="fas fa-user-graduate text-gray-300 text-6xl mb-4"></i>
                <p class="text-gray-500 text-lg">
                    <?php if ($currentTab === 'all'): ?>
                        <?php if ($apiError): ?>
                            Không thể tải danh sách experts. Vui lòng thử lại sau.
                        <?php else: ?>
                            Chưa có expert nào trong hệ thống.
                        <?php endif; ?>
                    <?php else: ?>
                        Không có expert nào với role "<?= htmlspecialchars($currentTab) ?>".
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tên</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($filteredExperts as $expert): 
                                $role = $expert['role'] ?? '';
                                $roleColors = [
                                    'Coordinator' => 'bg-green-100 text-green-700',
                                    'Arrangement' => 'bg-blue-100 text-blue-700',
                                    'Transcription' => 'bg-orange-100 text-orange-700',
                                    'Recorder' => 'bg-yellow-100 text-yellow-700',
                                    'Studio' => 'bg-indigo-100 text-indigo-700'
                                ];
                                $roleColor = $roleColors[$role] ?? 'bg-gray-100 text-gray-700';
                                
                                $roleIcons = [
                                    'Coordinator' => 'fa-user-tie',
                                    'Arrangement' => 'fa-music',
                                    'Transcription' => 'fa-microphone',
                                    'Recorder' => 'fa-headphones',
                                    'Studio' => 'fa-building'
                                ];
                                $roleIcon = $roleIcons[$role] ?? 'fa-user';
                            ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    #<?= htmlspecialchars($expert['id']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center text-white font-semibold">
                                                <?= strtoupper(substr($expert['name'] ?? 'E', 0, 1)) ?>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($expert['name']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <i class="fas fa-envelope mr-2 text-gray-400"></i>
                                    <?= htmlspecialchars($expert['email']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full <?= $roleColor ?>">
                                        <i class="fas <?= $roleIcon ?> mr-1"></i>
                                        <?= htmlspecialchars($role) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex gap-2">
                                        <button class="text-indigo-600 hover:text-indigo-900" title="Xem chi tiết">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="text-blue-600 hover:text-blue-900" title="Chỉnh sửa">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="text-red-600 hover:text-red-900" title="Xóa">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Toast thông báo -->
<script>
function showToast(message, type = "success") {
    const toast = document.createElement("div");
    toast.textContent = message;
    toast.className = `fixed bottom-6 right-6 px-4 py-3 rounded-lg text-white shadow-lg z-50 ${type === "success" ? "bg-green-600" : "bg-red-600"}`;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.classList.add("opacity-0", "transition");
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}

// Load lịch của chuyên gia khi chọn
async function loadSpecialistSchedule(requestId, specialistId) {
    if (!specialistId) {
        const infoDiv = document.getElementById(`schedule_info_${requestId}`);
        if (infoDiv) infoDiv.classList.add('hidden');
        return;
    }
    
    const infoDiv = document.getElementById(`schedule_info_${requestId}`);
    const textSpan = document.getElementById(`schedule_text_${requestId}`);
    if (!infoDiv || !textSpan) return;
    
    try {
        const today = new Date().toISOString().split('T')[0];
        const nextMonth = new Date();
        nextMonth.setMonth(nextMonth.getMonth() + 1);
        const endDate = nextMonth.toISOString().split('T')[0];
        
        const response = await fetch(`http://localhost:8000/api/Admin/specialists/${specialistId}/schedule?startDate=${today}&endDate=${endDate}`, {
            headers: {
                'Authorization': 'Bearer <?= $token ?>'
            }
        });
        
        if (response.ok) {
            const schedules = await response.json();
            const bookedDates = schedules.filter(s => {
                const slots = s.timeSlots;
                return slots.slot1 && slots.slot2 && slots.slot3 && slots.slot4;
            }).map(s => s.date);
            
            if (bookedDates.length > 0) {
                textSpan.textContent = `Cảnh báo: Các ngày sau đã kín lịch: ${bookedDates.slice(0, 5).join(', ')}${bookedDates.length > 5 ? '...' : ''}`;
                infoDiv.classList.remove('hidden');
            } else {
                infoDiv.classList.add('hidden');
            }
        }
    } catch (error) {
        console.error('Error loading schedule:', error);
    }
}
</script>

<?php if (isset($_SESSION['toast_message'])): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    showToast("<?= addslashes($_SESSION['toast_message']) ?>");
});
</script>
<?php unset($_SESSION['toast_message']); endif; ?>

</body>
</html>

