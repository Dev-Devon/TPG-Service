<?php
set_time_limit(0);
ignore_user_abort(true);
while (ob_get_level()) { ob_end_clean(); }
header("Content-Type: text/plain; charset=utf-8");
header("Cache-Control: no-cache");
header('X-Accel-Buffering: no');
ob_implicit_flush(true);

function logMsg($msg) {
    echo "[" . date("H:i:s") . "] " . $msg . "\n";
    flush();
}

$isWin = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
$ffmpegName = $isWin ? 'ffmpeg.exe' : 'ffmpeg';
$searchPaths = [
    __DIR__ . '/bin/' . $ffmpegName,
    __DIR__ . '/../bin/' . $ffmpegName
];

$ffmpeg = null;
foreach ($searchPaths as $p) {
    if (is_file($p) && is_executable($p)) {
        $ffmpeg = $p;
        break;
    }
}

if (!$ffmpeg) {
    $sysPath = shell_exec($isWin ? "where ffmpeg" : "which ffmpeg 2>/dev/null");
    if ($sysPath) $ffmpeg = trim($sysPath);
}

if (!$ffmpeg) {
    logMsg("CRITICAL: FFmpeg binary missing.");
    exit;
}

$tempDir = rtrim(__DIR__ . '/temp_eq', '/\\') . DIRECTORY_SEPARATOR;
if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);

$outputDir = isset($_POST['outputPath']) ? $_POST['outputPath'] : (getenv('USERPROFILE') ?: getenv('HOME') . '/Videos/EQ');
if (!is_dir($outputDir)) mkdir($outputDir, 0777, true);

$realOutputDir = realpath($outputDir);
if (!$realOutputDir || !is_dir($realOutputDir)) {
    logMsg("ERROR: Invalid output path.");
    exit;
}

$gains = (array)json_decode($_POST['gains'], true);
$preAmp = floatval($_POST['preAmp'] ?? 1);
$preAmp = max(-30, min($preAmp, 30));

$bands = [31, 62, 125, 250, 500, 1000, 2000, 4000, 8000, 16000, 20000];
$eqParts = [];
foreach ($bands as $i => $freq) {
    $g = floatval($gains[$i] ?? 0);
    $eqParts[] = "equalizer=f=$freq:width_type=q:w=1:g=$g";
}

$vol = pow(10, $preAmp / 20);
$finalFilter = "volume=$vol," . implode(',', $eqParts);

function processFile($input, $outDir, $bin, $filter, $tmp) {
    if (!file_exists($input)) {
        logMsg("WARN: File not found: " . basename($input));
        return;
    }

    $info = pathinfo($input);
    $tmpWav = $tmp . uniqid('proc_', true) . '.wav';
    $outName = $info['filename'] . '_eq_' . time() . '.mp3';
    $finalPath = $outDir . $outName;

    $start = microtime(true);

    logMsg("INFO: Decoding " . $info['basename']);
    
    $cmd1 = escapeshellcmd($bin) . " -y -i " . escapeshellarg($input) . " " . escapeshellarg($tmpWav) . " 2>&1";
    @exec($cmd1, $out1, $ret1);

    if (!file_exists($tmpWav) || filesize($tmpWav) === 0) {
        logMsg("ERROR: Decoding failed.");
        return;
    }

    logMsg("INFO: Encoding MP3 (320k)");
    $cmd2 = escapeshellcmd($bin) . " -y -i " . escapeshellarg($tmpWav) .
             " -af " . escapeshellarg($filter) .
             " -c:a libmp3lame -b:a 320k " .
             escapeshellarg($finalPath) . " 2>&1";

    passthru($cmd2, $ret2);

    $elapsed = round(microtime(true) - $start, 2);

    if (file_exists($finalPath) && filesize($finalPath) > 0) {
        logMsg("DONE: " . $outName . " (" . $elapsed . "s)");
    } else {
        logMsg("ERROR: Encoding failed.");
    }

    if (file_exists($tmpWav)) @unlink($tmpWav);
}

$action = $_POST['action'] ?? '';

if ($action === 'single' && isset($_FILES['audioFile'])) {
    $f = $_FILES['audioFile'];
    if ($f['error'] === 0) {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($f['name']));
        $src = $tempDir . $safeName;
        if (move_uploaded_file($f['tmp_name'], $src)) {
            processFile($src, $realOutputDir, $ffmpeg, $finalFilter, $tempDir);
            @unlink($src);
        }
    }
}
elseif ($action === 'batch' && isset($_FILES['batchFiles'])) {
    $files = $_FILES['batchFiles'];
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === 0) {
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $files['name'][$i]);
            $src = $tempDir . $safeName;
            if (move_uploaded_file($files['tmp_name'][$i], $src)) {
                processFile($src, $realOutputDir, $ffmpeg, $finalFilter, $tempDir);
                @unlink($src);
            }
        }
    }
}
elseif (isset($_POST['pathList'])) {
    $listFile = $tempDir . "queue_" . uniqid() . ".txt";
    file_put_contents($listFile, $_POST['pathList']);
    $h = fopen($listFile, "r");
    if ($h) {
        while (($line = fgets($h)) !== false) {
            $line = trim($line);
            if ($line === "-1" || empty($line)) break;
            processFile($line, $realOutputDir, $ffmpeg, $finalFilter, $tempDir);
        }
        fclose($h);
    }
    @unlink($listFile);
}

logMsg("INFO: Batch complete.");
?>