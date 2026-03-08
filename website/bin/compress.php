<?php
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);
ignore_user_abort(true);
error_reporting(E_ALL);
ini_set('display_errors',1);

/* ----------------------------------------------------------
   1. SETUP & PATHS
   ---------------------------------------------------------- */

 $homeDir = getenv('USERPROFILE') ?: getenv('HOME');
 $saveFolder = $homeDir . DIRECTORY_SEPARATOR . 'Videos' . DIRECTORY_SEPARATOR . 'Compress' . DIRECTORY_SEPARATOR;

if(!is_dir($saveFolder)){
    if(!mkdir($saveFolder,0777,true)){
        die("ERROR Cannot create output folder: $saveFolder");
    }
}

 $ffmpeg = __DIR__ . DIRECTORY_SEPARATOR . 'ffmpeg' . (strtoupper(substr(PHP_OS,0,3))==='WIN' ? '.exe' : '');
 $ffprobe = __DIR__ . DIRECTORY_SEPARATOR . 'ffprobe' . (strtoupper(substr(PHP_OS,0,3))==='WIN' ? '.exe' : '');

if(!file_exists($ffmpeg)) die("ERROR ffmpeg binary missing.");
if(!file_exists($ffprobe)) die("ERROR ffprobe binary missing.");

/* ----------------------------------------------------------
   2. VALIDATE UPLOAD
   ---------------------------------------------------------- */

if(empty($_FILES['video']) || $_FILES['video']['error']!==UPLOAD_ERR_OK){
    $errCode=$_FILES['video']['error']??0;
    $errMsg="Unknown upload error";
    if($errCode===1||$errCode===2) $errMsg="File too large (php.ini limit)";
    if($errCode===4) $errMsg="No file uploaded";
    die("ERROR Upload failed: $errMsg");
}

 $tmp  = $_FILES['video']['tmp_name'];
 $orig = basename($_FILES['video']['name']);
 $crf  = (int)($_POST['crf'] ?? 28);
 $crf  = max(18,min(51,$crf));

 $filename = pathinfo($orig, PATHINFO_FILENAME) . '_compressed.mp4';
 $dest     = $saveFolder . $filename;

/* ----------------------------------------------------------
   3. ANALYZE INPUT
   ---------------------------------------------------------- */

 $cmd = escapeshellarg($ffprobe) . 
       " -v quiet -print_format json -show_streams -select_streams v:0 " . 
       escapeshellarg($tmp);

 $json = shell_exec($cmd);
 $info = json_decode($json, true);
 $stream = $info['streams'][0] ?? [];

 $duration = (float)($stream['duration'] ?? 0);
 $inputBitrate = (int)($stream['bit_rate'] ?? 0);
 $transfer = $stream['color_transfer'] ?? '';
 $pixFmt   = $stream['pix_fmt'] ?? '';

// DETECT HDR
 $isHDR = (
    strpos($transfer, 'smpte2084') !== false || 
    strpos($transfer, 'arib-std-b67') !== false ||
    strpos($pixFmt, 'p10') !== false
);

// SMART MODE SWITCHING
 $forceCRF = false;
if ($inputBitrate > 0 && $inputBitrate < 1500000) $forceCRF = true;
if ($duration > 0 && $duration < 10) $forceCRF = true;

 $size = filesize($tmp) / 1024 / 1024;
 $target = ($size * 8192) / max($duration, 1);
 $target = $target * 0.7;
 $target = min(8000, $target);
 $target = max(600, $target);
 $target = intval($target);

/* ----------------------------------------------------------
   4. PRE-CONVERT NON-MP4
   ---------------------------------------------------------- */

 $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
 $workInput = $tmp;
 $tmpMp4 = "";

if($ext !== "mp4"){
    $tmpMp4 = sys_get_temp_dir() . "/comp_tmp_" . uniqid() . ".mp4";
    $preArgs = "-c:v libx264 -preset fast -crf 18 -c:a aac -b:a 192k";
    if($isHDR) {
        $preArgs = "-c:v libx265 -preset fast -crf 20 -pix_fmt yuv420p10le -c:a aac -b:a 192k";
    }
    $cmd = escapeshellarg($ffmpeg) . " -y -i " . escapeshellarg($tmp) . " " . $preArgs . " " . escapeshellarg($tmpMp4);
    exec($cmd, $o, $r);
    if($r !== 0) die("ERROR Container conversion failed.");
    $workInput = $tmpMp4;
}

/* ----------------------------------------------------------
   5. GPU DETECTION (RUNTIME PROBE)
   ---------------------------------------------------------- */

// 1. HDR always forces CPU x265 10-bit (Safest for quality)
if($isHDR) {
    $chosenEncoder = "libx265_hdr";
} 
else {
    // 2. Try Hardware Encoders in order of preference
    // We run a tiny test encode to see if the GPU actually works.
    // This prevents the "Cannot load nvcuda.dll" crash.
    
    $candidates = [
        "hevc_nvenc",    // NVIDIA Newer
        "h264_nvenc",    // NVIDIA Older
        "hevc_amf",      // AMD
        "h264_amf",      // AMD Older
        "hevc_qsv",      // Intel
    ];
    
    $chosenEncoder = "libx265"; // Default fallback

    foreach($candidates as $encName) {
        // Test command: Encode 1 frame of black video using this encoder
        $testCmd = escapeshellarg($ffmpeg) . 
                   " -y -f lavfi -i color=black:s=256x256:d=0.04 -c:v " . $encName . 
                   " -f null - 2>&1";
        
        exec($testCmd, $testOut, $testRet);
        
        // If return code is 0, the encoder works!
        if($testRet === 0) {
            $chosenEncoder = $encName;
            break;
        }
    }
}

/* ----------------------------------------------------------
   6. BUILD OPTIONS BASED ON CHOSEN ENCODER
   ---------------------------------------------------------- */

 $videoOptions = "";
 $audioOptions = "-map 0:a? -c:a aac -b:a 128k -ac 2";
 $extra = "-movflags +faststart -map_metadata 0";

switch($chosenEncoder) {
    
    case "libx265_hdr":
        $videoOptions = 
            "-map 0:v:0 -c:v libx265 -preset slow -crf $crf " .
            "-pix_fmt yuv420p10le " .
            "-x265-params colorprim=bt2020:transfer=smpte2084:colormatrix=bt2020nc";
        break;

    case "hevc_nvenc":
    case "h264_nvenc":
        // NVIDIA Found
        if($forceCRF) {
             $videoOptions = "-map 0:v:0 -c:v $chosenEncoder -preset p5 -rc vbr -cq $crf -pix_fmt yuv420p";
        } else {
             $videoOptions = "-map 0:v:0 -c:v $chosenEncoder -preset p5 -rc vbr -cq $crf -b:v {$target}k -maxrate {$target}k -pix_fmt yuv420p";
        }
        break;

    case "hevc_amf":
    case "h264_amf":
        // AMD Found (Use CQP for quality based, or VBR for size)
        // AMF syntax is slightly different
        if($forceCRF) {
             // AMF uses qp_i/qb_i/qb_p roughly similar to CRF logic (lower is better)
             $qp = $crf; 
             $videoOptions = "-map 0:v:0 -c:v $chosenEncoder -rc cqp -qp_i $qp -qp_p $qp -qp_b $qp";
        } else {
             $videoOptions = "-map 0:v:0 -c:v $chosenEncoder -rc vbr_peak -b:v {$target}k -maxrate {$target}k";
        }
        break;

    case "hevc_qsv":
        // Intel Found
        $videoOptions = "-map 0:v:0 -c:v hevc_qsv -preset medium -global_quality $crf";
        break;

    default:
        // Fallback CPU
        $videoOptions = "-map 0:v:0 -c:v libx265 -preset slow -crf $crf -pix_fmt yuv420p";
        break;
}

/* ----------------------------------------------------------
   7. EXECUTE
   ---------------------------------------------------------- */

 $cmd = escapeshellarg($ffmpeg) . " -y -i " . escapeshellarg($workInput) . 
       " " . $videoOptions . " " . $audioOptions . " " . $extra . " " . 
       escapeshellarg($dest) . " 2>&1";

exec($cmd, $lines, $ret);

if($tmpMp4 && file_exists($tmpMp4)) unlink($tmpMp4);

if($ret!==0){
    die("ERROR ffmpeg failed (Code $ret):\n".implode("\n",$lines));
}

if(!file_exists($dest)){
    die("ERROR Output file missing.");
}

 $fsize = filesize($dest);

echo "SUCCESS\n";
echo "name=$filename\n";
echo "url=$dest\n";
echo "size=$fsize\n";
exit;
?>