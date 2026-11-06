<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Auth;
use App\Gate;
use App\Models\BlogModel;
use Framework\Modules\SlotContext;
use Framework\Modules\SlotRenderer;

/**
 * The Place panel in the post editor's sidebar: search for a place or drop a pin, set the
 * stop number and how exact the place is.
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

        return Partial::render('editor-panel', [
            't' => Partial::translator(),
            'postId' => $postId ?: null,
            'stop' => $postId > 0 ? $this->stops->find($postId) : null,
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
     * Map files, plus the panel's own style and script.
     */
    public function assets(): array
    {
        $map = $this->map->available() ? $this->map->assets() : ['css' => [], 'js' => []];

        return ['css' => [...$map['css'], 'editor.css'], 'js' => [...$map['js'], 'editor.js']];
    }
}
