<?php

namespace App\src\controller;

/**
 * file handler.
 * currently only uploader
 */
class App
{
    private array $data;

    private string $imageData;

    private string $publicURL;

    /**
     * @return void
     */
    public function upload(): void
    {
        $this->getRequest();
        $this->handleRequest();
        $this->sendResponse();
    }

    /**
     * set request object to $this->data array
     * @return void
     */
    private function getRequest (): void
    {

        // Set headers to allow cross-origin requests (CORS)
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: POST");
        header("Access-Control-Allow-Headers: Content-Type");
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            $this->sendError(405, 'Request method not allowed');
        //decode JSON to array and store it into the private variable
        $this->setData(file_get_contents("php://input"));
    }

    /**
     * @return void
     */
    private function handleRequest(): void
    {
        $this->validateRequest();

        // Decode the base64 image
        $this->getBase64ImageData();

        // get path and filename
        $filename = $this->getFilename();
        $filePath = $this->getFilePath();
        $fullPath = $filePath.$filename;
        // Save the file
        if (file_put_contents($_SERVER['DOCUMENT_ROOT'].'/'.$fullPath, $this->imageData) === false)
            $this->sendError(500, 'Cannot write to file');

        //$storagePath = $this->getStoragePath();
        $serverHost = $_SERVER['HTTP_HOST'];
        // Check if HTTPS is set and not empty in the $_SERVER array
        $protocol = !empty($_SERVER['HTTPS']) ? 'https' : 'http';

        $this->publicURL = "$protocol://$serverHost/$filePath$filename";
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
    private function sendError(int $code, string $message): void
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

        if (!file_exists($filePath))
        {
            $dirCreated = mkdir($_SERVER['DOCUMENT_ROOT'].'/'. $filePath, 0755, true);
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