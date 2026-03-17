<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth;
use App\Policies\PolicyResolver;
use App\Resources\BlogResource;

/**
 * NavigationService
 *
 * generate navigation menus based on user authentication state,
 * roles, selected blog context, and policy-based authorization.
 */
class NavigationService
{
    /**
     * @var array Navigation configuration
     */
    private array $config;

    /**
     * @var Auth Authentication service
     */
    private Auth $auth;

    /**
     * Constructor
     *
     * inject dependencies to keep the service testable and flexible.
     *
     * @param  array  $config  Navigation configuration array
     * @param  Auth  $auth  Authentication service instance
     */
    public function __construct(array $config, Auth $auth)
    {
        $this->config = $config;
        $this->auth = $auth;
    }

    /**
     * Get navigation items for a specific area
     *
     * filter navigation items based on authentication state, roles,
     * blog context (global vs contextual), and policy-based permissions.
     *
     * @param  string  $area  Navigation area (front, back, admin)
     * @param  string|null  $currentPath  Current request path for active state
     * @param  BlogResource|null  $selectedBlog  Selected blog resource for contextual navigation
     * @return array Filtered and processed navigation items with translation keys
     */
    public function for(string $area, ?string $currentPath = null, ?BlogResource $selectedBlog = null): array
    {
        $defs = $this->config[$area] ?? [];
        $isLogged = $this->auth->check();
        $user = $isLogged ? $this->auth->user() : null;

        // filter items based on authentication, authorization, scope, and policies
        $visible = array_filter($defs, function (array $it) use ($isLogged, $user, $selectedBlog) {
            // check basic authentication requirement
            if (array_key_exists('auth', $it)) {
                if ($it['auth'] === true && !$isLogged) {
                    return false;
                }
                if ($it['auth'] === false && $isLogged) {
                    return false;
                }
            }

            // check global role requirements
            if (!empty($it['roles'])) {
                $hasRole = false;
                foreach ($it['roles'] as $role) {
                    if ($this->auth->hasRole($role)) {
                        $hasRole = true;
                        break;
                    }
                }
                if (!$hasRole) {
                    return false;
                }
            }

            // check onboarding status (hide items until user has blogs)
            if (array_key_exists('onboarding', $it)) {
                $has = $this->auth->hasBlogs();
                if ($it['onboarding'] == false && $has === 0) {
                    return false;
                }
            }

            // handle scoped navigation items (global vs contextual)
            if (isset($it['scope'])) {
                // show global items always, contextual items only when blog selected
                if ($it['scope'] === 'contextual' && $selectedBlog === null) {
                    return false;
                }
            }

            // check policy-based permissions for contextual items
            if (!empty($it['policy']) && $selectedBlog !== null && $user !== null) {
                try {
                    $policy = PolicyResolver::for($selectedBlog);
                    $policyMethod = $it['policy'];

                    // verify the policy method exists and user is authorized
                    if (method_exists($policy, $policyMethod)) {
                        if (!$policy->$policyMethod($user, $selectedBlog)) {
                            return false;
                        }
                    }
                } catch (\Exception $e) {
                    // log policy resolution errors but don't break navigation
                    error_log('Navigation policy check failed: '.$e->getMessage());

                    return false;
                }
            }

            return true;
        });

        $items = [];

        // process each visible item and prepare it for templates
        foreach ($visible as $it) {
            $label = $it['label'];
            $href = $it['href'];
            $type = $it['type'] ?? 'link';
            $scope = $it['scope'] ?? null;
            $key = $it['key'] ?? null;

            // replace blog ID placeholder with actual blog ID if needed
            if (!empty($it['replace_blog_id']) && $selectedBlog !== null) {
                $href = str_replace('{blogId}', (string) $selectedBlog->id(), $href);
            }

            $tag = str_replace(' ', '-', strtolower($label));
            $href = rtrim($href, '/');
            $path = rtrim($currentPath ?: '/', '/');

            // determine if this item represents the current page
            $current = ($type === 'link') && (
                ($path === $href) ||
                ($href !== '' && $href !== '#' && str_ends_with($path, $href.'/'))
            );

            $items[] = [
                'label' => $label,
                'href' => $href,
                'current' => $current,
                'type' => $type, // 'link' or 'section_header'
                'scope' => $scope, // 'global', 'contextual', or null
                'key' => $key, // Translation key for i18n
                // precompute attributes to avoid PHP logic in templates
                'current_attr' => $current ? 'aria-current="page"' : '',
                'tag' => $tag,
            ];
        }

        return $items;
    }
}
