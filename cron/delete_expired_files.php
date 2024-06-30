<?php

require_once 'settings.php';

/**
 * @param $dir
 * @return void
 */
function deleteOldFiles($dir): void {

    $now = time();
    $ageInSeconds = MAX_FILE_AGE;
    $day = 3600 * 24;

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    $index = 0;
    foreach ($files as $fileInfo) {
        if (!$fileInfo->isFile()) continue;
        $filePath = $fileInfo->getRealPath();
        //do not delete custom and old files
        if (str_contains($filePath, 'custom') ||
            str_contains($filePath, '202406')) continue;
        $fileAge = $now - $fileInfo->getCTime(); // get creation time of the file
        if ($fileAge > $ageInSeconds) {
            $index++;
            echo "$index. $filePath \n";
            unlink($filePath);
        }
    }
    echo "$index files deleted. \n";
}

// start job
deleteOldFiles(UPLOAD_ROOT_DIR);
