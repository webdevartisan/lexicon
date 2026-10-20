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

                    <?php $userMenuClass = 'is-cp h-header'; ?>
                    {% include "partials/_user_menu.lex.php" %}


            </div>
        </div>
    </div>
</header>
