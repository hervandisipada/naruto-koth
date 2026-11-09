import http.server
import socketserver
import json
import socket
import os
import threading
import time

PORT = 8000
STATE_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'state.json')

default_state = {
    "p1": None,
    "p2": None,
    "queue": [],
    "saved": []
}

# In-memory state
current_state = default_state
clients_lock = threading.Lock()
sse_clients = []

def load_initial_state():
    global current_state
    if os.path.exists(STATE_FILE):
        try:
            with open(STATE_FILE, 'r', encoding='utf-8') as f:
                current_state = json.load(f)
        except Exception as e:
            print(f"[!] Error loading {STATE_FILE}: {e}")
            current_state = default_state
    else:
        current_state = default_state

def save_current_state():
    try:
        with open(STATE_FILE, 'w', encoding='utf-8') as f:
            json.dump(current_state, f, ensure_ascii=False, indent=2)
    except Exception as e:
        print(f"[!] Error saving state: {e}")

def broadcast_state():
    with clients_lock:
        data = f"data: {json.dumps(current_state)}\n\n"
        dead_clients = []
        for wfile in sse_clients:
            try:
                wfile.write(data.encode('utf-8'))
                wfile.flush()
            except Exception:
                dead_clients.append(wfile)
        for dead in dead_clients:
            if dead in sse_clients:
                sse_clients.remove(dead)

def get_local_ip():
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        ip = s.getsockname()[0]
        s.close()
        return ip
    except Exception:
        return "127.0.0.1"

class KotHHandler(http.server.SimpleHTTPRequestHandler):
    def end_headers(self):
        # Enable CORS for local dev
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type')
        self.send_header('Cache-Control', 'no-cache, no-store, must-revalidate')
        super().end_headers()

    def do_OPTIONS(self):
        self.send_response(200)
        self.end_headers()

    def do_GET(self):
        if self.path == '/api/state':
            self.send_response(200)
            self.send_header('Content-Type', 'application/json; charset=utf-8')
            self.end_headers()
            self.wfile.write(json.dumps(current_state).encode('utf-8'))
            return
        elif self.path == '/api/events':
            self.send_response(200)
            self.send_header('Content-Type', 'text/event-stream')
            self.send_header('Connection', 'keep-alive')
            self.end_headers()

            # Send current state immediately on connect
            init_data = f"data: {json.dumps(current_state)}\n\n"
            try:
                self.wfile.write(init_data.encode('utf-8'))
                self.wfile.flush()
            except Exception:
                return

            with clients_lock:
                sse_clients.append(self.wfile)

            # Keep connection open until client disconnects
            try:
                while True:
                    time.sleep(15)
                    # Ping to keep connection alive
                    self.wfile.write(b": ping\n\n")
                    self.wfile.flush()
            except Exception:
                with clients_lock:
                    if self.wfile in sse_clients:
                        sse_clients.remove(self.wfile)
            return
        elif self.path == '/' or self.path == '/admin':
            self.path = '/index.html'
        elif self.path == '/overlay':
            self.path = '/overlay.html'

        return super().do_GET()

    def do_POST(self):
        global current_state
        if self.path == '/api/state':
            content_length = int(self.headers.get('Content-Length', 0))
            post_body = self.rfile.read(content_length)
            try:
                new_state = json.loads(post_body.decode('utf-8'))
                current_state = new_state
                save_current_state()
                broadcast_state()
                self.send_response(200)
                self.send_header('Content-Type', 'application/json')
                self.end_headers()
                self.wfile.write(b'{"status": "ok"}')
            except Exception as e:
                self.send_response(400)
                self.end_headers()
                self.wfile.write(f'{{"error": "{str(e)}" }}'.encode('utf-8'))
            return

        self.send_response(404)
        self.end_headers()

class ThreadingHTTPServer(socketserver.ThreadingMixIn, http.server.HTTPServer):
    daemon_threads = True

def run():
    load_initial_state()
    local_ip = get_local_ip()
    server_address = ('0.0.0.0', PORT)
    httpd = ThreadingHTTPServer(server_address, KotHHandler)

    print("=" * 65)
    print("  👑 NARUTO STORM KOTH - LIVE OVERLAY & CONTROLLER SYSTEM")
    print("=" * 65)
    print(f"🎬 LINK OVERLAY TIKTOK LIVE STUDIO:")
    print(f"   http://localhost:{PORT}/overlay.html")
    print()
    print(f"💻 LINK PANEL ADMIN (PC / LAPTOP):")
    print(f"   http://localhost:{PORT}")
    print()
    print(f"📱 LINK PANEL ADMIN (HP / SMARTPHONE - Wi-Fi Sama):")
    print(f"   http://{local_ip}:{PORT}")
    print("=" * 65)
    print("Server berjalan... Tekan Ctrl+C untuk menghentikan.")

    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\nServer dihentikan.")
        httpd.server_close()

if __name__ == '__main__':
    run()

