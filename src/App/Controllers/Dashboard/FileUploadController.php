<?php

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Services\UploadService;

class FileUploadController extends AppController
{
    public function __construct(
        private UploadService $uploadService
    ) {}

    /**
     * Handle Dropzone AJAX upload
     */
    public function upload()
    {
        // Dropzone sends file with name 'file' by default
        if (!isset($_FILES['file'])) {
            return $this->jsonError('No file uploaded', 400);
        }

        $file = $_FILES['file'];
        $userId = auth()->user()['id']; // or however you get user ID

        try {
            $result = $this->uploadService->storeTempImage($file, $userId);

            // Dropzone expects success response
            return $this->jsonSuccess($result);

        } catch (\InvalidArgumentException $e) {
            // Dropzone shows this message to user
            return $this->jsonError($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->jsonError('Upload failed. Please try again.', 500);
        }
    }
}
