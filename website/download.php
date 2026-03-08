<?php
set_time_limit(0);
header("Content-Type: text/plain");
ob_implicit_flush(true);
ob_end_flush();

 $savePath = $_POST['path'] ?? '';
 $url = $_POST['url'] ?? '';
 $resolution = $_POST['resolution'] ?? '';
 $video = $_POST['video'] ?? '0';
 $audio = $_POST['audio'] ?? '0';

if (!$savePath) {
    echo "Invalid save path.\n";
    exit;
}

// Create directory if it doesn't exist
// This handles mixed slashes correctly on all OS
if (!is_dir($savePath)) {
    mkdir($savePath, 0777, true);
}

 $urls = [];

if (!empty($_FILES['batchFile']['tmp_name'])) {
    $file = file($_FILES['batchFile']['tmp_name']);
    foreach ($file as $line) {
        $line = trim($line);
        if (!empty($line)) $urls[] = $line;
    }
} elseif (!empty($url)) {
    $urls[] = $url;
}

if (empty($urls)) {
    echo "No URLs provided.\n";
    exit;
}

 $ds = DIRECTORY_SEPARATOR;

foreach ($urls as $u) {

    echo "Processing: $u\n";

    if ($video === "1") {

        // HLS SAFE FORMAT LOGIC
        if ($resolution == "1080") {
            $format = "best[height=1080]/best[height<=1080]";
        }
        elseif ($resolution == "360") {
            $format = "best[height=360]/best[height<=360]";
        }
        else {
            $format = "best[height<=$resolution]";
        }

        // Fix path for command line
        $cleanPath = rtrim($savePath, '/\\');
        $outputPath = $cleanPath . $ds . '%(title)s.%(ext)s';

        $cmd = "yt-dlp -f \"$format\" --merge-output-format mp4 "
             . "-o \"$outputPath\" \"$u\" 2>&1";

        passthru($cmd, $exitCode);

        if ($exitCode !== 0) {
            echo "Video download failed.\n";
        }
    }

    if ($audio === "1") {

        $cleanPath = rtrim($savePath, '/\\');
        $outputPath = $cleanPath . $ds . '%(title)s.%(ext)s';

        $cmd = "yt-dlp -x --audio-format mp3 "
             . "-o \"$outputPath\" \"$u\" 2>&1";

        passthru($cmd, $exitCode);

        if ($exitCode !== 0) {
            echo "Audio download failed.\n";
        }
    }

    echo "Finished: $u\n";
}

echo "All tasks done.\n";
?>