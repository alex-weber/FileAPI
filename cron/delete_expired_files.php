<?php

require_once 'settings.php';

/**
 * @param $dir
 * @return void
 */
function deleteOldFiles($dir): void {

    $now = time();

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    $index = 0;
    foreach ($files as $fileInfo) {
        $filePath = $fileInfo->getRealPath();
        // Try to remove the directory if it is empty
        if ($fileInfo->isDir()) {
            @rmdir($filePath);
            continue;
        }
        if (!$fileInfo->isFile()) continue;
        //do not delete custom and old files
        if (str_contains($filePath, 'custom') ||
            str_contains($filePath, '202406')) continue;
        $fileAge = $now - $fileInfo->getCTime(); // get creation time of the file
        if ($fileAge > MAX_FILE_AGE) {
            $index++;
            echo "$index. $filePath \n";
            unlink($filePath);
        }

    }
    echo "$index files deleted. \n";
}

// start job
deleteOldFiles(UPLOAD_ROOT_DIR);
