<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RoleModel;

/**
 * Display names for the role someone holds on a blog.
 *
 * Shipped roles are translated; a custom role made in the control panel has no
 * translation, so it shows the name the administrator gave it.
 */
final class BlogRoleLabels
{
    private const SHIPPED = ['owner', 'editor', 'author', 'reviewer', 'contributor'];

    /** @var array<string, string>|null */
    private ?array $customNames = null;

    private ?TranslationService $translator = null;

    public function __construct(private RoleModel $roles) {}

    /**
     * @param  string  $role  'owner' or a blog_users.role slug
     */
    public function label(string $role): string
    {
        if (in_array($role, self::SHIPPED, true)) {
            $this->translator ??= new TranslationService(LocaleState::get()->chromeLocale);

            return $this->translator->translate('roles.blog.'.$role);
        }

        $this->customNames ??= array_column($this->roles->findByScope('blog'), 'role_name', 'role_slug');

        return (string) ($this->customNames[$role] ?? ucfirst(str_replace('_', ' ', $role)));
    }
}
