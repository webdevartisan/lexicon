<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Interfaces\SchedulableCommandInterface;
use Throwable;

/**
 * Builds the road network in the background, as the admin queued it on the module's page. A run with nothing
 * queued rebuilds the current area from fresh road data, so the Scheduler can keep roads up to date.
 *
 * Usage: php cli journey-map:routing
 */
final class RoutingCommand implements SchedulableCommandInterface
{
    public const NAME = 'journey-map:routing';

    public function __construct(
        private MapJob $job,
        private RoutingSetup $setup,
        private RoutingFiles $files,
        private RoutePlanSchedule $planSchedule,
        private LegModel $legs,
    ) {}

    /**
     * The name on the Scheduler page.
     */
    public static function scheduleLabel(): string
    {
        return 'Build or update the journey map road network';
    }

    /**
     * Nothing to choose: the area is set on the module's page.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function argumentSchema(): array
    {
        return [];
    }

    /**
     * Run the queued build, or rebuild the current area when nothing is queued.
     */
    public function handle(array $arguments = []): int
    {
        if (!$this->job->lock()) {
            echo "Another road network job is running.\n";

            return 1;
        }

        try {
            $request = $this->nextRequest();
            if ($request === null) {
                echo "No road network to update.\n";

                return 0;
            }

            return $this->build($request);
        } catch (InvalidMapRequest) {
            return $this->failed('request');
        } finally {
            $this->job->unlock();
        }
    }

    /**
     * The queued request, or the built area again, or null when there is neither.
     *
     * @throws InvalidMapRequest
     */
    private function nextRequest(): ?RoutingRequest
    {
        $job = $this->job->current();
        if (($job['state'] ?? null) === 'queued') {
            return RoutingRequest::fromArray((array) $job['request']);
        }

        $info = $this->files->info();
        if ($info === null) {
            return null;
        }

        $request = RoutingRequest::fromArray($info);
        $this->job->queueRequest('build', $request->toArray(), 0);

        return $request;
    }

    /**
     * Build, then send every leg back to planning on the new roads and make sure the planner runs.
     */
    private function build(RoutingRequest $request): int
    {
        $this->job->begin((int) getmypid());

        try {
            $this->setup->run($request, $this->job);
            $this->legs->replanAll();
            $this->planSchedule->ensure();
            $this->job->finish();
            echo "Road network ready: {$request->region}\n";

            return 0;
        } catch (MapJobFailed $e) {
            return $this->failed($e->reason);
        } catch (Throwable) {
            return $this->failed('error');
        }
    }

    /**
     * Note the failure for the page and the console.
     */
    private function failed(string $reason): int
    {
        $this->job->fail($reason);
        echo "The road network job failed: {$reason}\n";

        return 1;
    }
}
