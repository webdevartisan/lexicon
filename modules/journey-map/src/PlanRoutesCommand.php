<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Interfaces\SchedulableCommandInterface;
use App\Models\BlogModuleModel;
use App\Services\PublicCacheInvalidator;

/**
 * Brings every journey's legs up to date and plans the waiting ones. GraphHopper is started only when a leg
 * waits, and always stopped before the run ends.
 *
 * Usage: php cli journey-map:plan-routes
 */
final class PlanRoutesCommand implements SchedulableCommandInterface
{
    public const NAME = 'journey-map:plan-routes';

    private const BATCH = 200;

    /**
     * @param  MapJob  $buildJob  The road network job, so no run plans while the network is being rebuilt
     */
    public function __construct(
        private BlogModuleModel $blogModules,
        private LegSync $sync,
        private LegModel $legs,
        private RoutingFiles $files,
        private RouteSource $source,
        private RoutePlanner $planner,
        private MapJob $buildJob,
        private PublicCacheInvalidator $pages,
    ) {}

    /**
     * The name on the Scheduler page.
     */
    public static function scheduleLabel(): string
    {
        return 'Plan journey map road routes';
    }

    /**
     * Nothing to choose.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function argumentSchema(): array
    {
        return [];
    }

    /**
     * Sync, then plan what waits.
     */
    public function handle(array $arguments = []): int
    {
        if (!$this->files->built() || $this->buildJob->busy()) {
            return 0;
        }

        // Posts going public or private never pass through the stop editor, so each run checks every journey.
        foreach ($this->blogModules->blogsWithModule('journey-map') as $blogId) {
            $this->sync->sync($blogId);
        }
        if ($this->legs->waitingCount() === 0) {
            return 0;
        }

        return $this->planWaiting();
    }

    /**
     * Start the planner, plan one batch, and stop it whatever happens.
     */
    private function planWaiting(): int
    {
        try {
            if (!$this->source->start()) {
                echo "The route planner did not start.\n";

                return 1;
            }
            $planned = $this->planner->plan($this->source, self::BATCH);
            // Cached pages still show the straight lines, so they go once any road arrives.
            if ($planned > 0) {
                $this->pages->purgeAllPages();
            }
            echo "Planned {$planned} legs.\n";

            return 0;
        } finally {
            $this->source->stop();
        }
    }
}
