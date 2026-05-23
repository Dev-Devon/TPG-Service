<?php
header("Content-Type: text/plain");

 $user = "Dev-Devon";
 $repo = "TPG-Service";
 $verFile = "version.txt";

// figure out where we are
 $curr = file_exists($verFile) ? trim(file_get_contents($verFile)) : "none";
echo "Current: $curr\n";

// hit github api
 $ctx = stream_context_create(["http" => ["header" => "User-Agent: TPG-Updater"]]);
 $json = @file_get_contents("https://api.github.com/repos/$user/$repo/releases/latest", false, $ctx);

if (!$json) die("Error contacting GitHub\n");

 $data = json_decode($json, true);
 $latest = $data["tag_name"];
echo "Latest: $latest\n";

if ($latest == $curr) {
    echo "Already up to date.\n";
    exit;
}

echo "Updating...\n";

// grab the zip
file_put_contents("update.zip", file_get_contents($data["zipball_url"], false, $ctx));

// unzip it
 $z = new ZipArchive;
if ($z->open("update.zip") === TRUE) {
    $z->extractTo("update_temp");
    $z->close();
} else {
    die("Failed to unzip\n");
}

// find the folder github created (it's usually named hash-user-repo)
 $files = scandir("update_temp");
 $src = "";
foreach ($files as $f) {
    if ($f != '.' && $f != '..') {
        $src = "update_temp/" . $f . "/";
        break;
    }
}

// recursive copy helper
function copyDir($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                copyDir($src . '/' . $file, $dst . '/' . $file);
            } else {
                // skip git stuff just in case
                if (strpos($file, '.git') === false) {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
    }
    closedir($dir);
}

// do the copy
copyDir($src, '.');

// update local version file
file_put_contents($verFile, $latest);

// cleanup helper
function rrmdir($dir) {
    foreach (glob($dir . '/*') as $file) {
        if (is_dir($file)) rrmdir($file);
        else unlink($file);
    }
    rmdir($dir);
}

rrmdir("update_temp");
unlink("update.zip");

echo "Done. Now on $latest\n";
?>