<?php
// Simple test to check if PHP can access files here
$dir = __DIR__ . '/profiles/';
$files = scandir($dir);
echo "Files in profiles folder:<br>";
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        echo "- $file<br>";
        echo "Full path: " . $dir . $file . "<br>";
        echo "Exists: " . (file_exists($dir . $file) ? 'Yes' : 'No') . "<br>";
        echo "Readable: " . (is_readable($dir . $file) ? 'Yes' : 'No') . "<br>";
        echo "<img src='profiles/$file' style='max-width: 200px;'><br><br>";
    }
}
