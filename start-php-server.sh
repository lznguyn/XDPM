#!/bin/bash
# PHP Development Server Script
# Run: bash start-php-server.sh
# Or: chmod +x start-php-server.sh && ./start-php-server.sh

cd service-1/Web

echo "========================================"
echo "🚀 Starting PHP Server for MuTraPro Web..."
echo "========================================"
echo

# Check PHP installation
if ! command -v php &> /dev/null; then
    echo "❌ ERROR: PHP is not installed or not in PATH!"
    echo
    echo "Please install PHP:"
    echo "   - Ubuntu/Debian: sudo apt-get install php"
    echo "   - macOS: brew install php"
    echo "   - Or download from: https://www.php.net/downloads.php"
    echo
    exit 1
fi

echo "✅ PHP found: $(php --version | head -n 1)"
echo
echo "📁 Serving from: $(pwd)"
echo "🌐 Server running at: http://localhost:8082"
echo "🔐 Login page: http://localhost:8082/login.php"
echo "📝 Register page: http://localhost:8082/register.php"
echo "👤 Admin Panel: http://localhost:8082/admin/admin_page.php"
echo
echo "Press Ctrl+C to stop the server."
echo "========================================"
echo

php -S localhost:8082

