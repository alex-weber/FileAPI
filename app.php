<?php

namespace App;

/**
 * file handler.
 * currently only uploader
 */
class app
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
     * set request object to $this->data
     * @return void
     */
    private function getRequest (): void
    {
        // Set headers to allow cross-origin requests (CORS)
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: POST");
        header("Access-Control-Allow-Headers: Content-Type");
        header('Content-Type: application/json');
        $this->setData(file_get_contents("php://input"));
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
     * @return void
     */
    private function handleRequest(): void
    {
        $this->validateRequest();

        // Decode the base64 image
        $this->getBase64ImageData();

        // get filename
        $filename = $this->getFilename();

        // Save the image
        $filePath = $this->getFilePath();
        $fullPath = $filePath . $filename;

        if (file_put_contents($fullPath, $this->imageData) === false)
            $this->sendError(500, 'Cannot write to file');

        $storagePath = $this->getStoragePath();
        $serverHost = $_SERVER['HTTP_HOST'];

        $this->publicURL = "https://$serverHost/$storagePath$filename";
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
     * @return string
     */
    private function getFilename(): string
    {

        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($fileInfo, $this->imageData);
        finfo_close($fileInfo);
        $mimeTypesToExtensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];

        if (!array_key_exists($mimeType, $mimeTypesToExtensions))
            $this->sendError(400, 'Invalid mime type');

        // Get the file extension
        $fileExtension = $mimeTypesToExtensions[$mimeType];

        return uniqid().'.'.$fileExtension;
    }

    /**
     * @return string
     */
    private function getStoragePath(): string
    {
        // Define the storage path
        if (isset($this->data['path']) && $this->data['path'] === 'custom')
            $storagePath = 'uploads/custom/';
        else $storagePath = 'uploads/'.date('Ymd/');

        return $storagePath;
    }

    /**
     * @return string
     */
    private function getFilePath(): string
    {
        $storagePath = $this->getStoragePath();

        if (!file_exists($storagePath))
        {
            mkdir($storagePath, 0755, true);
        }

        return $storagePath;
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
        {
            $this->sendError(400, 'Invalid base64 image');
        }
    }

    /**
     * @return void
     */
    private function validateRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            $this->sendError(405, 'Request method not allowed');
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


}