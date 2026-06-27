import os
import sys
import time
import subprocess
import webbrowser
import socket
import psutil
import threading
import signal
from datetime import datetime
import atexit

class WebAppLauncher:
    def __init__(self):
        self.app_name = "TPG"
        self.script_dir = os.path.dirname(os.path.abspath(__file__))
        self.website_root = self.script_dir
        self.php_exe = os.path.join(self.script_dir, 'bin', 'php.exe')
        self.port = 8000
        self.server_process = None
        self.log_file = os.path.join(os.environ['TEMP'], f'{self.app_name}.log')
        self.running = True
        
        # Register cleanup on exit
        atexit.register(self.cleanup)
        signal.signal(signal.SIGINT, self.signal_handler)
        signal.signal(signal.SIGTERM, self.signal_handler)
    
    def log(self, message):
        timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        log_message = f'{timestamp} - {message}'
        print(log_message)
        with open(self.log_file, 'a') as f:
            f.write(log_message + '\n')
    
    def signal_handler(self, signum, frame):
        self.log('Received shutdown signal')
        self.cleanup()
        sys.exit(0)
    
    def cleanup(self):
        self.running = False
        self.kill_process('TPG.exe')
        if self.server_process and self.server_process.poll() is None:
            self.server_process.terminate()
            time.sleep(1)
            if self.server_process.poll() is None:
                self.server_process.kill()
        self.kill_process('php.exe')
        self.log('Cleanup completed')
    
    def kill_process(self, process_name):
        try:
            for proc in psutil.process_iter(['pid', 'name']):
                if proc.info['name'] and proc.info['name'].lower() == process_name.lower():
                    self.log(f'Killing {process_name} (PID: {proc.info["pid"]})')
                    proc.kill()
            return True
        except Exception as e:
            self.log(f'Error killing {process_name}: {e}')
            return False
    
    def find_free_port(self):
        sock = socket.socket()
        sock.bind(('', 0))
        port = sock.getsockname()[1]
        sock.close()
        return port
    
    def check_requirements(self):
        # Check PHP
        if not os.path.exists(self.php_exe):
            self.log(f'ERROR: PHP not found at {self.php_exe}')
            input('Press Enter to exit...')
            return False
        
        # Check index.html
        index_path = os.path.join(self.website_root, 'index.html')
        if not os.path.exists(index_path):
            self.log(f'ERROR: index.html not found at {index_path}')
            input('Press Enter to exit...')
            return False
        
        return True
    
    def start_server(self):
        # Kill existing TPG.exe
        self.kill_process('TPG.exe')
        time.sleep(1)
        
        # Find free port
        self.port = self.find_free_port()
        server_address = f'localhost:{self.port}'
        url = f'http://{server_address}'
        
        self.log(f'Starting server on {url}')
        
        # Start PHP server
        try:
            self.server_process = subprocess.Popen(
                [self.php_exe, '-S', server_address, '-t', self.website_root],
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                creationflags=subprocess.CREATE_NO_WINDOW if sys.platform == 'win32' else 0
            )
        except Exception as e:
            self.log(f'Failed to start PHP server: {e}')
            return False
        
        # Wait for server to start
        time.sleep(2)
        
        # Open browser
        webbrowser.open(url)
        
        return True
    
    def monitor_loop(self):
        self.log('Monitoring started. Press Ctrl+C to stop.')
        
        while self.running:
            time.sleep(60)  # Check every minute
            
            # Check if PHP server is still running
            if self.server_process and self.server_process.poll() is not None:
                self.log('PHP server stopped unexpectedly')
                self.cleanup()
                sys.exit(1)
            
            # Check if TPG.exe is running (to prevent it)
            try:
                tpg_running = False
                for proc in psutil.process_iter(['name']):
                    if proc.info['name'] and proc.info['name'].lower() == 'tpg.exe':
                        tpg_running = True
                        break
                
                if tpg_running:
                    self.log('Found TPG.exe running. Killing it...')
                    self.kill_process('TPG.exe')
                    time.sleep(2)
                    
                    # Restart PHP server if it was killed
                    if self.server_process and self.server_process.poll() is not None:
                        self.log('Restarting PHP server...')
                        self.start_server()
            except Exception as e:
                self.log(f'Error in monitor loop: {e}')
    
    def run(self):
        try:
            # Log startup
            self.log(f'{self.app_name} started')
            
            # Check requirements
            if not self.check_requirements():
                return 1
            
            # Kill existing processes
            self.kill_process('TPG.exe')
            self.kill_process('php.exe')
            time.sleep(1)
            
            # Start the server
            if not self.start_server():
                return 1
            
            # Start monitoring
            self.monitor_loop()
            
        except KeyboardInterrupt:
            self.log('User interrupted')
            self.cleanup()
            return 0
        except Exception as e:
            self.log(f'Unexpected error: {e}')
            self.cleanup()
            return 1
        
        return 0

if __name__ == '__main__':
    launcher = WebAppLauncher()
    sys.exit(launcher.run())