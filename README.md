# TPG Media Tools

A local web workspace for media. Downloads, compression, audio EQ, encoding utilities, and a PDF engine — all running on your machine, in your browser, behind a single PHP server.

Nothing phones home. Nothing asks you to sign in. Nothing limits you to three files a day.

<img width="1353" height="656" alt="WhatsApp Image 2026-10-09 at 05 33 10" src="https://github.com/user-attachments/assets/91af6b04-cb2e-455b-b7ea-c3f7b1924e46" />
<img width="1354" height="655" alt="image" src="https://github.com/user-attachments/assets/955c7e07-5939-4f77-b81b-d6ad2cab6602" />


---

## What's in the box

Seven tools, roughly in order of how often I use them.

**Video Downloader** — Paste a URL, pick a resolution, hit go. Or extract just the audio as MP3. Batch mode takes a `.txt` or `.csv` and chews through a list unattended. yt-dlp's output streams live into the browser as it works — no black box.

**EQ Studio** — A 13-band equalizer tuned for the Marshall Major IV, but useful for anything. Load a file, drag the sliders, listen. Batch mode applies the same curve across an entire folder. Preamp gain included so you don't clip.

**Video Compressor** — Drop a video, pick a CRF, walk away. Detects HDR, picks a hardware encoder if your GPU has one (NVENC / AMF / QuickSync), falls back to x265 on CPU. Non-MP4 inputs get converted automatically.

**IG-Image** — Lossless image compression that actually verifies its output. Every candidate gets decoded back and compared pixel-by-pixel against the original. If it doesn't match, it doesn't ship. Handles single files, multi-file drops, or an entire folder. Batch download as `.zip`.

**Base64 Converter** — Image → Base64 string or Base64 → image. Also useful for pasting a data URI back and forth when you're debugging CSS or building an inline asset.

**PageWright** — HTML → PDF. Write HTML and CSS on the left, see live pagination on the right, print to PDF. Pure PHP, no `wkhtmltopdf`, no headless Chrome, no external service.

**Update** — Reserved. Ignore for now.

<img width="1355" height="625" alt="image" src="https://github.com/user-attachments/assets/17d8416f-27b8-4ec0-b6a1-512b3aeb5c1f" />
<img width="1349" height="644" alt="image" src="https://github.com/user-attachments/assets/1c334ce0-81c4-4f8f-a368-27f96b685bfa" />
<img width="1347" height="656" alt="image" src="https://github.com/user-attachments/assets/397689f4-0275-4a7b-ad2e-7bfab3d6137d" />



---

## Requirements

- PHP 8 or newer
- `ffmpeg`, `ffprobe`, and `yt-dlp` on your PATH
- A modern browser

Don't have yt-dlp? `pip install -U yt-dlp`, or grab a binary from their releases page. FFmpeg is in every package manager worth using.

---

## Setup

```bash
git clone https://github.com/yourname/tpg-media-tools.git
cd tpg-media-tools
```

If you'd rather keep the binaries contained, drop them in `bin/`:

```
bin/
├── ffmpeg
├── ffprobe
└── yt-dlp
```

Then point PATH at that folder.

**Windows** — System Properties → Advanced → Environment Variables → Path → add the folder. Close and reopen your terminal or it won't take.

**macOS / Linux** — add to your shell rc:

```bash
export PATH="$PATH:/path/to/project/bin"
```

Reload the shell.

Run it:

```bash
php -S localhost:8000
```

Open http://localhost:8000.

That's it. No build step. No `npm install`. No database.

---

## How the tools actually work

### Video Downloader
<img width="1349" height="641" alt="image" src="https://github.com/user-attachments/assets/66a35b44-ab2f-4000-af6b-f5e4348733e4" />
<img width="1353" height="653" alt="image" src="https://github.com/user-attachments/assets/c9a408d6-3d6c-42ef-9daf-280b23619c70" />


Frontend sends a POST to `download.php`. The PHP side runs yt-dlp and pipes stdout back to the browser as a stream. You see yt-dlp's own output — progress bars, format selection, merge steps, everything. Some of it is ugly. That's fine. It tells you exactly what's happening.

Batch mode accepts one URL per line. Format selection applies to every entry in the batch.

### EQ Studio

<img width="1353" height="637" alt="image" src="https://github.com/user-attachments/assets/30aa074b-83ff-4807-beea-5617f90dffb0" />
<img width="1353" height="647" alt="image" src="https://github.com/user-attachments/assets/f2aeaa56-7092-4c54-97fc-78c0c4a49368" />


Built around FFmpeg's `equalizer` filter chain. Thirteen bands from 31 Hz to 20 kHz, ±30 dB each, plus a preamp stage. Presets are stored client-side. Batch mode walks a folder and applies the same curve to every audio file inside.

If you're using the Marshall Major IV defaults that ship with the tool, it's a mild smile curve — slight bass lift, slight presence bump, small cut at 500 Hz. Change it however you want; the defaults are just a starting point.

### Video Compressor
<img width="1350" height="647" alt="image" src="https://github.com/user-attachments/assets/e588c858-b1bc-4682-b928-bcb289742b50" />


The pipeline is: `ffprobe` → detect codec, bitrate, color space, HDR metadata → pick encoder → build an FFmpeg command with sane flags → run it.

Hardware encoder order is NVENC → AMF → QuickSync → x265 CPU. If your GPU driver is out of date or the encoder isn't available, it falls back silently. The log tells you which path it took.

HDR content stays 10-bit. If you override the flags manually and force 8-bit, gradients will band. That's not a bug, that's how color depth works.

### IG-Image
(Images already have show above)

The interesting part: it doesn't trust the encoder. For each input, it generates candidates (PNG, lossless WebP) and then **decodes each candidate back and compares it pixel-by-pixel against the original**. Using a `Uint32Array` view over the ImageData buffer, so it's fast enough to feel instant even on large images.

Only candidates that pass the pixel check are eligible. Among the survivors, the smallest one wins. If nothing beats the original size, you get the original back with a "no reduction" note — no fake savings.

Batch mode: drop files, drop a folder, or both. Each entry shows its own status. When the queue finishes, download everything as a single `.zip`.

### Base64 Converter

<img width="1354" height="648" alt="image" src="https://github.com/user-attachments/assets/1f6f627d-e99d-4e73-89bb-56f6152dbc4c" />
<img width="1352" height="654" alt="image" src="https://github.com/user-attachments/assets/23ceb103-35ca-4713-b4a0-8346ed2db54d" />


Two modes. Image → string, string → image. Detects the MIME type from the Base64 prefix if you paste a bare string. Nothing fancy, but it's the tool I reach for most when building inline SVG assets or debugging a broken data URI.

### PageWright

<img width="1350" height="645" alt="image" src="https://github.com/user-attachments/assets/e130205c-c9de-4e7a-863c-02307e885d21" />
<img width="1349" height="650" alt="image" src="https://github.com/user-attachments/assets/6da14b11-9cf1-400c-b810-c83c02bdb72a" />
<img width="1348" height="651" alt="image" src="https://github.com/user-attachments/assets/c2c22229-4784-4dca-88b1-6e798c32ba2d" />

A self-contained HTML → PDF engine written in pure PHP. No external binaries, no headless browser, no Composer dependencies. It parses HTML, matches CSS selectors, lays out a document, paginates it, and writes the PDF byte stream by hand.

The frontend shows live pagination — you see the actual page breaks as you type. Diagnostics panel tells you what's supported and what isn't. Flexbox and grid aren't implemented; block and inline layout are. That's a real limitation and the diagnostics panel says so.

The Generate button uses the browser's own print-to-PDF. This keeps the client-side preview and the final output consistent — the same rendering engine, top to bottom.

---

## Files and folders

```
project/
├── bin/
│   ├── ffmpeg
│   ├── ffprobe
│   └── yt-dlp
│
├── index.html              Hub page — links to everything
├── downloader.html
├── convert.html
├── vidcom.html
├── base64.html
├── ig-image.html
├── pagewright.php
│
├── download.php            Backend for the downloader
├── compress.php            Backend for the compressor
├── convert.php             Backend for EQ Studio
├── got_path.php            Returns the default save path
│
├── style.css               Shared stylesheet
├── tools.json              Tool registry (for the site manager)
│
└── manager.py              Optional site manager (see below)
```

---

## Tools Manager (optional)

There's a small Python app called `manager.py` that runs a local web GUI for editing the site itself — adding new tool pages, reordering the navigation, syncing the nav across every HTML file, previewing changes as a diff, and rolling back from automatic backups.

```bash
python manager.py
```

Opens http://127.0.0.1:8765. Not required to use any of the media tools. It's for when you're adding your own tool to the suite.

---

## Stuff worth knowing

**Don't put this on the internet.** No authentication, no rate limiting, no CSRF protection, and the backend shells out to binaries that fetch arbitrary URLs on command. It's a local tool. Keep it that way. If you need it from another machine, use a VPN or an SSH tunnel — not a port forward.

**Update yt-dlp often.** Sites change their internals constantly. When downloads start failing for no obvious reason, it's almost always yt-dlp needing an update. `yt-dlp -U` fixes most of it.

**Hardware encoders are best-effort.** If NVENC isn't detected, it's usually a driver issue or a GPU that predates the feature. The compressor tells you which encoder it picked in the log — check that first before assuming something's broken.

**The console output is raw.** It's yt-dlp and ffmpeg talking directly to the browser. Some of it looks like garbage. It's not decoration; it's the actual state of the process.

**Large batches take time.** The image optimizer processes one file at a time to keep memory flat. A folder of 200 photos will take a few minutes even on fast hardware. That's the tradeoff for not crashing the tab.

**PageWright's CSS support is deliberately limited.** Block and inline layout, text wrapping, tables, lists, margins, padding, borders, colors, font properties. No flexbox, no grid, no floats, no `@media`, no custom properties. The diagnostics panel is honest about this. If you need full CSS, use a headless browser; if you want zero dependencies, this is what you get.

---

## Default save locations

```
Downloads    →  ~/Videos/Download
Compressed   →  ~/Videos/Compress
EQ output    →  chosen per-run
IG-Image     →  download from the browser
PageWright   →  browser print dialog
```

Both `Videos` subfolders get created on first use.

---

## License

GPL v3. If you fork it, keep the license. If you improve it, send a pull request.
