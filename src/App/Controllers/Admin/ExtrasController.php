<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\ModuleModel;
use App\Services\PublicCacheInvalidator;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;
use Framework\Modules\ModuleManifest;
use Framework\Modules\ModuleRegistry;

/**
 * Switches installed extras on or off for the whole site, blog and platform ones alike.
 *
 * Blog extras switched off here vanish from every blog's own Extras page, but each
 * blog's choice is kept, so switching them back on restores it.
 */
final class ExtrasController extends AppController
{
    protected ?string $areaAbility = 'manageExtras';

    private const SCOPE_LABELS = ['blog' => 'Each blog', 'platform' => 'Whole site'];

    private const SCOPE_NOTES = [
        'blog' => 'Blog owners switch it on for their own blog.',
        'platform' => 'Works across the whole site while it is on.',
    ];

    private const STATUS_LABELS = ['on' => 'On', 'off' => 'Off', 'paused' => 'Paused'];

    public function __construct(
        private ModuleRegistry $registry,
        private ModuleModel $modules,
        private PublicCacheInvalidator $publicCache,
    ) {}

    /**
     * List every installed extra with its scope, its status and a switch.
     */
    public function index(): Response
    {
        $reasons = array_column($this->modules->paused(), 'paused_reason', 'module_key');

        $extras = [];
        foreach ($this->registry->present() as $key => $manifest) {
            $state = $this->registry->state($key) ?? 'off';
            $extras[] = [
                'key' => $key,
                'name' => $manifest->name,
                'description' => $manifest->description,
                'scope' => self::SCOPE_LABELS[$manifest->scope],
                'note' => self::SCOPE_NOTES[$manifest->scope],
                'on' => $state === 'on',
                'status' => self::STATUS_LABELS[$state],
                'reason' => (string) ($reasons[$key] ?? ''),
                'settings' => $state === 'on' && $manifest->hasAdminPage ? '/admin/m/'.$key : null,
            ];
        }

        return $this->view('areas/admin/Extras/index.lex.php', ['extras' => $extras]);
    }

    /**
     * Switch one extra on or off for the whole site. Switching a paused one on resumes it.
     */
    public function toggle(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $input = $this->validateOrFail(['module' => 'required', 'enabled' => 'required|in:0,1'])->validated();
        $manifest = $this->installed((string) $input['module']);

        if ($input['enabled'] === '1') {
            $this->modules->switchOn($manifest->key);
            $this->flash('success', 'Extra switched on for the whole site.');
        } else {
            $this->modules->switchOff($manifest->key);
            $this->flash('success', 'Extra switched off for the whole site.');
        }

        $this->registry->rebuild();
        // Extras draw into blog pages and site pages alike, so any cached page may hold their output.
        $this->publicCache->purgeAllPages();

        return $this->redirect(lurl('/admin/extras'));
    }

    /**
     * @throws PageNotFoundException When no installed extra has that key
     */
    private function installed(string $key): ModuleManifest
    {
        return $this->registry->present()[$key] ?? throw new PageNotFoundException('That extra is not installed.');
    }
}
