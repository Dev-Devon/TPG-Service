<?php
header("Content-Type: text/plain");

$githubUser="Dev-Devon";
$repo="TPG-Service";

$currentVersionFile="version.txt";

/* ---------- get current version ---------- */

if(file_exists($currentVersionFile)){
$currentVersion=trim(file_get_contents($currentVersionFile));
}else{
$currentVersion="none";
}

echo "Current version: $currentVersion\n";

/* ---------- check latest release ---------- */

$api="https://api.github.com/repos/$githubUser/$repo/releases/latest";

$options=[
"http"=>[
"header"=>"User-Agent: TPG-Updater"
]
];

$context=stream_context_create($options);
$json=file_get_contents($api,false,$context);

if(!$json){
die("Failed to contact GitHub\n");
}

$data=json_decode($json,true);

$latest=$data["tag_name"];

echo "Latest version: $latest\n";

/* ---------- compare versions ---------- */

if($latest==$currentVersion){

echo "\nSystem already up to date.\n";
exit;

}

echo "\nUpdate required.\n";

/* ---------- download zip ---------- */

$zipUrl=$data["zipball_url"];

echo "Downloading update...\n";

$zipData=file_get_contents($zipUrl,false,$context);

file_put_contents("update.zip",$zipData);

/* ---------- extract ---------- */

$zip=new ZipArchive;

if($zip->open("update.zip")===TRUE){

$zip->extractTo("update_temp");

$zip->close();

}else{
die("Extraction failed\n");
}

echo "Extracted.\n";

/* ---------- find extracted folder ---------- */

$dirs=scandir("update_temp");

foreach($dirs as $d){

if($d!="." && $d!=".."){
$source="update_temp/".$d."/";
break;
}

}

/* ---------- copy files ---------- */

$iterator=new RecursiveIteratorIterator(
new RecursiveDirectoryIterator($source,RecursiveDirectoryIterator::SKIP_DOTS),
RecursiveIteratorIterator::SELF_FIRST
);

foreach($iterator as $file){

$rel=str_replace($source,"",$file->getPathname());

$target="./".$rel;

/* skip git files */

if(strpos($rel,".git")===0) continue;

if($file->isDir()){

if(!is_dir($target)){
mkdir($target,0777,true);
}

}else{

copy($file->getPathname(),$target);

}

}

echo "Files updated.\n";

/* ---------- save version ---------- */

file_put_contents($currentVersionFile,$latest);

/* ---------- cleanup ---------- */

function del($dir){

foreach(scandir($dir) as $f){

if($f=="."||$f=="..") continue;

$p="$dir/$f";

if(is_dir($p)) del($p);
else unlink($p);

}

rmdir($dir);

}

del("update_temp");

unlink("update.zip");

echo "Update complete. Now running version $latest\n";
?>