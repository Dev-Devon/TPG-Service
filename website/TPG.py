import os
import webbrowser
import time
import subprocess
import sys

def kill_tpg():
    """Kill any running TPG.exe processes"""
    try:
        subprocess.run('taskkill /F /IM TPG.exe', 
                      shell=True, 
                      capture_output=True, 
                      text=True)
    except:
        pass

def main():
    # Kill existing TPG
    kill_tpg()
    
    # Open index.html
    index_path = os.path.join(os.path.dirname(__file__), 'index.html')
    webbrowser.open(index_path)
    
    print("TPG Service Started")
    print("Monitoring for TPG.exe...")
    print("Press Ctrl+C to stop")
    
    try:
        while True:
            time.sleep(60)  # Check every minute
            kill_tpg()      # Kill if it appears
    except KeyboardInterrupt:
        print("\nTPG Service Stopped")
        kill_tpg()  # Cleanup
        sys.exit(0)

if __name__ == '__main__':
    main()