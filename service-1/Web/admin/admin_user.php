<?php
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

// ✅ Lấy danh sách users từ API
$res = callApi("$apiBase/users", "GET", null, $token);
$users = $res['body'] ?? [];
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
            $users = $directRes['body'] ?? [];
            $apiError = null; // Clear error if direct call succeeded
        } else {
            $apiError .= " | Direct call also failed: HTTP " . $directRes['code'];
        }
    }
}

// Phân loại users theo role
$usersByRole = [
    'all' => $users,
    'Admin' => array_filter($users, fn($u) => ($u['role'] ?? '') === 'Admin'),
    'User' => array_filter($users, fn($u) => ($u['role'] ?? '') === 'User'),
    'Coordinator' => array_filter($users, fn($u) => ($u['role'] ?? '') === 'Coordinator'),
    'Arrangement' => array_filter($users, fn($u) => ($u['role'] ?? '') === 'Arrangement'),
    'Transcription' => array_filter($users, fn($u) => ($u['role'] ?? '') === 'Transcription'),
    'Recorder' => array_filter($users, fn($u) => ($u['role'] ?? '') === 'Recorder'),
    'Studio' => array_filter($users, fn($u) => ($u['role'] ?? '') === 'Studio')
];

// Lấy tab hiện tại từ URL
$currentTab = $_GET['tab'] ?? 'all';
$filteredUsers = $usersByRole[$currentTab] ?? $users;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Users - MuTraPro Admin</title>
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
                <div class="bg-primary bg-opacity-10 p-3 rounded-xl">
                    <i class="fas fa-users text-primary text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Quản lý Users</h1>
                    <p class="text-gray-600 mt-1">Xem và quản lý tất cả người dùng trong hệ thống</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="max-w-7xl mx-auto px-4 pt-6">
        <div class="bg-white rounded-xl shadow-sm p-2 flex flex-wrap gap-2 overflow-x-auto">
            <a href="?tab=all" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'all' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                <i class="fas fa-list mr-2"></i>Tất cả (<?= count($usersByRole['all']) ?>)
            </a>
            <a href="?tab=Admin" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'Admin' ? 'bg-red-500 text-white' : 'bg-red-50 text-red-700 hover:bg-red-100' ?>">
                <i class="fas fa-user-shield mr-2"></i>Admin (<?= count($usersByRole['Admin']) ?>)
            </a>
            <a href="?tab=User" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'User' ? 'bg-blue-500 text-white' : 'bg-blue-50 text-blue-700 hover:bg-blue-100' ?>">
                <i class="fas fa-user mr-2"></i>User (<?= count($usersByRole['User']) ?>)
            </a>
            <a href="?tab=Coordinator" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'Coordinator' ? 'bg-green-500 text-white' : 'bg-green-50 text-green-700 hover:bg-green-100' ?>">
                <i class="fas fa-user-tie mr-2"></i>Coordinator (<?= count($usersByRole['Coordinator']) ?>)
            </a>
            <a href="?tab=Arrangement" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'Arrangement' ? 'bg-purple-500 text-white' : 'bg-purple-50 text-purple-700 hover:bg-purple-100' ?>">
                <i class="fas fa-music mr-2"></i>Arrangement (<?= count($usersByRole['Arrangement']) ?>)
            </a>
            <a href="?tab=Transcription" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'Transcription' ? 'bg-orange-500 text-white' : 'bg-orange-50 text-orange-700 hover:bg-orange-100' ?>">
                <i class="fas fa-microphone mr-2"></i>Transcription (<?= count($usersByRole['Transcription']) ?>)
            </a>
            <a href="?tab=Recorder" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'Recorder' ? 'bg-yellow-500 text-white' : 'bg-yellow-50 text-yellow-700 hover:bg-yellow-100' ?>">
                <i class="fas fa-headphones mr-2"></i>Recorder (<?= count($usersByRole['Recorder']) ?>)
            </a>
            <a href="?tab=Studio" 
               class="px-4 py-2 rounded-lg font-medium transition whitespace-nowrap <?= $currentTab === 'Studio' ? 'bg-indigo-500 text-white' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100' ?>">
                <i class="fas fa-building mr-2"></i>Studio (<?= count($usersByRole['Studio']) ?>)
            </a>
        </div>
    </div>

    <!-- Danh sách Users -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <?php if ($apiError): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-6 mb-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl mr-4"></i>
                    <div>
                        <h3 class="text-red-800 font-semibold mb-1">Lỗi khi lấy dữ liệu users</h3>
                        <p class="text-red-600 text-sm"><?= htmlspecialchars($apiError) ?></p>
                        <p class="text-red-500 text-xs mt-2">Vui lòng kiểm tra lại kết nối API hoặc liên hệ quản trị viên.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (empty($filteredUsers)): ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
                <p class="text-gray-500 text-lg">
                    <?php if ($currentTab === 'all'): ?>
                        <?php if ($apiError): ?>
                            Không thể tải danh sách users. Vui lòng thử lại sau.
                        <?php else: ?>
                            Chưa có user nào trong hệ thống.
                        <?php endif; ?>
                    <?php else: ?>
                        Không có user nào với role "<?= htmlspecialchars($currentTab) ?>".
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tên</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($filteredUsers as $user): 
                            $role = $user['role'] ?? 'User';
                            $roleColors = [
                                'Admin' => 'bg-red-100 text-red-700',
                                'User' => 'bg-blue-100 text-blue-700',
                                'Coordinator' => 'bg-green-100 text-green-700',
                                'Arrangement' => 'bg-purple-100 text-purple-700',
                                'Transcription' => 'bg-orange-100 text-orange-700',
                                'Recorder' => 'bg-yellow-100 text-yellow-700',
                                'Studio' => 'bg-indigo-100 text-indigo-700'
                            ];
                            $roleColor = $roleColors[$role] ?? 'bg-gray-100 text-gray-700';
                        ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                #<?= htmlspecialchars($user['id']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($user['name']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($user['email']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $roleColor ?>">
                                    <?= htmlspecialchars($role) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

