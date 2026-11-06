<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Models\BlogModuleModel;
use App\Models\ScheduledTaskModel;
use App\Services\PublicCacheInvalidator;
use App\Services\ScheduleService;
use Closure;
use Framework\Core\Container;
use Framework\Database;

/**
 * The road routes part of the module: its files, the area list, Java, the build and the planner.
 */
final class RoutingServices
{
    /** A country's road data can take a while; the largest areas take far longer. */
    private const DOWNLOAD_SECONDS = 21600;

    /**
     * Register everything under the module's storage folder.
     */
    public static function register(Container $container, string $storage, string $agent): void
    {
        $download = MapTool::curlDownload($agent, self::DOWNLOAD_SECONDS);
        $container->setShared(RoutingFiles::class, fn () => new RoutingFiles($storage.'/routing'));
        $container->setShared(RoutingJob::class, fn () => new RoutingJob($storage.'/routing-job'));
        $container->setShared(CountryIndex::class, fn () => new CountryIndex(
            MapTool::curlJson($agent),
            CountryIndex::curlSize($agent),
            $storage.'/routing-areas.json'
        ));
        $container->setShared(JavaRuntime::class, fn (Container $c) => new JavaRuntime(
            $c->get(RoutingFiles::class),
            new ProcessToolRunner(),
            MapTool::curlJson($agent),
            $download
        ));
        $container->setShared(RouteSource::class, fn (Container $c) => new GraphHopperServer(
            $c->get(RoutingFiles::class),
            $c->get(JavaRuntime::class)
        ));
        $container->setShared(RoutingControl::class, fn (Container $c) => new RoutingControl(
            $c->get(RoutingFiles::class),
            $c->get(RoutingJob::class),
            $c->get(CountryIndex::class),
            $c->get(JavaRuntime::class),
            self::starter($c),
            $c->get(LegModel::class),
            $c->get(Database::class),
            $c->get(PublicCacheInvalidator::class),
            CountryIndex::curlSize($agent)
        ));
        self::registerCommands($container, $download);
    }

    /**
     * The routing starter, for the module's page.
     */
    public static function starter(Container $c): MapJobStarter
    {
        return new MapJobStarter(
            $c->get(Database::class),
            $c->get(ScheduledTaskModel::class),
            $c->get(ScheduleService::class),
            RoutingCommand::NAME,
            'Journey map road network'
        );
    }

    /**
     * Delete the Scheduler rows of both routing commands.
     */
    public static function forget(Database $db): void
    {
        MapJobStarter::forget($db, RoutingCommand::NAME);
        RoutePlanSchedule::forget($db);
    }

    /**
     * The build and planning commands.
     */
    private static function registerCommands(Container $container, Closure $download): void
    {
        $container->setShared(RoutingCommand::class, fn (Container $c) => new RoutingCommand(
            $c->get(RoutingJob::class),
            new RoutingSetup(
                $c->get(RoutingFiles::class),
                $c->get(CountryIndex::class),
                $c->get(JavaRuntime::class),
                new ProcessToolRunner(),
                $download
            ),
            $c->get(RoutingFiles::class),
            new RoutePlanSchedule($c->get(Database::class), $c->get(ScheduledTaskModel::class)),
            $c->get(LegModel::class)
        ));
        $container->setShared(PlanRoutesCommand::class, fn (Container $c) => new PlanRoutesCommand(
            $c->get(BlogModuleModel::class),
            $c->get(LegSync::class),
            $c->get(LegModel::class),
            $c->get(RoutingFiles::class),
            $c->get(RouteSource::class),
            new RoutePlanner($c->get(LegModel::class)),
            $c->get(RoutingJob::class),
            $c->get(PublicCacheInvalidator::class)
        ));
    }
}
