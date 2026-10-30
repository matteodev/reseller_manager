<?php
$count = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__))) as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php' || strpos($file->getPathname(), '/.git/') !== false) continue;
    passthru('php -l '.escapeshellarg($file->getPathname()), $code);
    if ($code) exit($code);
    $count++;
}
echo "PASS: PHP syntax in $count files\n";
