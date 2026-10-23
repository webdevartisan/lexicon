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
     * Delete a person's avatar images and temp uploads.
     *
     * @param  list<string>  $avatarUrls  Public URLs of their avatar and its crop source
     */
    public function deleteProfileUploads(int $userId, array $avatarUrls): void;

    /**
     * Delete every file stored for a blog, whoever uploaded it.
     */
    public function deleteBlogUploads(string $mediaKey): void;

    /**
     * Delete one stored upload by its public URL.
     */
    public function deleteUpload(string $url): void;
}
