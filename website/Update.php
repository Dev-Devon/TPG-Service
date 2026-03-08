<?php
// TPG Tools Auto-Updater
header("Content-Type: text/plain");

// CONFIG: Replace with your actual GitHub info
$githubUser = 'YOUR_GITHUB_USERNAME';
$repoName = 'YOUR_REPO_NAME';
$zipUrl = "https://github.com/$githubUser/$repoName/archive/refs/heads/main.zip";

echo "Starting update process...\n";

// 1. Download
$content = @file_get_contents($zipUrl);
if (!$content) die("Error: Unable to connect to GitHub.");
file_put_contents('update.zip', $content);

// 2. Extract
$zip = new ZipArchive;
if ($zip->open('update.zip') === TRUE) {
    $zip->extractTo('./temp_update/');
    $zip->close();
    
    // 3. Move files (Assuming repository folder structure inside ZIP)
    $sourceDir = "./temp_update/$repoName-main/";
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($files as $fileinfo) {
        $target = './' . $fileinfo->getFilename(); // Simplified for root deployment
        if ($fileinfo->isDir()) {
            if (!is_dir($target)) mkdir($target);
        } else {
            rename($fileinfo->getRealPath(), $target);
        }
    }
    
    echo "Update Applied Successfully!\nRefresh your browser to apply changes.";
} else {
    echo "Error: Could not extract update files.";
}
?>