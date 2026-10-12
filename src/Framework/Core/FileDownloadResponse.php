<?php

declare(strict_types=1);

namespace Framework\Core;

use RuntimeException;

/**
 * A response whose body is a file on disk, streamed rather than held in memory.
 *
 * Response keeps its body as a string, which for a large archive would mean the
 * whole file in memory at once. This streams it in chunks instead, and can delete
 * the file afterwards for downloads built in a temp folder.
 */
class FileDownloadResponse extends Response
{
    private const CHUNK_BYTES = 1024 * 1024;

    /**
     * @param  string  $path  File to send
     * @param  string  $downloadName  Name the browser saves it under
     * @param  string  $contentType  MIME type of the file
     * @param  bool  $deleteAfterSend  Remove the file once it has been sent
     */
    public function __construct(
        private string $path,
        string $downloadName,
        string $contentType,
        private bool $deleteAfterSend = false,
    ) {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Download file is missing: {$path}");
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName) ?: 'download';

        $this->addHeader('Content-Type', $contentType);
        $this->addHeader('Content-Disposition', 'attachment; filename="'.$safeName.'"');
        $this->addHeader('Content-Length', (string) filesize($path));
        $this->addHeader('Cache-Control', 'private, no-store');
        $this->addHeader('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Send the headers, then the file in chunks, then delete it when asked to.
     */
    public function send(): void
    {
        try {
            if (!headers_sent()) {
                http_response_code($this->getStatusCode());
                foreach ($this->getHeaders() as $name => $value) {
                    header($name.': '.$value);
                }
            }

            $this->streamFile();
        } finally {
            if ($this->deleteAfterSend && is_file($this->path) && !unlink($this->path)) {
                error_log("Could not delete sent download {$this->path}");
            }
        }
    }

    private function streamFile(): void
    {
        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Could not open download file {$this->path}");
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        try {
            while (!feof($handle)) {
                echo fread($handle, self::CHUNK_BYTES);
                flush();
            }
        } finally {
            fclose($handle);
        }
    }
}
