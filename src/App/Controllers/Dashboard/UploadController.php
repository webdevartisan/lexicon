<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Services\UploadService;
use Framework\Core\Response;

class UploadController extends AppController
{
    public function __construct(
        private UploadService $uploader
    ) {}

    public function tinymceImage(): Response
    {
        $user = auth()->user();
        $userId = (int) $user['id'];

        $blogId = (int) ($this->request->post['blog_id'] ?? 0);
        if ($blogId <= 0) {
            return $this->json(['error' => 'Missing blog_id'], 400);
        }

        // TinyMCE sends the file field named "image" from images_upload_handler
        $file = $this->request->files['image'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return $this->json(['error' => 'No image uploaded'], 400);
        }

        // Build dir + base URL using your existing helper
        [$dir, $baseUrl] = $this->uploader->userBlogPostPath($userId, $blogId);

        try {
            $url = $this->uploader->storeImage($file, [
                'dir' => $dir,
                'base_url' => $baseUrl,
                'allowed_ext' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
                'max_bytes' => 2 * 1024 * 1024,
                'rename' => 'post-image', // base part; UploadService will add hash
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Upload failed'], 500);
        }

        if (!$url) {
            return $this->json(['error' => 'Upload failed'], 500);
        }

        // TinyMCE expects { location: "<url>" }
        return $this->json(['location' => $url], 200);
    }
}
