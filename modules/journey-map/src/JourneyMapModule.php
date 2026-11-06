<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Services\Modules\ModuleAssets;
use App\Services\PublicCacheInvalidator;
use Framework\Cache\CacheService;
use Framework\Core\Container;
use Framework\Database;
use Framework\Modules\BlogArchiveParticipant;
use Framework\Modules\BlogDataEraser;
use Framework\Modules\ModuleContext;
use Framework\Modules\ModuleInterface;
use Framework\Modules\ModuleRoutes;
use Framework\Modules\ModuleUninstaller;
use Framework\Modules\SlotRenderer;

/**
 * Journey map: each post can be a stop with a place, and the blog shows the whole journey on a map.
 */
final class JourneyMapModule implements ModuleInterface, BlogDataEraser, BlogArchiveParticipant, ModuleUninstaller
{
    private const KEY = 'journey-map';
    private const STORAGE = ROOT_PATH.'/storage/journey-map';

    private StopModel $stops;

    private ?Container $container = null;

    public function __construct(private Database $db)
    {
        $this->stops = new StopModel($db);
    }

    /**
     * Shared services, so the slots and pages of one request use one copy of each.
     */
    public function register(Container $container): void
    {
        $this->container = $container;

        $container->setShared(StopModel::class, fn (Container $c) => new StopModel($c->get(Database::class)));
        $this->registerMap($container);
        $this->registerSearch($container);
    }

    /**
     * The map file, the tool that cuts it and the background job that downloads it.
     */
    private function registerMap(Container $container): void
    {
        $storage = self::STORAGE;
        $agent = self::userAgent();
        $container->setShared(MapData::class, function (Container $c) use ($storage): MapData {
            $public = $c->get(ModuleAssets::class);

            return new MapData($public->filesFolder(self::KEY), $storage, $public->filesUrl(self::KEY));
        });
        $container->setShared(MapJob::class, fn () => new MapJob($storage));
        $container->setShared(
            RegionStore::class,
            fn (Container $c) => new RegionStore($c->get(MapJob::class)->regionDir())
        );
        $container->setShared(MapTool::class, fn () => new MapTool(
            $storage.'/bin',
            MapTool::curlJson($agent),
            MapTool::curlDownload($agent, 300)
        ));
        $container->setShared(MapDataCommand::class, fn (Container $c) => new MapDataCommand(
            $c->get(MapJob::class),
            $c->get(MapData::class),
            // The fonts archive is far larger than the tool, so it gets more time.
            new MapAssets($c->get(ModuleAssets::class)->filesFolder(self::KEY), MapTool::curlDownload($agent, 1800)),
            new ProcessToolRunner(),
            $c->get(MapTool::class),
            $c->get(PublicCacheInvalidator::class),
            MapTool::curlJson($agent)
        ));
    }

    /**
     * Delete the tool, job, place outlines and map note under storage/, and the Scheduler row.
     * Core deletes the stops table and public/modules/journey-map/, map included, itself.
     */
    public function uninstall(): void
    {
        Folder::remove(self::STORAGE);
        MapJobStarter::forget($this->db);
    }

    /**
     * How the module names itself to the servers it calls: the site, never a person.
     */
    private static function userAgent(): string
    {
        return 'Lexicon journey map ('.rtrim(base_url(), '/').')';
    }

    /**
     * Place search follows the admin's settings: the server it asks and the contact it sends.
     */
    private function registerSearch(Container $container): void
    {
        $container->setShared(SearchStatus::class, fn (Container $c) => new SearchStatus($c->get(CacheService::class)));
        $container->setShared(PlaceSearch::class, function (Container $c): PlaceSearch {
            $settings = $c->get(SearchSettings::class);

            return new PlaceSearch(
                PlaceSearch::curlFetch(self::userAgent()),
                ROOT_PATH.'/storage/cache/journey-map/nominatim.lock',
                $c->get(CacheService::class),
                $settings->endpoint(),
                $settings->contact(),
                $c->get(SearchStatus::class)
            );
        });
    }

    /**
     * The Journey page, the blog's guide page, the control panel page, the editor's endpoints, the four
     * places the module draws into and the map command. Slots build their real renderer only when a page
     * reaches them.
     */
    public function boot(ModuleContext $context): void
    {
        $context->frontRoutes(function (ModuleRoutes $routes): void {
            $routes->add('/', $this->route(JourneyController::class, 'show', 'GET'));
        });

        $context->manageRoutes(function (ModuleRoutes $routes): void {
            $routes->add('/', $this->route(GuideController::class, 'show', 'GET'));
            $routes->add('/stops/{postId:\d+}', $this->route(StopController::class, 'save', 'POST'));
            $routes->add('/stops/{postId:\d+}/remove', $this->route(StopController::class, 'remove', 'POST'));
            $routes->add('/places', $this->route(PlaceController::class, 'search', 'GET'));
        });

        $context->adminRoutes(function (ModuleRoutes $routes): void {
            $routes->add('/', $this->route(AdminController::class, 'show', 'GET'));
            $routes->add('/status', $this->route(AdminController::class, 'status', 'GET'));
            foreach (['tool', 'area', 'check', 'download', 'cancel', 'remove'] as $action) {
                $routes->add('/'.$action, $this->route(AdminController::class, $action, 'POST'));
            }
            $routes->add('/search', $this->route(AdminController::class, 'saveSearch', 'POST'));
            $routes->add('/search/clear', $this->route(AdminController::class, 'clearSearch', 'POST'));
        });

        $context->slot('editor.sidebar', $this->deferred(EditorPanelSlot::class));
        $context->slot('post.header-aside', $this->deferred(LocatorSlot::class));
        $context->slot('post.after-content', $this->deferred(NextStopSlot::class));
        $context->slot('blog.nav', $this->deferred(NavSlot::class));

        $context->commands(['journey-map:map-data' => MapDataCommand::class]);
    }

    /**
     * Whether the blog has any stops, so the Extras page can offer to delete them.
     */
    public function hasBlogData(int $blogId): bool
    {
        return $this->stops->hasBlogData($blogId);
    }

    /**
     * Delete every stop of the blog.
     */
    public function eraseBlog(int $blogId): void
    {
        $this->stops->eraseBlog($blogId);
    }

    /**
     * The blog's stops for its backup.
     */
    public function exportBlog(int $blogId): array
    {
        return $this->stops->exportBlog($blogId);
    }

    /**
     * Restore stops onto the restored posts. Rows go through the same checks as the editor,
     * so a backup can never store an exact point for an approximate stop.
     */
    public function importBlog(int $blogId, int $version, array $rows, array $postIdMap): int
    {
        $written = 0;
        foreach ($rows as $row) {
            $postId = is_array($row) ? ($postIdMap[(int) ($row['post_id'] ?? 0)] ?? null) : null;
            if ($postId === null) {
                continue;
            }

            try {
                $stop = Stop::fromInput(['precision' => $row['place_precision'] ?? null] + $row);
            } catch (InvalidStopException) {
                continue;
            }

            $this->stops->save($blogId, $postId, $stop);
            $written++;
        }

        return $written;
    }

    /**
     * @param class-string $controller
     * @return array{controller_class: class-string, action: string, method: string}
     */
    private function route(string $controller, string $action, string $method): array
    {
        return ['controller_class' => $controller, 'action' => $action, 'method' => $method];
    }

    /**
     * @param class-string<SlotRenderer> $class
     */
    private function deferred(string $class): DeferredSlot
    {
        return new DeferredSlot(fn (): SlotRenderer => $this->container->get($class));
    }
}
