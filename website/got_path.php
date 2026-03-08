<?php
// Detect Home Directory (Works on Win/Mac/Linux)
 $home = getenv('USERPROFILE') ?: getenv('HOME');
 $ds = DIRECTORY_SEPARATOR;
// Return standard forward slashes for web compatibility
echo str_replace('\\', '/', $home . $ds . 'Videos' . $ds . 'EQ');
?>