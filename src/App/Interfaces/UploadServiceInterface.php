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
     * Delete a person's profile images and temp uploads.
     */
    public function deleteProfileUploads(int $userId): void;

    /**
     * Delete every file uploaded to a blog, whoever uploaded it.
     */
    public function deleteBlogUploads(int $blogId): void;

    /**
     * Every file a person uploaded to blogs, as public URLs grouped by blog.
     *
     * @return array<int, list<string>>
     */
    public function blogUploadsBy(int $userId): array;

    /**
     * Delete one stored upload by its public URL.
     */
    public function deleteUpload(string $url): void;

    /**
     * Remove a person's upload folder once nothing is left in it.
     */
    public function deleteEmptyUserFolder(int $userId): void;
}
