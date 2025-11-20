#!/usr/bin/env python3
"""
PHP Development Server Wrapper
Run: python server.py
Access: http://localhost:8082
"""
import subprocess
import sys
import os
from pathlib import Path

# Get the directory where this script is located
WEB_DIR = Path(__file__).parent.absolute()
PORT = 8082

def check_php():
    """Check if PHP is installed"""
    try:
        result = subprocess.run(['php', '--version'], 
                              capture_output=True, 
                              text=True,
                              timeout=5)
        if result.returncode == 0:
            print(f"✅ PHP found: {result.stdout.split()[1]}")
            return True
        else:
            print("❌ PHP not found!")
            return False
    except FileNotFoundError:
        print("❌ PHP is not installed or not in PATH!")
        print("\nPlease install PHP:")
        print("   - Download from: https://www.php.net/downloads.php")
        print("   - Or use XAMPP/WAMP which includes PHP")
        return False
    except Exception as e:
        print(f"❌ Error checking PHP: {e}")
        return False

def main():
    """Start PHP development server"""
    os.chdir(WEB_DIR)
    
    print("=" * 60)
    print("🚀 MuTraPro PHP Server")
    print("=" * 60)
    
    # Check PHP installation
    if not check_php():
        sys.exit(1)
    
    print(f"📁 Serving from: {WEB_DIR}")
    print(f"🌐 Server will run at: http://localhost:{PORT}")
    print(f"🔐 Login: http://localhost:{PORT}/login.php")
    print(f"📝 Register: http://localhost:{PORT}/register.php")
    print(f"👤 Admin Panel: http://localhost:{PORT}/admin/admin_page.php")
    print("=" * 60)
    print("Press Ctrl+C to stop the server")
    print("=" * 60)
    print()
    
    try:
        # Start PHP development server
        subprocess.run(['php', '-S', f'localhost:{PORT}'], 
                      cwd=str(WEB_DIR))
    except KeyboardInterrupt:
        print("\n\n🛑 Server stopped by user")
        sys.exit(0)
    except Exception as e:
        if "Address already in use" in str(e) or "Only one usage" in str(e):
            print(f"\n❌ Error: Port {PORT} is already in use!")
            print(f"   Please stop the process using port {PORT} or change the PORT in this script")
            print(f"\n   To find and kill the process:")
            print(f"   Windows: netstat -ano | findstr :{PORT}")
            print(f"   Then: taskkill /PID <PID> /F")
        else:
            print(f"\n❌ Error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()

