<header id="page-topbar" class="ltr:md:left-vertical-menu rtl:md:right-vertical-menu group-data-[sidebar-size=md]:ltr:md:left-vertical-menu-md group-data-[sidebar-size=md]:rtl:md:right-vertical-menu-md group-data-[sidebar-size=sm]:ltr:md:left-vertical-menu-sm group-data-[sidebar-size=sm]:rtl:md:right-vertical-menu-sm fixed right-0 z-[1000] left-0 print:hidden group-data-[navbar=bordered]:m-4 group-data-[navbar=bordered]:[&.is-sticky]:mt-0 transition-all ease-linear duration-300 group-data-[navbar=hidden]:hidden group-data-[navbar=scroll]:absolute group/topbar ">
    <div class="layout-width">
        <div class="flex items-center px-4 mx-auto bg-topbar border-b-2 border-topbar group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:border-topbar-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:border-topbar-brand shadow-md h-header shadow-slate-200/50 group-data-[navbar=bordered]:rounded-md group-data-[navbar=bordered]:group-[.is-sticky]/topbar:rounded-t-none group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:border-zink-700 dark:shadow-none group-data-[topbar=dark]:group-[.is-sticky]/topbar:dark:shadow-zink-500 group-data-[topbar=dark]:group-[.is-sticky]/topbar:dark:shadow-md group-data-[navbar=bordered]:shadow-none   ">
            <div class="flex items-center w-full   navbar-header  ">
                <!-- LOGO -->
                <div class="items-center justify-center hidden px-5 text-center h-header   ">
                    
                    <a href="/dashboard">

                        <span class="hidden">
                            <img src="/cp-assets/images/logo.png" alt="" class="h-6 mx-auto">
                        </span>

                        <span class="group-data-[topbar=dark]:hidden group-data-[topbar=brand]:hidden">
                            <!-- <img src="/cp-assets/images/logo-dark.png" alt="" class="h-6 mx-auto"> -->
                        </span>
                    </a>

                    <a href="/dashboard" class="hidden group-data-[topbar=dark]:block group-data-[topbar=brand]:block">
                        
                        <span class="group-data-[topbar=dark]:hidden group-data-[topbar=brand]:hidden">
                            <img src="/cp-assets/images/logo.png" alt="" class="h-6 mx-auto">
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
                <div class="relative hidden ltr:ml-3 rtl:mr-3 lg:block">
                    <form action="/dashboard/setDefaultBlog" method="POST" class="flex items-center">
                        {{ csrf_field() }}
                        {% cache 'lucide:book-open:blogswitch' ttl=31536000 %}<i data-lucide="book-open" class="inline-block size-4 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-topbar-item group-data-[topbar=dark]:text-zink-200"></i>{% endcache %}
                        <select name="blog" onchange="this.form.submit()" aria-label="Switch active blog"
                            class="py-2 ltr:pl-9 rtl:pr-9 ltr:pr-8 rtl:pl-8 text-sm rounded cursor-pointer appearance-none bg-topbar border border-topbar-border text-topbar-item min-w-[260px] focus-visible:outline-0 focus:border-blue-400 group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:border-topbar-border-dark group-data-[topbar=dark]:text-topbar-item-dark group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:border-zink-500 group-data-[topbar=dark]:dark:text-zink-100">
                            <?php foreach ($user_blogs as $bid => $b) {
                                $bname = is_array($b) ? (string) ($b['name'] ?? 'Untitled') : (string) $b;
                                $bstatus = is_array($b) ? (string) ($b['status'] ?? '') : '';
                                $suffixText = ($bstatus !== '' && $bstatus !== 'published')
                                    ? ' — '.ucfirst($bstatus)
                                    : '';
                                ?>
                            <option value="<?= e((string) $bid) ?>" <?= ((int) $bid === (int) ($selected_blog_id ?? 0)) ? 'selected' : '' ?>>
                                <?= e($bname.$suffixText) ?>
                            </option>
                            <?php } ?>
                        </select>
                        {% cache 'lucide:chevrons-up-down' ttl=31536000 %}<i data-lucide="chevrons-up-down" class="inline-block size-4 absolute ltr:right-2.5 rtl:left-2.5 top-1/2 -translate-y-1/2 pointer-events-none text-topbar-item group-data-[topbar=dark]:text-zink-200"></i>{% endcache %}
                    </form>
                </div>
                <?php } ?>

                <div class="flex gap-3 ms-auto">
                    <div class="relative flex items-center dropdown h-header">
                        <button type="button" 
                                class="inline-flex justify-center items-center p-0 text-topbar-item transition-all w-[37.5px] h-[37.5px] duration-200 ease-linear bg-topbar rounded-md dropdown-toggle btn hover:bg-topbar-item-bg-hover hover:text-topbar-item-hover group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:hover:bg-topbar-item-bg-hover-dark group-data-[topbar=dark]:hover:text-topbar-item-hover-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:hover:bg-topbar-item-bg-hover-brand group-data-[topbar=brand]:hover:text-topbar-item-hover-brand group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:hover:bg-zink-600 group-data-[topbar=dark]:dark:text-zink-500 group-data-[topbar=dark]:dark:hover:text-zink-50" 
                                id="flagsDropdown" 
                                data-bs-toggle="dropdown"
                                aria-label="Change language">
                            <img src="/cp-assets/images/flags/<?= $currentLang === 'el' ? 'gr' : 'us' ?>.svg" 
                                alt="<?= $currentLang ?>" 
                                id="header-lang-img" 
                                class="h-5 rounded-sm">
                        </button>
                        
                        <div class="absolute z-50 hidden p-4 ltr:text-left rtl:text-right bg-white rounded-md shadow-md !top-4 dropdown-menu min-w-[10rem] flex flex-col gap-4 dark:bg-zink-600" 
                            aria-labelledby="flagsDropdown">
                            
                            <a href="#!" class="flex items-center gap-3 group/items language" data-lang="en" title="English">
                                <img src="/cp-assets/images/flags/us.svg" alt="English" class="object-cover h-4 w-4 rounded-full">
                                <h6 class="transition-all duration-200 ease-linear text-[15px] font-medium text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">
                                    English
                                </h6>
                            </a>
                            
                            <a href="#!" class="flex items-center gap-3 group/items language" data-lang="el" title="Greek">
                                <img src="/cp-assets/images/flags/gr.svg" alt="Greek" class="object-cover h-4 w-4 rounded-full">
                                <h6 class="transition-all duration-200 ease-linear text-[15px] font-medium text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">
                                    Ελληνικά
                                </h6>
                            </a>
                            
                            <a href="#!" class="flex items-center gap-3 group/items language" data-lang="sp" title="Spanish">
                                <img src="/cp-assets/images/flags/es.svg" alt="Spanish" class="object-cover h-4 rounded-full">
                                <h6 class="transition-all duration-200 ease-linear text-[15px] font-medium text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">
                                    Español
                                </h6>
                            </a>
                            
                            <a href="#!" class="flex items-center gap-3 group/items language" data-lang="de" title="German">
                                <img src="/cp-assets/images/flags/de.svg" alt="German" class="object-cover h-4 rounded-full">
                                <h6 class="transition-all duration-200 ease-linear text-[15px] font-medium text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">
                                    Deutsch
                                </h6>
                            </a>
                            
                            <a href="#!" class="flex items-center gap-3 group/items language" data-lang="fr" title="French">
                                <img src="/cp-assets/images/flags/fr.svg" alt="French" class="object-cover h-4 rounded-full">
                                <h6 class="transition-all duration-200 ease-linear text-[15px] font-medium text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">
                                    Français
                                </h6>
                            </a>
                            
                            <a href="#!" class="flex items-center gap-3 group/items language" data-lang="jp" title="Japanese">
                                <img src="/cp-assets/images/flags/jp.svg" alt="Japanese" class="object-cover h-4 rounded-full">
                                <h6 class="transition-all duration-200 ease-linear text-[15px] font-medium text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">
                                    日本語
                                </h6>
                            </a>
                            
                            <a href="#!" class="flex items-center gap-3 group/items language" data-lang="ch" title="Chinese">
                                <img src="/cp-assets/images/flags/china.svg" alt="Chinese" class="object-cover h-4 rounded-full">
                                <h6 class="transition-all duration-200 ease-linear text-[15px] font-medium text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">
                                    中文
                                </h6>
                            </a>
                            
                            <a href="#!" class="flex items-center gap-3 group/items language" data-lang="it" title="Italian">
                                <img src="/cp-assets/images/flags/it2.svg" alt="Italian" class="object-cover h-4 rounded-full">
                                <h6 class="transition-all duration-200 ease-linear text-[15px] font-medium text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">
                                    Italiano
                                </h6>
                            </a>
                            
                            <a href="#!" class="flex items-center gap-3 group/items language" data-lang="ru" title="Russian">
                                <img src="/cp-assets/images/flags/ru2.svg" alt="Russian" class="object-cover h-4 rounded-full">
                                <h6 class="transition-all duration-200 ease-linear text-[15px] font-medium text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">
                                    Русский
                                </h6>
                            </a>
                            
                            <a href="#!" class="flex items-center gap-3 group/items language" data-lang="ar" title="Arabic">
                                <img src="/cp-assets/images/flags/ae2.svg" alt="Arabic" class="object-cover h-4 rounded-full">
                                <h6 class="transition-all duration-200 ease-linear text-[15px] font-medium text-slate-600 dark:text-zink-200 group-hover/items:text-custom-500">
                                    العربية
                                </h6>
                            </a>
                        </div>
                    </div>

                    {% cache 'topbar:sunbutton-icon' ttl=31536000 %}
                    <div class="relative flex items-center h-header">
                        <button type="button" aria-label="Toggle light or dark mode" class="inline-flex relative justify-center items-center p-0 text-topbar-item transition-all w-[37.5px] h-[37.5px] duration-200 ease-linear bg-topbar rounded-md btn hover:bg-topbar-item-bg-hover hover:text-topbar-item-hover group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:hover:bg-topbar-item-bg-hover-dark group-data-[topbar=dark]:hover:text-topbar-item-hover-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:hover:bg-topbar-item-bg-hover-brand group-data-[topbar=brand]:hover:text-topbar-item-hover-brand group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:hover:bg-zink-600 group-data-[topbar=brand]:text-topbar-item-brand group-data-[topbar=dark]:dark:hover:text-zink-50 group-data-[topbar=dark]:dark:text-zink-200 group-data-[topbar=dark]:text-topbar-item-dark" id="light-dark-mode">
                            <i data-lucide="sun" class="inline-block w-5 h-5 stroke-1 fill-slate-100 group-data-[topbar=dark]:fill-topbar-item-dark group-data-[topbar=brand]:fill-topbar-item-bg-hover-brand"></i>
                        </button>
                    </div>
                    {% endcache %}
                    <!-- Notifications: recent activity on the user's posts. -->
                    {% if (!empty($notifications['enabled'])): %}
                    <div class="relative flex items-center dropdown h-header">
                        <button type="button" class="inline-flex justify-center relative items-center p-0 text-topbar-item transition-all w-[37.5px] h-[37.5px] duration-200 ease-linear bg-topbar rounded-md dropdown-toggle btn hover:bg-topbar-item-bg-hover hover:text-topbar-item-hover group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:hover:bg-topbar-item-bg-hover-dark group-data-[topbar=dark]:hover:text-topbar-item-hover-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:hover:bg-topbar-item-bg-hover-brand group-data-[topbar=brand]:hover:text-topbar-item-hover-brand group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:hover:bg-zink-600 group-data-[topbar=brand]:text-topbar-item-brand group-data-[topbar=dark]:dark:hover:text-zink-50 group-data-[topbar=dark]:dark:text-zink-200 group-data-[topbar=dark]:text-topbar-item-dark" id="notificationDropdown" data-bs-toggle="dropdown" aria-label="Notifications">
                            {% cache 'lucide:bell-ring' ttl=31536000 %}<i data-lucide="bell-ring" class="inline-block w-5 h-5 stroke-1 fill-slate-100 group-data-[topbar=dark]:fill-topbar-item-bg-hover-dark group-data-[topbar=brand]:fill-topbar-item-bg-hover-brand"></i>{% endcache %}
                            <?php if (!empty($notifications['count'])) { ?>
                            <span class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center min-w-[16px] h-[16px] px-1 text-[10px] font-semibold text-white bg-red-500 rounded-full">
                                <?= (int) $notifications['count'] > 9 ? '9+' : (int) $notifications['count'] ?>
                            </span>
                            <?php } ?>
                        </button>
                        <div class="absolute z-50 hidden ltr:text-left rtl:text-right bg-white rounded-md shadow-md !top-4 dropdown-menu min-w-[20rem] lg:min-w-[26rem] dark:bg-zink-600" aria-labelledby="notificationDropdown">
                            <div class="p-4">
                                <h6 class="mb-2 text-16 flex items-center gap-2">
                                    Notifications
                                    <?php if (!empty($notifications['count'])) { ?>
                                    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[11px] font-medium rounded-full text-white bg-custom-500"><?= (int) $notifications['count'] ?></span>
                                    <?php } ?>
                                </h6>
                                <p class="text-xs text-slate-500 dark:text-zink-300">Recent activity on your posts and collaborations.</p>
                            </div>
                            <div data-simplebar="" class="max-h-[350px] border-t border-slate-100 dark:border-zink-500">
                                <?php if (empty($notifications['items'])) { ?>
                                <div class="text-center py-8 px-4">
                                    {% cache 'lucide:bell-off' ttl=31536000 %}<i data-lucide="bell-off" class="size-8 text-slate-400 mx-auto mb-2"></i>{% endcache %}
                                    <p class="text-sm text-slate-500 dark:text-zink-300">You're all caught up. No recent activity.</p>
                                </div>
                                <?php } else { ?>
                                <div class="flex flex-col">
                                    <?php foreach ($notifications['items'] as $n) { ?>
                                        {% include "partials/_notification_item.lex.php" %}
                                    <?php } ?>
                                </div>
                                <?php } ?>
                            </div>
                            <div class="flex items-center justify-end p-3 border-t border-slate-100 dark:border-zink-500">
                                <a href="/dashboard/notifications" class="text-xs font-medium text-custom-500 hover:text-custom-600">View all notifications</a>
                            </div>
                        </div>
                    </div>
                    {% endif; %}

                    <!-- Profile -->
                    {% set signedInAsLabel = t('layout.profileDropdown.signedInAs') %}
                    {% set controlPanelLabel = t('layout.profileDropdown.links.controlPanel') %}
                    {% set editProfileLabel = t('layout.profileDropdown.links.editProfile') %}
                    {% set accountSettingsLabel = t('layout.profileDropdown.links.accountSettings') %}
                    {% set signOutLabel = t('layout.profileDropdown.links.signOut') %}

                    <div class="relative flex items-center dropdown h-header">
                        <button type="button" class="inline-block p-0 transition-all duration-200 ease-linear bg-topbar rounded-full text-topbar-item dropdown-toggle btn hover:bg-topbar-item-bg-hover hover:text-topbar-item-hover group-data-[topbar=dark]:text-topbar-item-dark group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:hover:bg-topbar-item-bg-hover-dark group-data-[topbar=dark]:hover:text-topbar-item-hover-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:hover:bg-topbar-item-bg-hover-brand group-data-[topbar=brand]:hover:text-topbar-item-hover-brand group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:hover:bg-zink-600 group-data-[topbar=brand]:text-topbar-item-brand group-data-[topbar=dark]:dark:hover:text-zink-50 group-data-[topbar=dark]:dark:text-zink-200" id="dropdownMenuButton" data-bs-toggle="dropdown">
                            <div class="bg-pink-100 rounded-full">
                                {% if current_user.avatar_url|notempty %}
                                    <img src="{{ current_user.avatar_url }}" alt="" class="h-12 w-12 rounded-full ring-1 ring-offset-2 ring-slate-200 dark:ring-offset-zink-700 dark:ring-zink-500">
                                {% else %}
                                    <div class="flex items-center justify-center rounded-full size-10 bg-custom-100 text-custom-500 ring-1 ring-offset-2 ring-custom-200 dark:ring-offset-zink-700 dark:ring-custom-900 dark:bg-custom-950">
                                        <?php
                                            // generate user initials from first and last name
                                            $initials = '';
                if (!empty($current_user['first_name'])) {
                    $initials .= strtoupper(substr($current_user['first_name'], 0, 1));
                }
                if (!empty($current_user['last_name'])) {
                    $initials .= strtoupper(substr($current_user['last_name'], 0, 1));
                }
                echo e($initials ?: 'U');
                ?>
                                    </div>
                                {% endif %}
                            </div>
                        </button>
                        <div class="absolute z-50 hidden p-4 ltr:text-left rtl:text-right bg-white rounded-md shadow-md !top-4 dropdown-menu min-w-[14rem] dark:bg-zink-600" aria-labelledby="dropdownMenuButton">
                            <h6 class="mb-2 text-sm font-normal text-slate-500 dark:text-zink-300">{{ signedInAsLabel }}</h6>
                            <a href="/dashboard/profile" class="flex gap-3 mb-3">
                                <div class="relative inline-block shrink-0">
                                    <div class="rounded bg-slate-100 dark:bg-zink-500">
                                        {% if current_user.avatar_url|notempty %}
                                            <img src="{{ current_user.avatar_url }}" alt="" class="h-12 w-12 rounded-md ring-1 ring-offset-2 ring-slate-200 dark:ring-offset-zink-700 dark:ring-zink-500">
                                        {% else %}
                                            <div class="flex items-center justify-center rounded-md size-10 bg-custom-100 text-custom-500 ring-1 ring-offset-2 ring-custom-200 dark:ring-offset-zink-700 dark:ring-custom-900 dark:bg-custom-950">
                                                <?php
                        // generate user initials from first and last name
                        $initials = '';
                if (!empty($current_user['first_name'])) {
                    $initials .= strtoupper(substr($current_user['first_name'], 0, 1));
                }
                if (!empty($current_user['last_name'])) {
                    $initials .= strtoupper(substr($current_user['last_name'], 0, 1));
                }
                echo e($initials ?: 'U');
                ?>
                                            </div>
                                        {% endif %}
                                    </div>
                                    <span class="-top-1 ltr:-right-1 rtl:-left-1 absolute w-2.5 h-2.5 bg-green-400 border-2 border-white rounded-full dark:border-zink-600"></span>
                                </div>
                                <div>
                                    <h6 class="mb-1 text-15">{{ current_user.first_name }}</h6>
                                    <p class="text-slate-500 dark:text-zink-300">{{ current_user.email }}</p>
                                </div>
                            </a>
                            <ul>
                                <?php if (auth()->hasRole('administrator')) { ?>
                                <!-- Stable entry: always Control Panel. The logo already leads back to the dashboard. -->
                                <li>
                                    <a class="block ltr:pr-4 rtl:pl-4 py-1.5 text-base font-medium transition-all duration-200 ease-linear text-slate-600 dropdown-item hover:text-custom-500 focus:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:focus:text-custom-500" href="/admin">
                                        {% cache 'lucide:sliders-horizontal' ttl=31536000 %}<i data-lucide="sliders-horizontal" class="inline-block size-4 ltr:mr-2 rtl:ml-2"></i>{% endcache %}
                                        <span>{{ controlPanelLabel }}</span>
                                    </a>
                                </li>
                                <?php } ?>
                                <li>
                                    <a class="block ltr:pr-4 rtl:pl-4 py-1.5 text-base font-medium transition-all duration-200 ease-linear text-slate-600 dropdown-item hover:text-custom-500 focus:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:focus:text-custom-500" href="/dashboard/profile">
                                        {% cache 'lucide:user-2' ttl=31536000 %}<i data-lucide="user-2" class="inline-block size-4 ltr:mr-2 rtl:ml-2"></i>{% endcache %}
                                        <span>{{ editProfileLabel }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="block ltr:pr-4 rtl:pl-4 py-1.5 text-base font-medium transition-all duration-200 ease-linear text-slate-600 dropdown-item hover:text-custom-500 focus:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:focus:text-custom-500" href="/dashboard/account">
                                        {% cache 'lucide:settings' ttl=31536000 %}<i data-lucide="settings" class="inline-block size-4 ltr:mr-2 rtl:ml-2"></i>{% endcache %}
                                        <span>{{ accountSettingsLabel }}</span>
                                    </a>
                                </li>
                                <li class="pt-2 mt-2 border-t border-slate-200 dark:border-zink-500">
                                    <form method="post" action="<?= e(lurl('/logout')) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="block w-full ltr:text-left rtl:text-right ltr:pr-4 rtl:pl-4 py-1.5 text-base font-medium transition-all duration-200 ease-linear text-slate-600 dropdown-item hover:text-custom-500 focus:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:focus:text-custom-500">
                                            {% cache 'lucide:log-out' ttl=31536000 %}<i data-lucide="log-out" class="inline-block size-4 ltr:mr-2 rtl:ml-2"></i>{% endcache %}
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
<?php // var_dump($user);die;?>