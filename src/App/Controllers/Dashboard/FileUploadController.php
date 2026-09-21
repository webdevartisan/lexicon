<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Services\UploadService;
use Framework\Core\Response;

/**
 * Receives Dropzone uploads into the uploader's temp folder until the form they
 * belong to is saved.
 */
class FileUploadController extends AppController
{
    public function __construct(
        private UploadService $uploadService
    ) {}

    /**
     * Store one Dropzone file and return its temp filename.
     */
    public function upload(): Response
    {
        $file = $this->request->files['file'] ?? null;
        if (!is_array($file)) {
            return $this->jsonError('No file was received.', 400);
        }

        $userId = (int) auth()->user()['id'];

        try {
            return $this->jsonSuccess($this->uploadService->storeTempImage($file, $userId));
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), 422);
        } catch (\Throwable $e) {
            error_log("Temp upload failed for user {$userId}: ".$e->getMessage());

            return $this->jsonError('The server could not store the image.', 500);
        }
    }
}
