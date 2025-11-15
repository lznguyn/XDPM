<?php
$messages = [];

if(isset($_POST['submit'])){
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $pass = $_POST['password'];
    $cpass = $_POST['cpassword'];
    $user_type = $_POST['role'] ?? '';
    $admin_code = $_POST['admin_code'] ?? '';

    if ($pass !== $cpass) {
        $messages[] = 'Mật khẩu xác nhận không trùng khớp!';
    } elseif (strlen($pass) < 8) {
        $messages[] = 'Mật khẩu phải có ít nhất 8 ký tự!';
    } elseif ($user_type === 'admin' && $admin_code !== 'admin123') {
        $messages[] = 'Mã xác nhận Admin không đúng!';
    } else {
        // 🔹 Gọi API backend .NET
        $api_url = "http://localhost:5200/api/Auth/register";
        $roleMap = [
            'admin' => 0,
            'user' => 1,
            'coordinator' => 2,
            'arrangement' => 3,
            'transcription' => 4,
            'recorder' => 5,
            'studio' => 6
        ];

        $roleInt = $roleMap[strtolower($user_type)] ?? 1; 
        $payload = [
            "name" => $name,
            "email" => $email,
            "password" => $pass,
            "confirmPassword" => $cpass,
            "role" => $roleInt // Admin, User, Coordinator, ...
        ];
        if (strtolower($user_type) === 'admin') {
            // Đảm bảo tên key là 'adminCode' (camelCase) để khớp với DTO C#
            $payload['adminCode'] = $admin_code; 
        }

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status === 200 || $http_status === 201) {
            $messages[] = 'Đăng ký thành công! Vui lòng đăng nhập.';
            echo '<meta http-equiv="refresh" content="2;url=login.php">';
        } else {
            $messages[] = 'Đăng ký thất bại! Vui lòng kiểm tra lại thông tin.' . $response;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - MuTraPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-purple-900 via-blue-900 to-indigo-900 flex items-center justify-center p-4">

    <div class="absolute inset-0 bg-black bg-opacity-20"></div>

    <div id="messageContainer" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 space-y-2 max-w-md w-full px-4"></div>

    <div class="relative z-10 w-full max-w-lg">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white bg-opacity-20 rounded-full mb-4 backdrop-blur-sm">
                <i class="fas fa-music text-2xl text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">MuTraPro</h1>
            <p class="text-blue-200">Tạo tài khoản để bắt đầu hành trình âm nhạc</p>
        </div>

        <div class="bg-white bg-opacity-10 backdrop-blur-lg rounded-2xl shadow-2xl p-8 border border-white border-opacity-20">
            <form action="" method="post" class="space-y-6">
                <div class="text-center mb-6">
                    <h3 class="text-2xl font-bold text-white mb-2">Đăng ký tài khoản</h3>
                    <p class="text-blue-200 text-sm">Điền thông tin để tạo tài khoản mới</p>
                </div>

                <input type="text" name="name" placeholder="Nhập họ và tên" required class="w-full px-4 py-4 bg-white bg-opacity-10 border border-white border-opacity-20 rounded-xl text-white placeholder-blue-200 focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                <input type="email" name="email" placeholder="Nhập địa chỉ email" required class="w-full px-4 py-4 bg-white bg-opacity-10 border border-white border-opacity-20 rounded-xl text-white placeholder-blue-200 focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                <input type="password" name="password" id="passwordInput" placeholder="Nhập mật khẩu (tối thiểu 8 ký tự)" required minlength="8" class="w-full px-4 py-4 bg-white bg-opacity-10 border border-white border-opacity-20 rounded-xl text-white placeholder-blue-200 focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                <input type="password" name="cpassword" id="confirmPasswordInput" placeholder="Nhập lại mật khẩu" required minlength="8" class="w-full px-4 py-4 bg-white bg-opacity-10 border border-white border-opacity-20 rounded-xl text-white placeholder-blue-200 focus:ring-2 focus:ring-blue-400 focus:border-transparent">

                <select name="role" onchange="toggleAdminCodeField(this)" class="w-full px-4 py-4 bg-white bg-opacity-10 border border-white border-opacity-20 rounded-xl text-white focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                    <option value="user">Người dùng</option>
                    <option value="admin">Quản trị viên</option>
                    <option value="coordinator">Điều phối viên</option>
                    <option value="arrangement">Chuyên gia Hòa âm</option>
                    <option value="transcription">Chuyên gia Phiên âm</option>
                    <option value="recorder">Nghệ sĩ Thu âm</option>
                    <option value="studio">Phòng thu âm</option>
                   
                </select>

                <div id="admin-code-container" class="hidden">
                    <input type="text" name="admin_code" placeholder="Nhập mã xác thực Admin" class="w-full px-4 py-4 bg-yellow-500 bg-opacity-10 border border-yellow-400 border-opacity-30 rounded-xl text-white placeholder-yellow-200 focus:ring-2 focus:ring-yellow-400 focus:border-transparent">
                </div>

                <div class="flex items-start space-x-3">
                    <input type="checkbox" id="terms" required class="mt-1 rounded border-white border-opacity-20 bg-white bg-opacity-10 text-blue-500 focus:ring-blue-400">
                    <label for="terms" class="text-blue-200 text-sm cursor-pointer">
                        Tôi đồng ý với 
                        <a href="#" class="text-white underline hover:text-blue-300">Điều khoản sử dụng</a> 
                        và 
                        <a href="#" class="text-white underline hover:text-blue-300">Chính sách bảo mật</a>.
                    </label>
                </div>

                <button type="submit" name="submit" class="w-full bg-gradient-to-r from-green-600 to-blue-600 text-white py-4 rounded-xl font-semibold text-lg hover:from-green-700 hover:to-blue-700 transition transform hover:scale-105">
                    <i class="fas fa-user-plus mr-2"></i>Đăng ký tài khoản
                </button>
            </form>
        </div>

        <div class="text-center mt-8 text-blue-200 text-sm">
            <p>&copy; 2025 MuTraPro. Tất cả quyền được bảo lưu.</p>
        </div>
    </div>

    <script>
        const messages = <?php echo json_encode($messages ?? []); ?>;
        const messageContainer = document.getElementById('messageContainer');
        messages.forEach(msg => {
            const div = document.createElement('div');
            const success = msg.includes('thành công');
            div.className = `${success ? 'bg-green-500' : 'bg-red-500'} text-white px-6 py-4 rounded-lg shadow-lg backdrop-blur-sm border border-opacity-50`;
            div.innerHTML = `
                <div class="flex items-center justify-between">
                    <span><i class="fas ${success ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>${msg}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-3 hover:text-gray-200"><i class="fas fa-times"></i></button>
                </div>`;
            messageContainer.appendChild(div);
            setTimeout(() => div.remove(), 5000);
        });

        function toggleAdminCodeField(select) {
            const adminField = document.getElementById('admin-code-container');
            adminField.classList.toggle('hidden', select.value !== 'admin');
        }
    </script>
</body>
</html>
