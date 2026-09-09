<?php

namespace App\src\controller;

use JetBrains\PhpStorm\NoReturn;

/**
 * file handler.
 * currently only uploader
 */
class App
{
    private array $data = [];

    private string $imageData;

    private string $publicURL;

    /**
     * @return void
     */
    public function upload(): void
    {
        $this->setCorsHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            $this->sendError(405, 'Request method not allowed');

        // Branch on the request content type:
        //  - application/json  -> legacy base64-in-JSON path (buffered in memory)
        //  - anything else     -> raw binary body streamed straight to disk
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') === 0)
            $this->handleJsonRequest();
        else
            $this->handleStreamRequest();

        $this->sendResponse();
    }

    /**
     * @return void
     */
    private function setCorsHeaders(): void
    {
        // Set headers to allow cross-origin requests (CORS)
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: POST");
        header("Access-Control-Allow-Headers: Content-Type, X-Api-Key");
        header('Content-Type: application/json');
    }

    /**
     * Legacy path: JSON body carrying a base64-encoded image.
     * The whole payload is buffered in memory.
     * @return void
     */
    private function handleJsonRequest(): void
    {
        //decode JSON to array and store it into the private variable
        $this->setData(file_get_contents("php://input"));

        $this->validateRequest();

        // Decode the base64 image
        $this->getBase64ImageData();

        // get path and filename
        $filePath = $this->getFilePath();
        $filename = $this->getFilename();
        $fullPath = $filePath.$filename;
        // Save the file
        if (file_put_contents($_SERVER['DOCUMENT_ROOT'].'/'.$fullPath, $this->imageData) === false)
            $this->sendError(500, 'Cannot write to file');

        $this->publicURL = $this->buildPublicURL($filePath, $filename);
    }

    /**
     * Streaming path: raw binary request body copied to disk in fixed-size
     * chunks, so peak memory stays flat regardless of file size.
     *
     * Metadata that used to live in the JSON body now travels out-of-band:
     *  - API key via the X-Api-Key header
     *  - optional path hint via the ?path=custom query parameter
     * @return void
     */
    private function handleStreamRequest(): void
    {
        // Authenticate before reading the body.
        $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($key !== API_KEY)
            $this->sendError(400, 'Invalid API key');

        // Path hint is the only piece of "data" the stream path understands.
        $this->data['path'] = (($_GET['path'] ?? '') === 'custom') ? 'custom' : null;

        $filePath = $this->getFilePath();
        $root = $_SERVER['DOCUMENT_ROOT'].'/';

        // Stream to a temp file first; rename to the final name once we know
        // its mime type (and therefore extension) and that it is valid.
        $tmpPath = $root.$filePath.uniqid('tmp_', true).'.part';

        $in = fopen('php://input', 'rb');
        if ($in === false)
            $this->sendError(400, 'Cannot read input stream');

        $out = fopen($tmpPath, 'wb');
        if ($out === false) {
            fclose($in);
            $this->sendError(500, 'Cannot write to file');
        }

        $written = $this->copyStream($in, $out, $tmpPath);
        fclose($in);
        fclose($out);

        if ($written === 0) {
            @unlink($tmpPath);
            $this->sendError(400, 'Missing image');
        }

        // Detect the mime type from the file on disk, not from memory.
        $mimeType = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmpPath);
        if (!array_key_exists($mimeType, ALLOWED_FILE_EXT)) {
            @unlink($tmpPath);
            $this->sendError(400, 'Invalid mime type');
        }

        $filename = uniqid().'.'.ALLOWED_FILE_EXT[$mimeType];
        if (!rename($tmpPath, $root.$filePath.$filename)) {
            @unlink($tmpPath);
            $this->sendError(500, 'Cannot write to file');
        }

        $this->publicURL = $this->buildPublicURL($filePath, $filename);
    }

    /**
     * Copy an input stream to an output stream in fixed-size chunks,
     * enforcing MAX_FILE_SIZE as we go. On any failure the partial temp
     * file is removed and an error response is sent.
     *
     * @param resource $in
     * @param resource $out
     * @param string $tmpPath temp file to clean up on failure
     * @return int bytes written
     */
    private function copyStream($in, $out, string $tmpPath): int
    {
        $written = 0;
        while (!feof($in)) {
            $chunk = fread($in, 8192);
            if ($chunk === false)
                break;

            $written += strlen($chunk);
            if ($written > MAX_FILE_SIZE) {
                fclose($in);
                fclose($out);
                @unlink($tmpPath);
                $this->sendError(400,
                    'Max file size of '.MAX_FILE_SIZE.' bytes exceeded');
            }

            if (fwrite($out, $chunk) === false) {
                fclose($in);
                fclose($out);
                @unlink($tmpPath);
                $this->sendError(500, 'Cannot write to file');
            }
        }

        return $written;
    }

    /**
     * @param string $filePath
     * @param string $filename
     * @return string
     */
    private function buildPublicURL(string $filePath, string $filename): string
    {
        $serverHost = $_SERVER['HTTP_HOST'];
        // Check if HTTPS is set and not empty in the $_SERVER array
        $protocol = !empty($_SERVER['HTTPS']) ? 'https' : 'http';

        return "$protocol://$serverHost/$filePath$filename";
    }

    /**
     * @return void
     */
    private function validateRequest(): void
    {
        //check if the API key is valid
        if (!isset($this->data['key']))
            $this->sendError(400, 'API key not provided');
        $key = $this->data['key'];
        if ($key !== API_KEY)
            $this->sendError(400, 'Invalid API key');

        // Check if the image data and filename are present
        if (!isset($this->data['image']))
            $this->sendError(400, 'Missing image');
    }

    /**
     * @return void
     */
    private function sendResponse(): void
    {
        //send json response
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Image saved successfully',
            'url' => $this->publicURL
        ]);
        exit();
    }

    /**
     * @param int $code
     * @param string $message
     * @return void
     */
    #[NoReturn] private function sendError(int $code, string $message): void
    {
        http_response_code($code);
        echo json_encode([
            'status' => $code,
            'message' => $message]);
        exit();
    }

    /**
     * @param $postData
     * @return void
     */
    private function setData($postData): void
    {
        $data = json_decode($postData, true);

        if (strlen($data['image']) > MAX_FILE_SIZE)
            $this->sendError(400,
                'Max file size of '.MAX_FILE_SIZE.' bytes exceeded');

        if (json_last_error() !== JSON_ERROR_NONE)
            $this->sendError(400, 'Invalid JSON');

        $this->data = $data;
    }

    /**
     * ALLOWED_FILE_EXT array
     * generates a unique filename, keeps the extension
     *
     * @return string
     */
    private function getFilename(): string
    {

        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($fileInfo, $this->imageData);
        finfo_close($fileInfo);

        if (!array_key_exists($mimeType, ALLOWED_FILE_EXT))
            $this->sendError(400, 'Invalid mime type');

        // Get the file extension
        $fileExtension = ALLOWED_FILE_EXT[$mimeType];

        return uniqid().'.'.$fileExtension;
    }

    /**
     * @return string
     */
    private function getFilePath(): string
    {
        // Define the storage path
        if (isset($this->data['path']) && $this->data['path'] === 'custom')
            $filePath = UPLOAD_ROOT_DIR.'/custom/';
        else $filePath = UPLOAD_ROOT_DIR.'/'.date('Ymd/');

        $root = $_SERVER['DOCUMENT_ROOT'].'/';
        if (!file_exists($root.$filePath))
        {
            $dirCreated = mkdir($root.$filePath, 0755, true);
            if (!$dirCreated)
                $this->sendError(500, 'Unable to create directory');
        }

        return $filePath;
    }

    /**
     * @return void
     */
    private function getBase64ImageData(): void
    {
        $base64Image = $this->data['image'];
        // Decode the base64 image
        $this->imageData = base64_decode($base64Image);
        if ($this->imageData === false)
            $this->sendError(400, 'Invalid base64 image');
    }

}
