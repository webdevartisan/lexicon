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

                {% cache 'topbar:chevron-icon' ttl=3600 %}
                <button type="button" class="inline-flex relative justify-center items-center p-0 text-topbar-item transition-all w-[37.5px] h-[37.5px] duration-75 ease-linear bg-topbar rounded-md btn hover:bg-slate-100 group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:border-topbar-dark group-data-[topbar=dark]:text-topbar-item-dark group-data-[topbar=dark]:hover:bg-topbar-item-bg-hover-dark group-data-[topbar=dark]:hover:text-topbar-item-hover-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:border-topbar-brand group-data-[topbar=brand]:text-topbar-item-brand group-data-[topbar=brand]:hover:bg-topbar-item-bg-hover-brand group-data-[topbar=brand]:hover:text-topbar-item-hover-brand group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:text-zink-200 group-data-[topbar=dark]:dark:border-zink-700 group-data-[topbar=dark]:dark:hover:bg-zink-600 group-data-[topbar=dark]:dark:hover:text-zink-50   hamburger-icon" id="topnav-hamburger-icon">
                    <i data-lucide="chevrons-left" class="w-5 h-5 group-data-[sidebar-size=sm]:hidden"></i>
                    <i data-lucide="chevrons-right" class="hidden w-5 h-5 group-data-[sidebar-size=sm]:block"></i>
                </button>
                {% endcache %}
                <div class="relative hidden ltr:ml-3 rtl:mr-3 lg:block  ">
                    <form action="/dashboard" method="GET">

                        <input type="hidden" name="selectedBlogId" value="{{ selectedBlogId }}">
                        <input type="hidden" name="searchPostStatus" value="">
                        <div>

                        <input type="text" 
                            name="query" 
                            value="{{ query }}" 
                            class="py-2 pr-4 text-sm text-topbar-item bg-topbar border border-topbar-border rounded pl-8 placeholder:text-slate-400 form-control focus-visible:outline-0 min-w-[300px] focus:border-blue-400 group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:border-topbar-border-dark group-data-[topbar=dark]:placeholder:text-slate-500 group-data-[topbar=dark]:text-topbar-item-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:border-topbar-border-brand group-data-[topbar=brand]:placeholder:text-blue-300 group-data-[topbar=brand]:text-topbar-item-brand group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:border-zink-500 group-data-[topbar=dark]:dark:text-zink-100" 
                            placeholder="{{ t('layout.search.placeholder') }}" 
                            autocomplete="off">
                            {% cache 'topbar:inputsearch-icon' ttl=3600 %}
                            <i data-lucide="search" class="inline-block size-4 absolute left-2.5 top-2.5 text-topbar-item fill-slate-100 group-data-[topbar=dark]:fill-topbar-item-bg-hover-dark group-data-[topbar=dark]:text-topbar-item-dark group-data-[topbar=brand]:fill-topbar-item-bg-hover-brand group-data-[topbar=brand]:text-topbar-item-brand group-data-[topbar=dark]:dark:text-zink-200 group-data-[topbar=dark]:dark:fill-zink-600"></i>
                            {% endcache %}
                            {% if errors.query|notempty %}
                                {% foreach ($errors as $type => $messages): %}
                                    {% foreach ($messages as $msg): %}
                                        <p class="mt-1 text-xs text-red-600"> <?= $msg ?> </p>
                                    {% endforeach %}
                                {% endforeach %}
                            {% endif %}
                        </div>
                    </form>
                </div>

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

                    {% cache 'topbar:sunbutton-icon' ttl=3600 %}
                    <div class="relative flex items-center h-header">
                        <button type="button" class="inline-flex relative justify-center items-center p-0 text-topbar-item transition-all w-[37.5px] h-[37.5px] duration-200 ease-linear bg-topbar rounded-md btn hover:bg-topbar-item-bg-hover hover:text-topbar-item-hover group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:hover:bg-topbar-item-bg-hover-dark group-data-[topbar=dark]:hover:text-topbar-item-hover-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:hover:bg-topbar-item-bg-hover-brand group-data-[topbar=brand]:hover:text-topbar-item-hover-brand group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:hover:bg-zink-600 group-data-[topbar=brand]:text-topbar-item-brand group-data-[topbar=dark]:dark:hover:text-zink-50 group-data-[topbar=dark]:dark:text-zink-200 group-data-[topbar=dark]:text-topbar-item-dark" id="light-dark-mode">
                            <i data-lucide="sun" class="inline-block w-5 h-5 stroke-1 fill-slate-100 group-data-[topbar=dark]:fill-topbar-item-dark group-data-[topbar=brand]:fill-topbar-item-bg-hover-brand"></i>
                        </button>
                    </div>
                    {% endcache %}
                    <!-- Notifications -->
                    {% if (!empty($notifications['enabled'])): %}
                    <div class="relative flex items-center dropdown h-header">
                        <button type="button" class="inline-flex justify-center relative items-center p-0 text-topbar-item transition-all w-[37.5px] h-[37.5px] duration-200 ease-linear bg-topbar rounded-md dropdown-toggle btn hover:bg-topbar-item-bg-hover hover:text-topbar-item-hover group-data-[topbar=dark]:bg-topbar-dark group-data-[topbar=dark]:hover:bg-topbar-item-bg-hover-dark group-data-[topbar=dark]:hover:text-topbar-item-hover-dark group-data-[topbar=brand]:bg-topbar-brand group-data-[topbar=brand]:hover:bg-topbar-item-bg-hover-brand group-data-[topbar=brand]:hover:text-topbar-item-hover-brand group-data-[topbar=dark]:dark:bg-zink-700 group-data-[topbar=dark]:dark:hover:bg-zink-600 group-data-[topbar=brand]:text-topbar-item-brand group-data-[topbar=dark]:dark:hover:text-zink-50 group-data-[topbar=dark]:dark:text-zink-200 group-data-[topbar=dark]:text-topbar-item-dark" id="notificationDropdown" data-bs-toggle="dropdown">
                            <i data-lucide="bell-ring" class="inline-block w-5 h-5 stroke-1 fill-slate-100 group-data-[topbar=dark]:fill-topbar-item-bg-hover-dark group-data-[topbar=brand]:fill-topbar-item-bg-hover-brand"></i>
                            <span class="absolute top-0 right-0 flex w-1.5 h-1.5">
                                <span class="absolute inline-flex w-full h-full rounded-full opacity-75 animate-ping bg-sky-400"></span>
                                <span class="relative inline-flex w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                            </span>
                        </button>
                        <div class="absolute z-50 hidden ltr:text-left rtl:text-right bg-white rounded-md shadow-md !top-4 dropdown-menu min-w-[20rem] lg:min-w-[26rem] dark:bg-zink-600" aria-labelledby="notificationDropdown">
                            <div class="p-4">
                                <h6 class="mb-4 text-16">Notifications <span class="inline-flex items-center justify-center w-5 h-5 ml-1 text-[11px] font-medium border rounded-full text-white bg-orange-500 border-orange-500">15</span></h6>
                                <ul class="flex flex-wrap w-full p-1 mb-2 text-sm font-medium text-center rounded-md filter-btns text-slate-500 bg-slate-100 nav-tabs dark:bg-zink-500 dark:text-zink-200" data-filter-target="notification-list">
                                    <li class="grow">
                                        <a href="javascript:void(0);" data-filter="all" class="inline-block nav-link px-1.5 w-full py-1 text-xs transition-all duration-300 ease-linear rounded-md text-slate-500 border border-transparent [&.active]:bg-white [&.active]:text-custom-500 hover:text-custom-500 active:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:[&.active]:bg-zink-600 -mb-[1px] active">View All</a>
                                    </li>
                                    <li class="grow">
                                        <a href="javascript:void(0);" data-filter="mention" class="inline-block nav-link px-1.5 w-full py-1 text-xs transition-all duration-300 ease-linear rounded-md text-slate-500 border border-transparent [&.active]:bg-white [&.active]:text-custom-500 hover:text-custom-500 active:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:[&.active]:bg-zink-600 -mb-[1px]">Mentions</a>
                                    </li>
                                    <li class="grow">
                                        <a href="javascript:void(0);" data-filter="follower" class="inline-block nav-link px-1.5 w-full py-1 text-xs transition-all duration-300 ease-linear rounded-md text-slate-500 border border-transparent [&.active]:bg-white [&.active]:text-custom-500 hover:text-custom-500 active:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:[&.active]:bg-zink-600 -mb-[1px]">Followers</a>
                                    </li>
                                    <li class="grow">
                                        <a href="javascript:void(0);" data-filter="invite" class="inline-block nav-link px-1.5 w-full py-1 text-xs transition-all duration-300 ease-linear rounded-md text-slate-500 border border-transparent [&.active]:bg-white [&.active]:text-custom-500 hover:text-custom-500 active:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:[&.active]:bg-zink-600 -mb-[1px]">Invites</a>
                                    </li>
                                </ul>

                            </div>
                            <div data-simplebar="" class="max-h-[350px]">
                                <div class="flex flex-col gap-1" id="notification-list">
                                    <a href="#!" class="flex gap-3 p-4 product-item hover:bg-slate-50 dark:hover:bg-zink-500 follower">
                                        <div class="w-10 h-10 rounded-md shrink-0 bg-slate-100">
                                            <img src="/cp-assets/images/avatar-3.png" alt="" class="rounded-md">
                                        </div>
                                        <div class="grow">
                                            <h6 class="mb-1 font-medium"><b>@willie_passem</b> followed you</h6>
                                            <p class="mb-0 text-sm text-slate-500 dark:text-zink-300"><i data-lucide="clock" class="inline-block w-3.5 h-3.5 mr-1"></i> <span class="align-middle">Wednesday 03:42 PM</span></p>
                                        </div>
                                        <div class="flex items-center self-start gap-2 text-xs text-slate-500 shrink-0 dark:text-zink-300">
                                            <div class="w-1.5 h-1.5 bg-custom-500 rounded-full"></div> 4 sec
                                        </div>
                                    </a>
                                    <a href="#!" class="flex gap-3 p-4 product-item hover:bg-slate-50 dark:hover:bg-zink-500 mention">
                                        <div class="w-10 h-10 bg-yellow-100 rounded-md shrink-0">
                                            <img src="/cp-assets/images/avatar-5.png" alt="" class="rounded-md">
                                        </div>
                                        <div class="grow">
                                            <h6 class="mb-1 font-medium"><b>@caroline_jessica</b> commented on your post</h6>
                                            <p class="mb-3 text-sm text-slate-500 dark:text-zink-300"><i data-lucide="clock" class="inline-block w-3.5 h-3.5 mr-1"></i> <span class="align-middle">Wednesday 03:42 PM</span></p>
                                            <div class="p-2 rounded bg-slate-100 text-slate-500 dark:bg-zink-500 dark:text-zink-300">Amazing! Fast, to the point, professional and really amazing to work with them!!!</div>
                                        </div>
                                        <div class="flex items-center self-start gap-2 text-xs text-slate-500 shrink-0 dark:text-zink-300">
                                            <div class="w-1.5 h-1.5 bg-custom-500 rounded-full"></div> 15 min
                                        </div>
                                    </a>
                                    <a href="#!" class="flex gap-3 p-4 product-item hover:bg-slate-50 dark:hover:bg-zink-500 invite">
                                        <div class="flex items-center justify-center w-10 h-10 bg-red-100 rounded-md shrink-0">
                                            <i data-lucide="shopping-bag" class="w-5 h-5 text-red-500 fill-red-200"></i>
                                        </div>
                                        <div class="grow">
                                            <h6 class="mb-1 font-medium">Successfully purchased a business plan for <span class="text-red-500">$199.99</span></h6>
                                            <p class="mb-0 text-sm text-slate-500 dark:text-zink-300"><i data-lucide="clock" class="inline-block w-3.5 h-3.5 mr-1"></i> <span class="align-middle">Monday 11:26 AM</span></p>
                                        </div>
                                        <div class="flex items-center self-start gap-2 text-xs text-slate-500 shrink-0 dark:text-zink-300">
                                            <div class="w-1.5 h-1.5 bg-custom-500 rounded-full"></div> Yesterday
                                        </div>
                                    </a>
                                    <a href="#!" class="flex gap-3 p-4 product-item hover:bg-slate-50 dark:hover:bg-zink-500 mention">
                                        <div class="relative shrink-0">
                                            <div class="w-10 h-10 bg-pink-100 rounded-md">
                                                <img src="/cp-assets/images/avatar-7.png" alt="" class="rounded-md">
                                            </div>
                                            <div class="absolute text-orange-500 -bottom-0.5 -right-0.5 text-16">
                                                <i class="ri-heart-fill"></i>
                                            </div>
                                        </div>
                                        <div class="grow">
                                            <h6 class="mb-1 font-medium"><b>@scott</b> liked your post</h6>
                                            <p class="mb-0 text-sm text-slate-500 dark:text-zink-300"><i data-lucide="clock" class="inline-block w-3.5 h-3.5 mr-1"></i> <span class="align-middle">Thursday 06:59 AM</span></p>
                                        </div>
                                        <div class="flex items-center self-start gap-2 text-xs text-slate-500 shrink-0 dark:text-zink-300">
                                            <div class="w-1.5 h-1.5 bg-custom-500 rounded-full"></div> 1 Week
                                        </div>
                                    </a>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 p-4 border-t border-slate-200 dark:border-zink-500">
                                <div class="grow">
                                    <a href="#!">Manage Notification</a>
                                </div>
                                <div class="shrink-0">
                                    <button type="button" class="px-2 py-1.5 text-xs text-white transition-all duration-200 ease-linear btn bg-custom-500 border-custom-500 hover:text-white hover:bg-custom-600 hover:border-custom-600 focus:text-white focus:bg-custom-600 focus:border-custom-600 focus:ring focus:ring-custom-100 active:text-white active:bg-custom-600 active:border-custom-600 active:ring active:ring-custom-100">View All Notification <i data-lucide="move-right" class="inline-block w-3.5 h-3.5 ml-1"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    {% endif; %}

                    <!-- Profile -->
                    {% set signedInAsLabel = t('layout.profileDropdown.signedInAs') %}
                    {% set dashboardLabel = t('layout.profileDropdown.links.dashboard') %}
                    {% set controlPanelLabel = t('layout.profileDropdown.links.controlPanel') %}
                    {% set editProfileLabel = t('layout.profileDropdown.links.editProfile') %}
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
                            <a href="#!" class="flex gap-3 mb-3">
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
                                        echo htmlspecialchars($initials ?: 'U', ENT_QUOTES, 'UTF-8');
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
                                    <?php
                                        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                                    $path = rtrim($path, '/');
                                    ?>
                                <li>
                                    <?php if (str_ends_with($path, 'admin')) { ?>
                                        <a class="block ltr:pr-4 rtl:pl-4 py-1.5 text-base font-medium transition-all duration-200 ease-linear text-slate-600 dropdown-item hover:text-custom-500 focus:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:focus:text-custom-500" href="/dashboard">
                                            <i data-lucide="layout-dashboard" class="inline-block size-4 ltr:mr-2 rtl:ml-2"></i>
                                            <span>{{ dashboardLabel }}</span>
                                        </a>
                                    <?php } else { ?> 
                                        <a class="block ltr:pr-4 rtl:pl-4 py-1.5 text-base font-medium transition-all duration-200 ease-linear text-slate-600 dropdown-item hover:text-custom-500 focus:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:focus:text-custom-500" href="/admin">
                                            <i data-lucide="sliders-horizontal" class="inline-block size-4 ltr:mr-2 rtl:ml-2"></i>
                                            <span>{{ controlPanelLabel }}</span>
                                        </a>
                                    <?php } ?>    
                                </li>
                                <?php } ?>
                                <li>
                                    <a class="block ltr:pr-4 rtl:pl-4 py-1.5 text-base font-medium transition-all duration-200 ease-linear text-slate-600 dropdown-item hover:text-custom-500 focus:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:focus:text-custom-500" href="/dashboard/profile">
                                        {% cache 'lucide:user-2' ttl=3600 %}<i data-lucide="user-2" class="inline-block size-4 ltr:mr-2 rtl:ml-2"></i>{% endcache %}
                                        <span>{{ editProfileLabel }}</span>
                                    </a>
                                </li>
                                <li class="pt-2 mt-2 border-t border-slate-200 dark:border-zink-500">
                                    <a class="block ltr:pr-4 rtl:pl-4 py-1.5 text-base font-medium transition-all duration-200 ease-linear text-slate-600 dropdown-item hover:text-custom-500 focus:text-custom-500 dark:text-zink-200 dark:hover:text-custom-500 dark:focus:text-custom-500" href="/logout">
                                        {% cache 'lucide:log-out' ttl=3600 %}<i data-lucide="log-out" class="inline-block size-4 ltr:mr-2 rtl:ml-2"></i>{% endcache %}
                                        <span>{{ signOutLabel }}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>


            </div>
        </div>
    </div>
</header>
<?php // var_dump($user);die;?>