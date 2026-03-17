<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * UploadServiceInterface
 *
 * We define the contract for file upload operations.
 * Allows mocking in tests while keeping UploadService final for security.
 */
interface UploadServiceInterface
{
    /**
     * Delete all uploaded files for a user.
     *
     * @param int $userId User ID
     * @return void
     */
    public function deleteUserUploads(int $userId): void;
}
