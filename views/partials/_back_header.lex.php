<header id="page-topbar" class="ltr:md:left-vertical-menu rtl:md:right-vertical-menu group-data-[sidebar-size=sm]:ltr:md:left-vertical-menu-sm group-data-[sidebar-size=sm]:rtl:md:right-vertical-menu-sm fixed right-0 z-[1000] left-0 print:hidden group-data-[navbar=bordered]:m-4 group-data-[navbar=bordered]:[&.is-sticky]:mt-0 transition-all ease-linear duration-300 group-data-[navbar=hidden]:hidden group-data-[navbar=scroll]:absolute group/topbar ">
    <div class="layout-width">
        <div class="flex items-center px-4 mx-auto bg-topbar border-b-2 border-topbar group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:border-topbar-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:border-topbar-brand shadow-md h-header shadow-slate-200/50 group-data-[navbar=bordered]:rounded-md group-data-[navbar=bordered]:group-[.is-sticky]/topbar:rounded-t-none group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:border-zink-700 dark:shadow-none group-data-[topbar=dark]:group-[.is-sticky]/topbar:dark:shadow-zink-500 group-data-[topbar=dark]:group-[.is-sticky]/topbar:dark:shadow-md group-data-[navbar=bordered]:shadow-none   ">
            <div class="flex items-center w-full   navbar-header  ">
                <!-- LOGO -->
                <div class="items-center justify-center hidden px-5 text-center h-header   ">
                    
                    <a href="<?= e(lurl('/dashboard')) ?>">

                        <span class="hidden">
                            <img src="/cp-assets/images/logo/L-light.png" alt="" class="h-6 mx-auto">
                        </span>

                        <span class="group-data-[topbar=dark]:hidden group-data-[topbar=brand]:hidden">
                            <!-- <img src="/cp-assets/images/logo-dark.png" alt="" class="h-6 mx-auto"> -->
                        </span>
                    </a>

                    <a href="<?= e(lurl('/dashboard')) ?>" class="hidden group-data-[topbar=dark]:block group-data-[topbar=brand]:block">
                        
                        <span class="group-data-[topbar=dark]:hidden group-data-[topbar=brand]:hidden">
                            <img src="/cp-assets/images/logo/L-dark.png" alt="" class="h-6 mx-auto">
                        </span>

                        <span class="group-data-[topbar=dark]:block group-data-[topbar=brand]:block">
                            <!-- <img src="/cp-assets/images/logo-light.png" alt="" class="h-6 mx-auto"> -->
                        </span>

                    </a>

                </div>

                {% cache 'topbar:chevron-icon' ttl=31536000 %}
                <button type="button" class="inline-flex relative justify-center items-center p-0 text-topbar-item transition-all w-[37.5px] h-[37.5px] duration-75 ease-linear bg-topbar rounded-md btn hover:bg-slate-100 group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:border-topbar-dark group-data-[topbar=dark]:text-topbar-item-dark group-data-[topbar=dark]:hover:bg-topbar-item-bg-hover-dark group-data-[topbar=dark]:hover:text-topbar-item-hover-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:border-topbar-brand group-data-[topbar=brand]:text-topbar-item-brand group-data-[topbar=brand]:hover:bg-topbar-item-bg-hover-brand group-data-[topbar=brand]:hover:text-topbar-item-hover-brand group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:text-zink-200 group-data-[topbar=dark]:dark:border-zink-700 group-data-[topbar=dark]:dark:hover:bg-zink-600 group-data-[topbar=dark]:dark:hover:text-zink-50   hamburger-icon" id="topnav-hamburger-icon">
                    <i data-lucide="chevrons-left" class="w-5 h-5 group-data-[sidebar-size=sm]:hidden"></i>
                    <i data-lucide="chevrons-right" class="hidden w-5 h-5 group-data-[sidebar-size=sm]:block"></i>
                </button>
                {% endcache %}
                <?php if (!empty($user_blogs)) { ?>
                <!-- Blog switcher: -->
                <div class="relative min-w-0 ltr:ml-2 rtl:mr-2 sm:ltr:ml-3 sm:rtl:mr-3">
                    <form action="/dashboard/active-blog" method="POST" class="flex items-center">
                        <input type="hidden" name="return_to" value="<?= e($current_path) ?>">
                        {{ csrf_field() }}
                        <?php
                        $switcherGroups = ['owned' => [], 'shared' => []];
                        foreach ($user_blogs as $bid => $b) {
                            $switcherGroups[$b['shared'] ? 'shared' : 'owned'][$bid] = $b;
                        }
                        $switcherOption = static function (int $bid, array $b) use ($t, $selected_blog_id): string {
                            $text = $b['name'];
                            if ($b['shared']) {
                                $text .= ' · '.$b['roleLabel'];
                            }
                            if ($b['status'] !== 'published') {
                                $text .= ' ('.$t('dashboard.blogStatus.'.$b['status']).')';
                            }

                            return '<option value="'.$bid.'"'.($bid === (int) ($selected_blog_id ?? 0) ? ' selected' : '').'>'.e($text).'</option>';
                        };
                        ?>
                        <select name="blog" data-auto-submit aria-label="<?= e($t('dashboard.switcher.label')) ?>"
                            class="py-2 ltr:pl-2 rtl:pr-2 sm:ltr:pl-9 sm:rtl:pr-9 ltr:pr-7 rtl:pl-7 sm:ltr:pr-8 sm:rtl:pl-8 text-sm rounded cursor-pointer appearance-none bg-topbar border border-topbar-border text-topbar-item w-full max-w-[8.5rem] sm:max-w-[14rem] lg:max-w-none lg:min-w-[260px] truncate focus-visible:outline-0 focus:border-blue-400 group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:border-topbar-border-dark group-data-[topbar=dark]:text-topbar-item-dark group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:border-zink-500 group-data-[topbar=dark]:dark:text-zink-100">
                            <?php if ((int) ($selected_blog_id ?? 0) === 0) { ?>
                            <option value="" selected disabled><?= e($t('dashboard.switcher.choose')) ?></option>
                            <?php } ?>
                            <?php foreach (['owned', 'shared'] as $group) {
                                if ($switcherGroups[$group] === []) {
                                    continue;
                                } ?>
                            <optgroup label="<?= e($t('dashboard.blogs.groups.'.$group)) ?>">
                                <?php foreach ($switcherGroups[$group] as $bid => $b) {
                                    echo $switcherOption((int) $bid, $b);
                                } ?>
                            </optgroup>
                            <?php } ?>
                        </select>
                    </form>
                </div>
                <?php } ?>

                <div class="hidden md:block ltr:ml-4 rtl:mr-4 min-w-0">
                    {% include "partials/_breadcrumbs.lex.php" %}
                </div>

                <div class="flex gap-3 ms-auto">

                    <?php
                    // The shared bell: dashboard pages show the person's own inbox,
                    // admin pages the operations inbox. The panel loads on first open.
                    ?>
                    {% if (!empty($notifications['enabled'])): %}
                    <?php
                    $bellCount = (int) $notifications['count'];
                    $bellListPath = $notifications['listPath'];
                    $bellClass = 'is-cp h-header';
                    ?>
                    {% include "partials/notifications/_bell.lex.php" %}
                    {% endif; %}

                    <!-- User menu -->
                    {% set controlPanelLabel = t('layout.profileDropdown.links.controlPanel') %}
                    {% set editProfileLabel = t('layout.profileDropdown.links.editProfile') %}
                    {% set accountSettingsLabel = t('layout.profileDropdown.links.accountSettings') %}
                    {% set signOutLabel = t('layout.profileDropdown.links.signOut') %}
                    {% set themeLabel = t('layout.themeMenu.label') %}
                    {% set themeLightLabel = t('layout.themeMenu.light') %}
                    {% set themeAutoLabel = t('layout.themeMenu.auto') %}
                    {% set themeDarkLabel = t('layout.themeMenu.dark') %}
                    <?php
                    $initials = '';
                    if (!empty($current_user['first_name'])) {
                        $initials .= strtoupper(substr($current_user['first_name'], 0, 1));
                    }
                    if (!empty($current_user['last_name'])) {
                        $initials .= strtoupper(substr($current_user['last_name'], 0, 1));
                    }
                    $initials = $initials ?: 'U';
                    $menuName = trim((string) ($current_user['first_name'] ?? '')) ?: (string) ($current_user['email'] ?? '');
                    $avatarUrl = (string) ($current_user['avatar_url'] ?? '');
                    $avatar = static function (string $size) use ($avatarUrl, $initials): string {
                        if ($avatarUrl !== '') {
                            return '<img src="'.e($avatarUrl).'" alt="" class="'.$size.' shrink-0 rounded-full object-cover">';
                        }

                        return '<span class="'.$size.' shrink-0 inline-flex items-center justify-center rounded-full bg-custom-100 text-custom-500 text-xs font-semibold dark:bg-custom-950">'.e($initials).'</span>';
                    };
                    $menuItem = 'flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md transition-colors text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus:bg-slate-100 dark:text-zink-200 dark:hover:bg-zink-500 dark:hover:text-zink-50 dark:focus:bg-zink-500';
                    $themeButton = 'inline-flex items-center justify-center size-7 rounded-md text-slate-500 transition-colors hover:text-slate-900 aria-pressed:bg-white aria-pressed:text-slate-900 aria-pressed:shadow-sm dark:text-zink-300 dark:hover:text-zink-50 dark:aria-pressed:bg-zink-400 dark:aria-pressed:text-zink-50';
                    ?>
                    <div class="relative flex items-center dropdown h-header" id="theme-menu">
                        <button type="button" aria-haspopup="menu" aria-label="<?= e($menuName) ?>" id="dropdownMenuButton" data-bs-toggle="dropdown"
                            class="dropdown-toggle inline-flex items-center gap-2 py-1 ltr:pl-1 ltr:pr-2 rtl:pr-1 rtl:pl-2 rounded-full text-sm font-medium transition-colors text-topbar-item hover:bg-topbar-item-bg-hover group-data-[topbar=dark]:text-zink-100 group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:hover:bg-zink-600">
                            <?= $avatar('size-8') ?>
                            <span class="hidden sm:inline max-w-[9rem] truncate"><?= e($menuName) ?></span>
                            {% cache 'lucide:menu:chevron-down' ttl=31536000 %}<i data-lucide="chevron-down" class="size-4 opacity-70" aria-hidden="true"></i>{% endcache %}
                        </button>
                        <div aria-labelledby="dropdownMenuButton"
                            class="absolute z-50 hidden p-2 ltr:text-left rtl:text-right bg-white rounded-xl shadow-lg dropdown-menu w-72 dark:bg-zink-600 !top-3 max-sm:!fixed max-sm:!inset-x-0 max-sm:!bottom-0 max-sm:!top-auto max-sm:![transform:none] max-sm:!w-full max-sm:rounded-b-none max-sm:pb-6">
                            <a href="<?= e(lurl('/account/profile')) ?>" data-account-open class="flex items-center gap-3 px-3 py-3 mb-1 rounded-md hover:bg-slate-100 dark:hover:bg-zink-500">
                                <?= $avatar('size-10') ?>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold truncate text-slate-900 dark:text-zink-50"><?= e($menuName) ?></span>
                                    <span class="block text-xs truncate text-slate-500 dark:text-zink-300">{{ current_user.email }}</span>
                                </span>
                            </a>
                            <ul>
                                <?php if (auth()->hasRole('administrator')) { ?>
                                <li>
                                    <a class="<?= $menuItem ?>" href="<?= e(lurl('/admin')) ?>">
                                        {% cache 'lucide:menu:sliders-horizontal' ttl=31536000 %}<i data-lucide="sliders-horizontal" class="size-4" aria-hidden="true"></i>{% endcache %}
                                        <span>{{ controlPanelLabel }}</span>
                                    </a>
                                </li>
                                <?php } ?>
                                <li>
                                    <a class="<?= $menuItem ?>" data-account-open href="<?= e(lurl('/account/profile')) ?>">
                                        {% cache 'lucide:menu:user-2' ttl=31536000 %}<i data-lucide="user-2" class="size-4" aria-hidden="true"></i>{% endcache %}
                                        <span>{{ editProfileLabel }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="<?= $menuItem ?>" data-account-open href="<?= e(lurl('/account/preferences')) ?>">
                                        {% cache 'lucide:menu:settings' ttl=31536000 %}<i data-lucide="settings" class="size-4" aria-hidden="true"></i>{% endcache %}
                                        <span>{{ accountSettingsLabel }}</span>
                                    </a>
                                </li>
                                <li class="flex items-center justify-between gap-3 px-3 py-2">
                                    <span class="flex items-center gap-3 text-sm font-medium text-slate-600 dark:text-zink-200">
                                        {% cache 'lucide:menu:sun-moon' ttl=31536000 %}<i data-lucide="sun-moon" class="size-4" aria-hidden="true"></i>{% endcache %}
                                        <span>{{ themeLabel }}</span>
                                    </span>
                                    <span class="inline-flex gap-0.5 p-0.5 rounded-lg bg-slate-100 dark:bg-zink-700" role="group" aria-label="<?= e($themeLabel) ?>">
                                        <button type="button" class="<?= $themeButton ?>" data-theme-choice="light" title="{{ themeLightLabel }}" aria-label="{{ themeLightLabel }}">{% cache 'lucide:menu:sun' ttl=31536000 %}<i data-lucide="sun" class="size-4" aria-hidden="true"></i>{% endcache %}</button>
                                        <button type="button" class="<?= $themeButton ?>" data-theme-choice="dark" title="{{ themeDarkLabel }}" aria-label="{{ themeDarkLabel }}">{% cache 'lucide:menu:moon' ttl=31536000 %}<i data-lucide="moon" class="size-4" aria-hidden="true"></i>{% endcache %}</button>
                                        <button type="button" class="<?= $themeButton ?>" data-theme-choice="auto" title="{{ themeAutoLabel }}" aria-label="{{ themeAutoLabel }}">{% cache 'lucide:menu:monitor' ttl=31536000 %}<i data-lucide="monitor" class="size-4" aria-hidden="true"></i>{% endcache %}</button>
                                    </span>
                                </li>
                                <li class="pt-1 mt-1 border-t border-slate-200 dark:border-zink-500">
                                    <form method="post" action="<?= e(lurl('/logout')) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="<?= $menuItem ?> w-full text-red-600 hover:text-red-600 dark:text-red-400 dark:hover:text-red-400">
                                            {% cache 'lucide:menu:log-out' ttl=31536000 %}<i data-lucide="log-out" class="size-4" aria-hidden="true"></i>{% endcache %}
                                            <span>{{ signOutLabel }}</span>
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>


            </div>
        </div>
    </div>
</header>
