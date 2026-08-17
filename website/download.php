<?php
// Disable output buffering
while (ob_get_level()) { ob_end_clean(); }
header("Content-Type: text/plain");
header("Cache-Control: no-cache");
header("X-Accel-Buffering: no");
ob_implicit_flush(true);

ignore_user_abort(true);
set_time_limit(0);

function logLine($msg) {
    echo "[" . date("H:i:s") . "] " . $msg . "\n";
    flush();
}

// =========================================================================
// CORRECTED IMPERSONATE TARGET (matches your actual available list)
// =========================================================================
// Chrome-131 on Macos-14 is the newest Chrome available
// TLS fingerprint matters 100x more than OS fingerprint for YouTube
 $impersonateTarget = "Chrome-131";

// Fallback if needed (Windows-10 match, older TLS)
 $fallbackTarget = "Chrome-116";

// Chrome UA that matches Chrome-131
 $chromeUA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36";

logLine("INFO: Impersonate target set to: " . $impersonateTarget);

// =========================================================================
// INPUT HANDLING
// =========================================================================
 $savePath = $_POST['path'] ?? '';
 $url      = $_POST['url'] ?? '';
 $res      = intval($_POST['resolution'] ?? 1080); 
 $dlVideo  = $_POST['video'] ?? '0';
 $dlAudio  = $_POST['audio'] ?? '0';

 $urls = [];

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

// =========================================================================
// BASE COMMAND
// =========================================================================
 $baseCmd = "yt-dlp --newline --no-warnings --ignore-errors --fragment-retries 20 --retries 10 --socket-timeout 60 --force-ipv4 --hls-prefer-native";

foreach ($urls as $targetUrl) {
    $domain = parse_url($targetUrl, PHP_URL_HOST);
    $ref = $domain ? "https://" . $domain . "/" : "https://www.google.com/";
    
    $safePath = escapeshellarg(rtrim($savePath, '/\\'));
    $safeUrl  = escapeshellarg($targetUrl);
    $safeRef  = escapeshellarg($ref);
    $outTemplate = $safePath . '/%(title)s.%(ext)s';
    
    // Build command with CORRECT target format
    $cmd = $baseCmd . " --impersonate " . escapeshellarg($impersonateTarget);
    $cmd .= " --referer " . $safeRef . " -o " . $outTemplate;

    if ($dlVideo === "1") {
        // Added 'best[height<={$res}]' fallback for sites using muxed HLS streams
        $fmt = "bestvideo[height<={$res}]+bestaudio/best[height<={$res}]/best";
        $cmd .= " -f " . escapeshellarg($fmt) . " --merge-output-format mp4";
    }

    if ($dlAudio === "1") {
        $cmd .= " -f bestaudio --audio-quality 0 -x --audio-format mp3";
    }

    $cmd .= " " . $safeUrl . " 2>&1";

    logLine("INFO: Processing " . $domain . " [TLS: {$impersonateTarget}]");
    
    passthru($cmd, $exitCode);
    
    if ($exitCode !== 0) {
        logLine("WARN: Primary target failed (code " . $exitCode . "), trying fallback...");
        
        // Fallback to Chrome-116 Windows-10
        $cmd = $baseCmd . " --impersonate " . escapeshellarg($fallbackTarget);
        $cmd .= " --referer " . $safeRef . " -o " . $outTemplate;

        if ($dlVideo === "1") {
            // Added 'best[height<={$res}]' fallback for sites using muxed HLS streams
            $fmt = "bestvideo[height<={$res}]+bestaudio/best[height<={$res}]/best";
            $cmd .= " -f " . escapeshellarg($fmt) . " --merge-output-format mp4";
        }

        if ($dlAudio === "1") {
            $cmd .= " -f bestaudio --audio-quality 0 -x --audio-format mp3";
        }

        $cmd .= " " . $safeUrl . " 2>&1";
        
        logLine("INFO: Retrying with " . $fallbackTarget);
        passthru($cmd, $exitCode2);
        
        if ($exitCode2 !== 0) {
            logLine("WARN: Fallback also failed with code " . $exitCode2);
        } else {
            logLine("INFO: Item completed with fallback target.");
        }
    } else {
        logLine("INFO: Item completed.");
    }
}

logLine("SYSTEM: All queued tasks complete.");
?>