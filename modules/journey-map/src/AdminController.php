<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Auth;
use App\Controllers\AppController;
use Closure;
use Framework\Core\Response;

/**
 * The module's control panel page: the map and its tool, road routes, place search, and which blogs use it.
 *
 * The route group already lets in only people who manage extras, and the CSRF middleware checks every
 * POST. Each action answers JSON for the page's script.
 */
final class AdminController extends AppController
{
    private const AREA_NAME_MIN = 3;
    private const AREA_NAME_MAX = 120;

    public function __construct(
        private MapControl $map,
        private PlaceSearch $places,
        private SearchSettings $search,
        private SearchStatus $searchStatus,
        private StopModel $stops,
        private RangeProbe $probe,
        private RoutingControl $routing,
        private Auth $auth,
    ) {
    }

    /**
     * The page with its five cards.
     */
    public function show(): Response
    {
        $status = $this->map->status();
        $box = $status['map']['bbox'] ?? null;

        return $this->view('journey-map::admin', [
            'status' => $status,
            'preview' => $this->map->preview(),
            'rangeStatus' => $status['map'] === null ? null : $this->probe->status($this->map->fileUrl()),
            'search' => [
                'enabled' => $this->search->enabled(),
                'contact' => $this->search->contact(),
                'server' => $this->search->server(),
                'health' => $this->searchStatus->read(),
            ],
            'usage' => $this->stops->usage(),
            'outside' => is_array($box) ? $this->stops->outside($box) : [],
            'zooms' => MapRequest::ZOOMS,
            'presets' => array_keys(MapRequest::PRESETS),
            'routing' => $this->routing->status(),
            'areas' => $this->routing->areas(),
        ]);
    }

    /**
     * Say how much a road network for the chosen area downloads.
     */
    public function routingCheck(): Response
    {
        return $this->attempt(fn (): string => $this->routing->measure($this->chosenArea()));
    }

    /**
     * Build the road network for the chosen area in the background.
     */
    public function routingBuild(): Response
    {
        return $this->attempt(fn (): string => $this->routing->build($this->chosenArea(), $this->userId()));
    }

    /**
     * Stop the running road network build.
     */
    public function routingCancel(): Response
    {
        return $this->attempt(fn (): string => $this->routing->cancel());
    }

    /**
     * Delete the road network.
     */
    public function routingRemove(): Response
    {
        return $this->attempt(fn (): string => $this->routing->remove());
    }

    /**
     * The map card's state, polled while a job runs.
     */
    public function status(): Response
    {
        return $this->json($this->map->status());
    }

    /**
     * Get the map tool from its official release.
     */
    public function tool(): Response
    {
        // The download may take minutes on a slow link, and on Windows PHP counts that time against its limit.
        set_time_limit(360);

        return $this->attempt(fn (): string => $this->map->installTool());
    }

    /**
     * Look up a country or region by name and keep its outline for the area choice.
     */
    public function area(): Response
    {
        $name = trim((string) $this->field('name'));
        $length = mb_strlen($name);
        if ($length < self::AREA_NAME_MIN || $length > self::AREA_NAME_MAX) {
            return $this->json(['errors' => ['name' => 'Type a place name of 3 to 120 characters.']], 422);
        }

        try {
            $area = $this->places->area($name, 'en');
        } catch (PlaceSearchUnavailable $e) {
            $busy = $e->reason === 'busy';

            return $this->json(['error' => $busy ? 'Place search is busy. Try again in a moment.'
                : 'Place search did not answer. Try again later.'], $busy ? 429 : 503);
        }
        if ($area === null) {
            return $this->json(['error' => 'No area with an outline was found.'], 404);
        }

        return $this->attempt(function () use ($area): array {
            $saved = $this->map->saveRegion($area);

            return $saved + ['message' => 'Found '.$saved['name'].'. Check its size, then download.'];
        });
    }

    /**
     * Measure the chosen map without downloading it.
     */
    public function check(): Response
    {
        return $this->attempt(fn (): string => $this->map->check($this->chosenMap(), $this->userId()));
    }

    /**
     * Download the chosen map in the background.
     */
    public function download(): Response
    {
        return $this->attempt(fn (): string => $this->map->download($this->chosenMap(), $this->userId()));
    }

    /**
     * Stop the running job.
     */
    public function cancel(): Response
    {
        return $this->attempt(fn (): string => $this->map->cancel());
    }

    /**
     * Delete the map, its fonts and icons.
     */
    public function remove(): Response
    {
        return $this->attempt(fn (): string => $this->map->remove());
    }

    /**
     * Save the place search settings. Errors come back per field.
     */
    public function saveSearch(): Response
    {
        $errors = $this->search->save(
            (string) $this->field('enabled') === '1',
            trim((string) $this->field('contact')),
            trim((string) $this->field('server'))
        );

        return $errors === []
            ? $this->json(['message' => 'Saved the place search settings.'])
            : $this->json(['errors' => $errors], 422);
    }

    /**
     * Forget the saved search answers, so the next searches ask the service again.
     */
    public function clearSearch(): Response
    {
        $count = $this->places->forgetAnswers();

        return $this->json(['message' => 'Cleared '.$count.' saved '.($count === 1 ? 'answer.' : 'answers.')]);
    }

    /**
     * Run an action and answer with its message or data, or with why it was refused.
     *
     * @param Closure(): (string|array<string, mixed>) $action
     */
    private function attempt(Closure $action): Response
    {
        try {
            $result = $action();
        } catch (InvalidMapRequest $e) {
            return $this->json(['errors' => $e->errors], 422);
        } catch (MapActionRefused $e) {
            return $this->json(['error' => $e->getMessage()], $e->status);
        }

        return $this->json(is_array($result) ? $result : ['message' => $result]);
    }

    /**
     * The detail and area the admin chose.
     *
     * @throws InvalidMapRequest
     */
    private function chosenMap(): MapRequest
    {
        $input = [];
        foreach (['zoom', 'area', 'region', 'overview'] as $name) {
            $input[$name] = $this->field($name);
        }

        return $this->map->request($input);
    }

    /**
     * The road network area and port the admin chose.
     *
     * @throws InvalidMapRequest
     */
    private function chosenArea(): RoutingRequest
    {
        return RoutingRequest::fromArray(['region' => $this->field('region'), 'port' => $this->field('port')]);
    }

    /**
     * One posted value, or null when it is missing or not a plain value.
     */
    private function field(string $name): string|int|float|bool|null
    {
        $value = $this->request->post[$name] ?? null;

        return is_scalar($value) ? $value : null;
    }

    /**
     * The admin asking, recorded with the job.
     */
    private function userId(): int
    {
        return (int) ($this->auth->user()['id'] ?? 0);
    }
}
