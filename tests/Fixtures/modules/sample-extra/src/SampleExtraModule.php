<?php

declare(strict_types=1);

namespace Tests\Fixtures\Modules\SampleExtra;

use Framework\Core\Container;
use Framework\Database;
use Framework\Modules\BlogArchiveParticipant;
use Framework\Modules\BlogDataEraser;
use Framework\Modules\ModuleContext;
use Framework\Modules\ModuleInterface;
use Framework\Modules\ModuleRoutes;
use Framework\Modules\PersonalDataEraser;
use Framework\Modules\PersonalDataExporter;
use RuntimeException;

/**
 * The smallest module the module system tests can install, boot and erase.
 */
final class SampleExtraModule implements BlogArchiveParticipant, BlogDataEraser, ModuleInterface, PersonalDataEraser, PersonalDataExporter
{
    public static bool $registered = false;

    public static bool $failErase = false;

    public function __construct(private Database $db) {}

    public function register(Container $container): void
    {
        self::$registered = true;
    }

    public function boot(ModuleContext $context): void
    {
        $route = fn (string $action) => [
            'controller_class' => NoteController::class,
            'action' => $action,
            'method' => 'GET',
        ];

        $context->frontRoutes(fn (ModuleRoutes $routes) => $routes->add('/note', $route('show')));
        $context->manageRoutes(fn (ModuleRoutes $routes) => $routes->add('/notes', $route('index')));
        $context->adminRoutes(fn (ModuleRoutes $routes) => $routes->add('/', $route('settings')));
        $context->slot('post.after-content', new NoteSlot());
        $context->commands(['sample-extra:tidy' => TidyCommand::class]);
    }

    public function hasBlogData(int $blogId): bool
    {
        return (bool) $this->db->query('SELECT 1 FROM sample_extra_notes WHERE blog_id = ? LIMIT 1', [$blogId])
            ->fetchColumn();
    }

    public function eraseBlog(int $blogId): void
    {
        if (self::$failErase) {
            throw new RuntimeException('Sample extra could not erase its notes');
        }

        $this->db->execute('DELETE FROM sample_extra_notes WHERE blog_id = ?', [$blogId]);
    }

    public function eraseUser(int $userId): void
    {
        $this->db->execute('DELETE FROM sample_extra_notes WHERE user_id = ?', [$userId]);
    }

    public function exportUser(int $userId): array
    {
        return $this->db->query('SELECT note FROM sample_extra_notes WHERE user_id = ? ORDER BY id', [$userId])
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function exportBlog(int $blogId): array
    {
        return $this->db->query('SELECT post_id, note FROM sample_extra_notes WHERE blog_id = ? ORDER BY id', [$blogId])
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function importBlog(int $blogId, int $version, array $rows, array $postIdMap): int
    {
        $written = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['note'] ?? null)) {
                continue;
            }
            $postId = isset($row['post_id']) ? ($postIdMap[(int) $row['post_id']] ?? null) : null;
            if (isset($row['post_id']) && $postId === null) {
                continue;
            }

            $this->db->execute(
                'INSERT INTO sample_extra_notes (blog_id, post_id, note) VALUES (?, ?, ?)',
                [$blogId, $postId, mb_substr($row['note'], 0, 255)]
            );
            $written++;
        }

        return $written;
    }
}
