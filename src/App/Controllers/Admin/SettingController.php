<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\Controllers\AppController;
use App\Models\RoleModel;
use App\Models\SettingModel;
use App\Services\MaintenanceMode;
use Framework\Core\Response;

/**
 * Manage site-wide settings.
 *
 * We organize settings into logical sections (identity, content, users, email)
 * to keep the interface manageable as the platform grows.
 */
final class SettingController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageSettings';

    public function __construct(
        protected Auth $auth,
        private SettingModel $settings,
        private RoleModel $roles,
        private MaintenanceMode $maintenance,
    ) {}

    /**
     * Display all settings organized by section.
     */
    public function index(): Response
    {
        return $this->view([
            'settings' => $this->settings->all(),
            'mail_config' => $this->getMailConfig(),
            'roles' => $this->roles->findAll(),
            'maintenance_active' => MaintenanceMode::active(),
        ]);
    }

    /**
     * Save site settings.
     *
     * The settings screen is split into section forms that each post only
     * their own fields, so we validate and persist just the keys that were
     * actually submitted.
     */
    public function update(): Response
    {
        // enforce CSRF protection
        csrf()->assertValid($this->request->postParam('_token'));

        $rules = [
            // site identity
            'site_name' => 'required|min:2|max:100',
            'site_description' => 'max:255',
            'site_tagline' => 'max:100',
            'admin_email' => 'required|email',
            'timezone' => 'required',

            // content
            'posts_per_page' => 'required|integer|min:1|max:50',
            'excerpt_length' => 'required|integer|min:50|max:500',
            'date_format' => 'required',
            'allow_comments' => 'boolean',

            // registration and availability
            'registration_enabled' => 'boolean',
            'default_user_role' => 'required|integer',
            'require_email_verification' => 'boolean',
            'maintenance_mode' => 'boolean',
        ];

        // only validate fields this section form actually posted
        $rules = array_intersect_key($rules, $this->request->post);

        $validator = $this->validateOrFail($rules);

        $data = $validator->validated();

        // Maintenance lives in a flag file, not the settings table, so the
        // gate keeps working when the database is down (see MaintenanceMode)
        if (array_key_exists('maintenance_mode', $data)) {
            $this->applyMaintenanceToggle(!empty($data['maintenance_mode']));
            unset($data['maintenance_mode']);
        }

        // persist each setting using batch update for better performance
        if ($data !== []) {
            $this->settings->setMany($data);
        }

        $this->flash('success', 'Settings saved successfully.');

        return $this->redirect('/admin/settings');
    }

    /**
     * Flip the maintenance flag file when the submitted state differs.
     *
     * Silently flipping the whole site on or off is exactly what an audit
     * trail is for, so every actual change is logged.
     */
    private function applyMaintenanceToggle(bool $wanted): void
    {
        if ($wanted === MaintenanceMode::active()) {
            return;
        }

        $changed = $wanted
            ? $this->maintenance->enable([
                'site_name' => (string) $this->settings->get('site_name', 'Lexicon'),
            ])
            : $this->maintenance->disable();

        if (!$changed) {
            $this->flash('error', 'Could not update the maintenance flag file. Check storage permissions.');

            return;
        }

        audit()->log(
            (int) auth()->user()['id'],
            $wanted ? 'site.maintenance_on' : 'site.maintenance_off',
            'setting',
            null,
            [],
            $this->request->ip()
        );
    }

    /**
     * Get mail configuration for read-only display.
     *
     * We never expose SMTP credentials in the UI for security.
     *
     * @return array<string, mixed> Sanitized mail settings
     */
    private function getMailConfig(): array
    {
        return [
            'driver' => (string) env('MAIL_DRIVER', 'not set'),
            'host' => (string) env('MAIL_HOST', 'not set'),
            'port' => (string) env('MAIL_PORT', 'not set'),
            'from_address' => (string) env('MAIL_FROM_ADDRESS', 'not set'),
            'from_name' => (string) env('MAIL_FROM_NAME', 'not set'),
            'encryption' => (string) env('MAIL_ENCRYPTION', 'tls'),
            'debug' => (bool) env('MAIL_DEBUG', false),
        ];
    }
}
