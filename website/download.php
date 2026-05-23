<?php
// Disable output buffering to allow real-time log streaming
while (ob_get_level()) { ob_end_clean(); }
header("Content-Type: text/plain");
header("Cache-Control: no-cache");
header("X-Accel-Buffering: no");
ob_implicit_flush(true);

// Keep script running even if user closes tab (long downloads)
ignore_user_abort(true);
set_time_limit(0);

// Use Chrome UA to avoid strict CDN blocks on some platforms
 $ua = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36";

// Logging helper for "alive" feeling
function logLine($msg) {
    echo "[" . date("H:i:s") . "] " . $msg . "\n";
    flush();
}

 $savePath = $_POST['path'] ?? '';
 $url = $_POST['url'] ?? '';
 $res = intval($_POST['resolution'] ?? 1080); // Force integer
 $dlVideo = $_POST['video'] ?? '0';
 $dlAudio = $_POST['audio'] ?? '0';

 $urls = [];

// Handle Batch File upload
if (isset($_FILES['batchFile']) && $_FILES['batchFile']['error'] === 0) {
    $lines = file($_FILES['batchFile']['tmp_name']);
    foreach ($lines as $line) {
        if (trim($line)) $urls[] = trim($line);
    }
} elseif (!empty($url)) {
    $urls[] = $url;
}

if (empty($urls) || empty($savePath)) {
    logLine("ERROR: Invalid input or missing save path.");
    exit;
}

if (!is_dir($savePath)) {
    mkdir($savePath, 0755, true);
}

// Base flags
 $baseCmd = "yt-dlp --newline --no-warnings --ignore-errors --fragment-retries 10 --hls-prefer-native";

foreach ($urls as $targetUrl) {
    $domain = parse_url($targetUrl, PHP_URL_HOST);
    $ref = $domain ? "https://" . $domain . "/" : "https://www.youtube.com/";
    
    // Secure path handling
    $safePath = escapeshellarg(rtrim($savePath, '/\\'));
    $safeUrl = escapeshellarg($targetUrl);
    $safeRef = escapeshellarg($ref);
    
    $outTemplate = $safePath . '/%(title)s.%(ext)s';
    
    // Build command securely
    $cmd = $baseCmd . " --user-agent " . escapeshellarg($ua) . " --referer " . $safeRef . " -o " . $outTemplate;

    if ($dlVideo === "1") {
        // Secure format string construction
        $fmt = "bestvideo[height<={$res}]+bestaudio/best[height<={$res}]";
        $cmd .= " -f " . escapeshellarg($fmt) . " --merge-output-format mp4";
    }

    if ($dlAudio === "1") {
        $cmd .= " -x --audio-format mp3";
    }

    $cmd .= " " . $safeUrl . " 2>&1";

    logLine("INFO: Processing " . parse_url($targetUrl, PHP_URL_HOST));
    passthru($cmd, $exitCode);
    
    if ($exitCode !== 0) {
        logLine("WARN: Process exited with code " . $exitCode);
    } else {
        logLine("INFO: Item completed.");
    }
}

logLine("SYSTEM: All queued tasks complete.");
?>