<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Auth;
use App\Gate;
use App\Models\BlogModel;
use Closure;
use Framework\Modules\SlotContext;
use Framework\Modules\SlotRenderer;

/**
 * The Place section in the post editor's main column: the post's places in order, each found by search
 * or a dropped pin, with how exact it is, and the post's stop number.
 *
 * It shows only to people who may change the blog, the same check the module's
 * endpoints sit behind, so nobody sees a panel that would refuse them.
 */
final class EditorPanelSlot implements SlotRenderer
{
    public function __construct(
        private Auth $auth,
        private BlogModel $blogs,
        private StopModel $stops,
        private MapData $map,
        private SearchSettings $searchSettings,
        private Route $route,
        private RoutingFiles $routing,
    ) {
    }

    /**
     * Only for people who may change the blog.
     */
    public function shouldRender(SlotContext $context): bool
    {
        if ($context->blogId === null) {
            return false;
        }

        $blog = $this->blogs->getBlog($context->blogId);

        return $blog !== false && Gate::allows('update', $blog, $this->auth->user() ?? []);
    }

    /**
     * The Place panel for the post being edited, or the save first note for a new post.
     */
    public function render(SlotContext $context): string
    {
        $postId = (int) ($context->post['id'] ?? 0);
        $base = '/dashboard/blog/'.$context->blogId.'/m/journey-map';
        $t = Partial::translator();

        return Partial::render('editor-panel', [
            't' => $t,
            'road' => $postId > 0 ? $this->roadLine((int) $context->blogId, $postId, $t) : null,
            'postId' => $postId ?: null,
            'stops' => $postId > 0 ? $this->stops->ofPost($postId) : [],
            'maxStops' => StopModel::MAX_PER_POST,
            'urls' => [
                'save' => lurl($base.'/stops/'.$postId),
                'remove' => lurl($base.'/stops/'.$postId.'/remove'),
                'places' => lurl($base.'/places'),
                'guide' => lurl($base),
            ],
            'token' => csrf_token(),
            'searchOn' => $this->searchSettings->enabled(),
            'map' => $this->map->available() ? $this->map->urls() : null,
        ]);
    }

    /**
     * How the roads this post travels stand, or null when there is no network or nothing to travel.
     * One step still waiting means planning; otherwise the worst answer speaks for the post.
     */
    private function roadLine(int $blogId, int $postId, Closure $t): ?string
    {
        $leg = $this->routing->built() ? $this->route->legOf($blogId, $postId) : null;
        $roads = $leg === null ? [] : $leg->roads();
        if ($roads === []) {
            return null;
        }

        $states = array_map(static fn (?array $road): string => match (true) {
            Leg::points($road) !== null => 'road',
            ($road['status'] ?? null) === 'none' => 'none',
            ($road['status'] ?? null) === 'failed' && (int) $road['attempts'] >= LegModel::MAX_ATTEMPTS => 'failed',
            default => 'waiting',
        }, $roads);

        return match (true) {
            in_array('waiting', $states, true) => $t('editor.roadPlanning'),
            in_array('failed', $states, true) => $t('editor.roadFailed'),
            in_array('none', $states, true) => $t('editor.roadNone'),
            default => $t('editor.roadPlanned', ['km' => Distance::rounded((float) $leg->km())]),
        };
    }

    /**
     * Map files, plus the panel's own style and script.
     */
    public function assets(): array
    {
        $map = $this->map->available() ? $this->map->assets() : ['css' => [], 'js' => []];

        return ['css' => [...$map['css'], 'editor.css'], 'js' => [...$map['js'], 'editor.js']];
    }
}
