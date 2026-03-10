# TPG Media Tools (Local)

A lightweight local web interface for downloading and compressing media using **yt-dlp** and **FFmpeg**.

The project runs locally through PHP and provides a browser UI for:

- Downloading videos or audio from supported sources
- Batch downloading from a text file
- Selecting resolution for video downloads
- Compressing videos with automatic encoder detection

All processing happens locally on the machine running the server.

---

## Requirements

- PHP 8+
- FFmpeg
- FFprobe
- yt-dlp
- Modern web browser

---

## Setup

### 1. Clone the Repository

```bash
git clone https://github.com/yourname/tpg-media-tools.git
cd tpg-media-tools
```

---

### 2. Add the `bin` Directory to PATH

The application expects **FFmpeg**, **FFprobe**, and **yt-dlp** to be available.

Place these binaries inside the `bin` directory included in the repository.

Example structure:

```
project/
│
├─ bin/
│  ├─ ffmpeg
│  ├─ ffprobe
│  └─ yt-dlp
│
├─ downloader.html
├─ vidcom.html
├─ download.php
└─ compress.php
```

Add the `bin` directory to your system **environment PATH**.

#### Windows

1. Open **System Properties**
2. Go to **Advanced → Environment Variables**
3. Edit **Path**
4. Add:

```
C:\path\to\project\bin
```

#### Linux / macOS

Add this to `.bashrc`, `.zshrc`, or your shell profile:

```bash
export PATH="$PATH:/path/to/project/bin"
```

Reload the shell or restart the system.

---

### 3. Start the PHP Server

From the project directory run:

```bash
php -S localhost:8000
```

Open the application in your browser:

```
http://localhost:8000
```

---

## Applications

### Video & Audio Downloader

Uses **yt-dlp** to download media from supported sources.

Features:

- Video download with selectable resolution
- Audio extraction to MP3
- Batch downloads from `.txt` or `.csv`
- Custom save location
- Live console output in the browser

The frontend sends requests to `download.php`, which executes `yt-dlp` and streams its output back to the browser.

---

### Video Compressor

Uses **FFmpeg** to compress uploaded videos.

Features:

- Automatic hardware encoder detection  
  - NVIDIA NVENC  
  - AMD AMF  
  - Intel QuickSync
- CPU fallback (x265)
- HDR detection with safe 10-bit encoding
- Smart bitrate targeting
- CRF mode for low bitrate or short videos
- Automatic container conversion if the input is not MP4

Compression is handled by `compress.php`, which:

1. Analyzes the input video using **ffprobe**
2. Detects HDR and bitrate characteristics
3. Selects the most appropriate encoder
4. Runs FFmpeg with optimized settings
5. Returns the compressed file information to the UI

---

## Default Save Locations

Downloaded videos:

```
~/Videos/Download
```

Compressed videos:

```
~/Videos/Compress
```

The application automatically creates these directories if they do not exist.

---

## Notes

- This project is intended for **local use**
- No authentication or security hardening is implemented
- Do not expose the PHP server directly to the internet

---

## License

GPL v3 License
