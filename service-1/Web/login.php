<?php
session_start();
$message = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $pass = $_POST['password'];

    // Gọi API .NET (địa chỉ của bạn)
    $api_url = "http://localhost:5200/api/Auth/login"; // ✅ Đúng với AuthController route

    $data = json_encode([
        "email" => $email,
        "password" => $pass
    ]);

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Xử lý phản hồi từ API
    if ($http_code == 200) {
        $result = json_decode($response, true);

        // Lưu token + user info vào session
        $_SESSION['token'] = $result['token'];
        $_SESSION['user'] = $result['user'];

        // Chuyển hướng theo role
        $role = strtolower($result['user']['role']);
        switch ($role) {
            case 'admin':
                header('Location: admin/admin_page.php');
                break;
            case 'user':
                header('Location: dashboard.php');
                break;
            case 'coordinator':
                header('Location: ../coordinator/coordinator_page.php');
                break;
            case 'arrangement':
                header('Location: ../arrangement/arrangement_page.php');
                break;
            case 'transcription':
                header('Location: ../transcription/transcription_page.php');
                break;
            case 'recording_artists':
                header('Location: ../recording_artists/recording_artists_page.php');
                break;
            case 'studio':
                header('Location: studio/studio_page.php');
                break;
            default:
                header('Location: dashboard.php');
                break;
        }
        exit();
    } else {
        $result = json_decode($response, true);
        $message[] = $result['message'] ?? 'Đăng nhập thất bại! Vui lòng thử lại.';
    }
}
?>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - MuTraPro</title>
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
                        danger: '#dc2626'
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-900 via-purple-900 to-indigo-900 flex items-center justify-center p-4">

```
<!-- Background Pattern -->
<div class="absolute inset-0 bg-black bg-opacity-20"></div>
<div class="absolute inset-0" style="background-image: url('https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80'); background-size: cover; background-position: center; opacity: 0.1;"></div>

<!-- Error Messages -->
<div id="messageContainer" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 space-y-2"></div>

<!-- Login Container -->
<div class="relative z-10 w-full max-w-md">
    <!-- Logo/Brand -->
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-white bg-opacity-20 rounded-full mb-4 backdrop-blur-sm">
            <i class="fas fa-music text-2xl text-white"></i>
        </div>
        <h1 class="text-3xl font-bold text-white mb-2">MuTraPro</h1>
        <p class="text-blue-200">Hệ thống quản lý âm nhạc chuyên nghiệp</p>
    </div>
    
    <!-- Login Form -->
    <div class="bg-white bg-opacity-10 backdrop-blur-lg rounded-2xl shadow-2xl p-8 border border-white border-opacity-20">
        <form action="" method="post" class="space-y-6">
            <div class="text-center mb-6">
                <h3 class="text-2xl font-bold text-white mb-2">Đăng nhập</h3>
                <p class="text-blue-200 text-sm">Chào mừng bạn quay trở lại</p>
            </div>
            
            <!-- Email Input -->
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fas fa-envelope text-blue-300"></i>
                </div>
                <input type="email" 
                       name="email" 
                       placeholder="Nhập địa chỉ email" 
                       required 
                       class="w-full pl-12 pr-4 py-4 bg-white bg-opacity-10 border border-white border-opacity-20 rounded-xl text-white placeholder-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent transition-all backdrop-blur-sm">
            </div>
            
            <!-- Password Input -->
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fas fa-lock text-blue-300"></i>
                </div>
                <input type="password" 
                       name="password" 
                       placeholder="Nhập mật khẩu" 
                       required 
                       id="passwordInput"
                       class="w-full pl-12 pr-12 py-4 bg-white bg-opacity-10 border border-white border-opacity-20 rounded-xl text-white placeholder-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent transition-all backdrop-blur-sm">
                <button type="button" 
                        onclick="togglePassword()" 
                        class="absolute inset-y-0 right-0 pr-4 flex items-center text-blue-300 hover:text-white transition-colors">
                    <i class="fas fa-eye" id="eyeIcon"></i>
                </button>
            </div>
            
            <!-- Remember Me & Forgot Password -->
            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center text-blue-200 cursor-pointer">
                    <input type="checkbox" class="mr-2 rounded border-white border-opacity-20 bg-white bg-opacity-10 text-blue-500 focus:ring-blue-400">
                    <span>Ghi nhớ đăng nhập</span>
                </label>
                <a href="forget_pass.php" class="text-blue-300 hover:text-white transition-colors hover:underline">
                    Quên mật khẩu?
                </a>
            </div>
            
            <!-- Submit Buttons -->
            <div class="space-y-3">
                <button type="submit" 
                        name="submit" 
                        class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-4 rounded-xl font-semibold text-lg hover:from-blue-700 hover:to-purple-700 transition-all transform hover:scale-105 shadow-lg">
                    <i class="fas fa-sign-in-alt mr-2"></i>Đăng nhập
                </button>
                
                <button type="button" 
                        onclick="window.location.href='dashboard.php'" 
                        class="w-full bg-white bg-opacity-10 text-white py-4 rounded-xl font-semibold border border-white border-opacity-20 hover:bg-opacity-20 transition-all backdrop-blur-sm">
                    <i class="fas fa-home mr-2"></i>Về trang chủ
                </button>
            </div>
            
            <!-- Register Link -->
            <div class="text-center pt-4 border-t border-white border-opacity-20">
                <p class="text-blue-200">
                    Chưa có tài khoản? 
                    <a href="register.php" class="text-white font-semibold hover:text-blue-300 transition-colors hover:underline">
                        Đăng ký ngay
                    </a>
                </p>
            </div>
        </form>
    </div>
    
    <!-- Social Login (Optional) -->
    <div class="mt-8 text-center">
        <p class="text-blue-200 text-sm mb-4">Hoặc đăng nhập bằng</p>
        <div class="flex justify-center space-x-4">
            <button class="w-12 h-12 bg-white bg-opacity-10 rounded-full flex items-center justify-center text-white hover:bg-opacity-20 transition-all backdrop-blur-sm border border-white border-opacity-20">
                <i class="fab fa-google"></i>
            </button>
            <button class="w-12 h-12 bg-white bg-opacity-10 rounded-full flex items-center justify-center text-white hover:bg-opacity-20 transition-all backdrop-blur-sm border border-white border-opacity-20">
                <i class="fab fa-facebook-f"></i>
            </button>
            <button class="w-12 h-12 bg-white bg-opacity-10 rounded-full flex items-center justify-center text-white hover:bg-opacity-20 transition-all backdrop-blur-sm border border-white border-opacity-20">
                <i class="fab fa-apple"></i>
            </button>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="text-center mt-8 text-blue-200 text-sm">
        <p>&copy; 2024 MuTraPro. Tất cả quyền được bảo lưu.</p>
    </div>
</div>

<script>
    // Toggle password visibility
    function togglePassword() {
        const passwordInput = document.getElementById('passwordInput');
        const eyeIcon = document.getElementById('eyeIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            eyeIcon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    // Auto-hide messages
    function hideMessage(element) {
        setTimeout(() => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(-20px)';
            setTimeout(() => element.remove(), 300);
        }, 5000);
    }

    // Form submit animation
    document.querySelector('form').addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang đăng nhập...';
        submitBtn.disabled = true;
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 3000);
    });

    // Floating animation
    const formContainer = document.querySelector('.bg-white.bg-opacity-10');
    let mouseX = 0, mouseY = 0, formX = 0, formY = 0;
    document.addEventListener('mousemove', (e) => {
        mouseX = (e.clientX - window.innerWidth / 2) / 50;
        mouseY = (e.clientY - window.innerHeight / 2) / 50;
    });
    function animate() {
        formX += (mouseX - formX) * 0.1;
        formY += (mouseY - formY) * 0.1;
        formContainer.style.transform = `translate(${formX}px, ${formY}px)`;
        requestAnimationFrame(animate);
    }
    animate();

    // Display PHP messages
    const messages = <?php echo json_encode($message ?? []); ?>;
    const messageContainer = document.getElementById('messageContainer');
    messages.forEach(msg => {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'bg-red-500 bg-opacity-90 text-white px-6 py-4 rounded-lg shadow-lg backdrop-blur-sm border border-red-400 border-opacity-50 transform transition-all duration-300';
        messageDiv.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-3"></i>
                    <span>${msg}</span>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-red-200 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        messageContainer.appendChild(messageDiv);
        hideMessage(messageDiv);
    });
</script>
```

</body>
</html>