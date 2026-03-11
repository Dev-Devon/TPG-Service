<?php
header('Content-Type: application/json');

// Local version file
$localVersionFile = __DIR__ . '/version.txt';
$localVersion = '';
if (file_exists($localVersionFile)) {
    $lines = file($localVersionFile, FILE_IGNORE_NEW_LINES);
    $localVersion = trim($lines[0]);
}

// GitHub version URL
$githubUser = 'Dev-Devon';
$repoName = 'TPG-Service';
$versionUrl = "https://raw.githubusercontent.com/$githubUser/$repoName/main/version.txt";

// Get GitHub version
$remoteVersion = @file($versionUrl, FILE_IGNORE_NEW_LINES);
if (!$remoteVersion) {
    echo json_encode(['error' => 'Unable to check remote version.']);
    exit;
}
$remoteVersion = trim($remoteVersion[0]);

$updateAvailable = version_compare($remoteVersion, $localVersion, '>');

// Update not.txt accordingly
$notFile = __DIR__ . '/not.txt';
if ($updateAvailable) {
    file_put_contents($notFile, '1');
} else {
    file_put_contents($notFile, '0');
}

echo json_encode([
    'local' => $localVersion,
    'remote' => $remoteVersion,
    'update' => $updateAvailable
]);