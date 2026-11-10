<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Auth;
use App\Controllers\ModuleController;
use App\Models\BlogModuleModel;
use App\Services\LocaleState;
use Framework\Core\Response;
use Framework\Helpers\RateLimiter;

/**
 * Place search for the editor's Place panel. Each answer is places, or a message the panel can show.
 */
final class PlaceController extends ModuleController
{
    private const SEARCHES_PER_MINUTE = 30;

    private const WINDOW_SECONDS = 60;

    public function __construct(
        BlogModuleModel $blogModules,
        private PlaceSearch $places,
        private RateLimiter $limiter,
        private Auth $auth,
        private SearchSettings $settings,
    ) {
        parent::__construct($blogModules);
    }

    /**
     * Places matching ?q=, named in the editor's language. A 404 while the admin has search switched off.
     */
    public function search(string $blogId): Response
    {
        $this->requireActive((int) $blogId);
        if (!$this->settings->enabled()) {
            return $this->json(['error' => chrome_translate('journey-map::editor.searchOff')], 404);
        }

        $key = 'journey-map:places:'.(int) ($this->auth->user()['id'] ?? 0);
        $this->limiter->hit($key, self::WINDOW_SECONDS);
        if ($this->limiter->tooManyAttempts($key, self::SEARCHES_PER_MINUTE, self::WINDOW_SECONDS)) {
            return $this->json(['error' => chrome_translate('journey-map::editor.tooMany')], 429);
        }

        $query = $this->request->get['q'] ?? '';

        try {
            $places = $this->places->search(is_string($query) ? $query : '', LocaleState::get()->chromeLocale);
        } catch (PlaceSearchUnavailable $e) {
            $busy = $e->reason === 'busy';

            return $this->json(
                ['error' => chrome_translate($busy ? 'journey-map::editor.busy' : 'journey-map::editor.failed')],
                $busy ? 429 : 503
            );
        }

        return $this->json(['places' => $places]);
    }

    /**
     * The module whose switch decides if this endpoint exists on a blog.
     */
    protected function moduleKey(): string
    {
        return 'journey-map';
    }
}
