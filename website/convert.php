<?php
header("Content-Type: text/plain; charset=utf-8");
header('X-Accel-Buffering: no'); 
set_time_limit(0); 

// OS Detection for Binary Extension
 $isWin = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
 $ffmpegName = $isWin ? 'ffmpeg.exe' : 'ffmpeg';

// Path Finding Logic
 $ffmpeg = null;
 $searchPaths = [
    __DIR__ . DIRECTORY_SEPARATOR . "bin" . DIRECTORY_SEPARATOR . $ffmpegName,
    __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "bin" . DIRECTORY_SEPARATOR . $ffmpegName
];

foreach ($searchPaths as $path) {
    if (file_exists($path)) {
        $ffmpeg = realpath($path);
        break;
    }
}

if (!$ffmpeg) {
    $ffmpeg = shell_exec($isWin ? "where ffmpeg" : "which ffmpeg");
    $ffmpeg = $ffmpeg ? trim($ffmpeg) : null;
}

if (!$ffmpeg) {
    die("[FATAL ERROR] ffmpeg not found in bin folder or system PATH.");
}

// Temporary directory
 $tempDir = __DIR__ . DIRECTORY_SEPARATOR . "temp_eq" . DIRECTORY_SEPARATOR;
if (!is_dir($tempDir)) {
    @mkdir($tempDir, 0777, true);
}

// Get Output Path
 $outputDir = isset($_POST['outputPath']) ? $_POST['outputPath'] : '';
if (empty($outputDir)) {
    $userProfile = getenv('USERPROFILE') ?: getenv('HOME') ?: __DIR__;
    $outputDir = $userProfile . DIRECTORY_SEPARATOR . "Videos" . DIRECTORY_SEPARATOR . "EQ" . DIRECTORY_SEPARATOR;
}
 $outputDir = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR;
if (!is_dir($outputDir)) {
    @mkdir($outputDir, 0777, true);
}

// Prepare FFmpeg Filter
 $gains = json_decode($_POST['gains']);
 $preAmp = floatval($_POST['preAmp'] ?? 1); 
 $preAmp = min($preAmp, 2); 

 $bands = [31, 62, 125, 250, 500, 1000, 2000, 4000, 8000, 16000];
 $filterParts = [];
foreach ($bands as $i => $freq) {
    $g = $gains[$i] ?? 0;
    $filterParts[] = "equalizer=f=$freq:width_type=q:w=1:g=$g";
}

 $volFactor = pow(10, $preAmp / 20);
 $finalFilter = implode(',', $filterParts) . ",volume=$volFactor,dynaudnorm=f=200,alimiter=limit=0.98";

// Function to process a single file
function processFile($inputPath, $outputDir, $ffmpeg, $finalFilter, $tempDir) {
    if (!file_exists($inputPath)) {
        echo "[NOT FOUND] Skipping: $inputPath\n\n";
        return;
    }

    $info = pathinfo($inputPath);
    $timestamp = date("His");
    $tempWav = $tempDir . "temp_clean_" . $timestamp . ".wav";
    $outputPath = $outputDir . $info['basename'];

    echo "[STEP 1/2] Decoding & Cleaning: " . $info['basename'] . "...\n";
    $cmd1 = "\"$ffmpeg\" -y -i " . escapeshellarg($inputPath) . " " . escapeshellarg($tempWav) . " 2>&1";
    exec($cmd1);

    if (!file_exists($tempWav)) {
        echo "[FAILED] Could not decode file.\n\n";
        return;
    }

    echo "[STEP 2/2] Applying EQ & Encoding 320k MP3...\n";
    $cmd2 = "\"$ffmpeg\" -y -i " . escapeshellarg($tempWav) . 
           " -af " . escapeshellarg($finalFilter) . 
           " -c:a libmp3lame -b:a 320k " . 
           escapeshellarg($outputPath) . " 2>&1";
    
    exec($cmd2, $shellOutput, $status);
    if ($status === 0 && file_exists($outputPath)) {
        echo "[SUCCESS] Marshall EQ applied successfully.\n[OUTPUT] Saved to: $outputPath\n\n";
    } else {
        echo "[FAILED] Encoding error: ". end($shellOutput) . "\n\n";
    }

    if (file_exists($tempWav)) @unlink($tempWav);
    unset($shellOutput);
    @ob_flush(); flush();
}

// --- LOGIC HANDLER ---

 $action = isset($_POST['action']) ? $_POST['action'] : '';

// CASE 1: Single File Upload
if ($action === 'single' && isset($_FILES['audioFile'])) {
    $file = $_FILES['audioFile'];
    if ($file['error'] === 0) {
        $targetPath = $tempDir . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            echo "[UPLOAD] File received: " . $file['name'] . "\n";
            processFile($targetPath, $outputDir, $ffmpeg, $finalFilter, $tempDir);
            @unlink($targetPath); // Clean up uploaded source
        } else {
            echo "[ERROR] Could not move uploaded file to temp.\n";
        }
    } else {
        echo "[ERROR] Upload error code: " . $file['error'] . "\n";
    }
}
// CASE 2: Batch Upload (Browser Mode)
elseif ($action === 'batch' && isset($_FILES['batchFiles'])) {
    echo "[INFO] Processing batch upload...\n";
    foreach ($_FILES['batchFiles']['name'] as $i => $name) {
        if ($_FILES['batchFiles']['error'][$i] === 0) {
            $targetPath = $tempDir . basename($name);
            if (move_uploaded_file($_FILES['batchFiles']['tmp_name'][$i], $targetPath)) {
                echo "[UPLOAD] Received: $name\n";
                processFile($targetPath, $outputDir, $ffmpeg, $finalFilter, $tempDir);
                @unlink($targetPath);
            }
        }
    }
}
// CASE 3: Path List (Electron/Local Server Mode)
elseif (isset($_POST['pathList'])) {
    $queueFile = $tempDir . "queue.txt";
    file_put_contents($queueFile, $_POST['pathList']);
    $handle = fopen($queueFile, "r");
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $inputPath = trim($line);
            if ($inputPath === "-1" || empty($inputPath)) break;
            processFile($inputPath, $outputDir, $ffmpeg, $finalFilter, $tempDir);
        }
        fclose($handle);
    }
    @unlink($queueFile);
}

echo "--- BATCH COMPLETED ---";
?>