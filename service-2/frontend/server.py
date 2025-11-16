#!/usr/bin/env python3
"""
Simple HTTP server to serve frontend files
Run: python server.py
Access: http://localhost:8080
"""
import http.server
import socketserver
import os
import sys
from pathlib import Path

# Get the directory where this script is located
FRONTEND_DIR = Path(__file__).parent.absolute()
PORT = 8080

class CustomHTTPRequestHandler(http.server.SimpleHTTPRequestHandler):
    """Custom handler to serve files from frontend directory"""
    
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(FRONTEND_DIR), **kwargs)
    
    def end_headers(self):
        # Add CORS headers
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        super().end_headers()
    
    def log_message(self, format, *args):
        # Custom log format
        print(f"[{self.log_date_time_string()}] {format % args}")

def main():
    """Start the HTTP server"""
    os.chdir(FRONTEND_DIR)
    
    try:
        with socketserver.TCPServer(("", PORT), CustomHTTPRequestHandler) as httpd:
            print("=" * 60)
            print("🚀 MuTraPro Frontend Server")
            print("=" * 60)
            print(f"📁 Serving from: {FRONTEND_DIR}")
            print(f"🌐 Server running at: http://localhost:{PORT}")
            print(f"📄 Customer Dashboard: http://localhost:{PORT}/customer-dashboard.html")
            print(f"🔐 Auth Page: http://localhost:{PORT}/auth.html")
            print(f"🎵 Transcriber: http://localhost:{PORT}/index.html")
            print("=" * 60)
            print("Press Ctrl+C to stop the server")
            print("=" * 60)
            
            httpd.serve_forever()
            
    except KeyboardInterrupt:
        print("\n\n🛑 Server stopped by user")
        sys.exit(0)
    except OSError as e:
        if e.errno == 98 or e.errno == 48:  # Address already in use
            print(f"❌ Error: Port {PORT} is already in use!")
            print(f"   Please stop the process using port {PORT} or change the PORT in this script")
            print(f"\n   To find and kill the process:")
            print(f"   Windows: netstat -ano | findstr :{PORT}")
            print(f"   Then: taskkill /PID <PID> /F")
        else:
            print(f"❌ Error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()

