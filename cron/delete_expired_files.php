<?php

/**
 * @param $dir
 * @return void
 */
function deleteOldFiles($dir): void {
    $now = time();
    $ageInSeconds = 7 * 24 * 60 * 60; // approximate one week in seconds

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    $index = 0;
    foreach ($files as $fileInfo) {
        if (!$fileInfo->isFile()) continue;
        $filePath = $fileInfo->getRealPath();
        if (str_contains($filePath, 'custom')) continue;
        $fileAge = $now - $fileInfo->getCTime(); // get creation time of the file
        if ($fileAge > $ageInSeconds) {
            echo "$index. $filePath <br> \n";
            //unlink($filePath);
            $index++;
        }
    }
}

// start

$directory = 'uploads/';
deleteOldFiles($directory);
