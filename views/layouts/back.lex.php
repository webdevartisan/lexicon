<?php
$flash = flash();
$errors = errors();
$old = old();
?>
<!DOCTYPE html>
<html lang="{{ chromeLang }}" class="dark scrollbar-gutter-stable scroll-smooth group" data-layout="vertical" data-sidebar="dark" data-sidebar-size="lg" data-mode="dark" data-topbar="dark" dir="{{ chromeDir }}">

<head>

    <meta charset="utf-8">
    <title>{% yield title %} | Lexicon CP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="/assets/icon/favicon.ico">
    <script src="/cp-assets/js/layout.js"></script>

    <link rel="stylesheet" href="/cp-assets/css/vendors/simplebar.css">
    <link rel="stylesheet" href="/cp-assets/css/fonts.css">
    <link rel="stylesheet" href="/cp-assets/css/cp.css">
    {% yield head %}

</head>

<body class="text-base bg-body-bg text-body font-public dark:text-zink-100 dark:bg-zink-800 group-data-[skin=bordered]:bg-body-bordered group-data-[skin=bordered]:dark:bg-zink-700">
<div class="group-data-[sidebar-size=sm]:min-h-sm group-data-[sidebar-size=sm]:relative">

    
    <div class="app-menu w-vertical-menu bg-vertical-menu ltr:border-r rtl:border-l border-vertical-menu-border fixed bottom-0 top-0 z-[1003] transition-all duration-75 ease-linear group-data-[sidebar-size=md]:w-vertical-menu-md group-data-[sidebar-size=sm]:w-vertical-menu-sm group-data-[sidebar-size=sm]:pt-header group-data-[sidebar=dark]:bg-vertical-menu-dark group-data-[sidebar=dark]:border-vertical-menu-dark group-data-[sidebar=brand]:bg-vertical-menu-brand group-data-[sidebar=brand]:border-vertical-menu-brand group-data-[sidebar=modern]:bg-gradient-to-tr group-data-[sidebar=modern]:to-vertical-menu-to-modern group-data-[sidebar=modern]:from-vertical-menu-form-modern hidden md:block print:hidden group-data-[sidebar-size=sm]:absolute group-data-[sidebar=modern]:border-vertical-menu-border-modern group-data-[sidebar=dark]:dark:bg-zink-700 group-data-[sidebar=dark]:dark:border-zink-600">
        <div class="flex items-center justify-center px-5 text-center h-header group-data-[sidebar-size=sm]:fixed group-data-[sidebar-size=sm]:top-0 group-data-[sidebar-size=sm]:bg-vertical-menu group-data-[sidebar-size=sm]:group-data-[sidebar=dark]:bg-vertical-menu-dark group-data-[sidebar-size=sm]:group-data-[sidebar=brand]:bg-vertical-menu-brand group-data-[sidebar-size=sm]:group-data-[sidebar=modern]:bg-gradient-to-br group-data-[sidebar-size=sm]:group-data-[sidebar=modern]:to-vertical-menu-to-modern group-data-[sidebar-size=sm]:group-data-[sidebar=modern]:from-vertical-menu-form-modern group-data-[sidebar-size=sm]:group-data-[sidebar=modern]:bg-vertical-menu-modern group-data-[sidebar-size=sm]:z-10 group-data-[sidebar-size=sm]:w-[calc(theme('spacing.vertical-menu-sm')_-_1px)] group-data-[sidebar-size=sm]:group-data-[sidebar=dark]:dark:bg-zink-700">
            
            {% cmp="logo" %}

            <button type="button" class="hidden p-0 float-end" id="vertical-hover">
                <i class="ri-record-circle-line"></i>
            </button>
        </div>
    
        <div id="scrollbar" class="group-data-[sidebar-size=md]:max-h-[calc(100vh_-_theme('spacing.header')_*_1.2)] group-data-[sidebar-size=lg]:max-h-[calc(100vh_-_theme('spacing.header')_*_1.2)]">
            <div>
                <ul class="" id="navbar-nav">
                    <!-- Menu items here -->
                    {% include "partials/_back_side_bar_items.lex.php" %}
                </ul>
            </div>
            <!-- Sidebar -->
        </div>
    </div>
    <!-- Left Sidebar End -->

    <div id="sidebar-overlay" class="absolute inset-0 z-[1002] bg-slate-500/30 hidden"></div>
    <!-- header -->
    {% include "partials/_back_header.lex.php" %}
    
    <!-- Page -->
    <div class="min-h-screen flex flex-col group-data-[sidebar-size=sm]:min-h-sm">
        <!-- Start Page-content -->
        <div class="flex-1 group-data-[sidebar-size=lg]:ltr:md:ml-vertical-menu group-data-[sidebar-size=lg]:rtl:md:mr-vertical-menu group-data-[sidebar-size=md]:ltr:ml-vertical-menu-md group-data-[sidebar-size=md]:rtl:mr-vertical-menu-md group-data-[sidebar-size=sm]:ltr:ml-vertical-menu-sm group-data-[sidebar-size=sm]:rtl:mr-vertical-menu-sm pt-[calc(theme('spacing.header')_*_1)] pb-[calc(theme('spacing.header')_*_0.8)] px-4">        
            <div class="container group-data-[content=boxed]:max-w-boxed mx-auto">

                <?php /* Only admins see this, and only while the site is offline for visitors */ ?>
                <?php if (auth()->check() && auth()->hasRole('administrator') && \App\Services\MaintenanceMode::active()) { ?>
                <div class="flex items-center gap-2 px-4 py-2.5 mt-4 text-sm rounded-md border border-amber-300 bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-200 print:hidden">
                    <i data-lucide="hard-hat" class="size-4 shrink-0"></i>
                    <span class="grow">Maintenance mode is on. Visitors currently see the maintenance page.</span>
                    <a href="/admin/settings" class="font-medium underline hover:no-underline">Turn off</a>
                </div>
                <?php } ?>

                {% if noBreadcrumb|empty %}
                <div class="flex flex-col gap-2 py-4 md:flex-row md:items-center print:hidden">
                    <div class="grow">
                        <h5 class="text-2xl font-semibold">
                            {% if (empty($hideTitle)): %}
                                {% yield title %}
                            {% endif; %}
                        </h5>
                        <p class="text-sm text-slate-500 dark:text-zink-300">
                            {% yield subtitle %}
                        </p>
                    </div>
                    <!-- Breadcrumb -->
                    {% include "partials/_breadcrumbs.lex.php" %}
                    {% if (!empty($breadcrumb)): %}
                    <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                        <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1  before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                            <a href="#!" class="text-slate-400 dark:text-zink-200">Navigation</a>
                        </li>
                        <li class="text-slate-700 dark:text-zink-100">
                            Navbar
                        </li>
                    </ul>
                    {% endif; %}
                </div>
                {% endif %}
                
                <!-- Content here -->
                 {% yield body %}

            </div>
            <!-- container-fluid -->
        </div>
        <!-- End Page-content -->

        <?php /* The footer is static, so the sidebar offset has to be a margin.
                 It used to carry `left-vertical-menu`, which does nothing without
                 positioning and left the copyright sitting under the fixed sidebar. */ ?>
        <footer class="group-data-[sidebar-size=lg]:ltr:md:ml-vertical-menu group-data-[sidebar-size=lg]:rtl:md:mr-vertical-menu group-data-[sidebar-size=md]:ltr:ml-vertical-menu-md group-data-[sidebar-size=md]:rtl:mr-vertical-menu-md group-data-[sidebar-size=sm]:ltr:ml-vertical-menu-sm group-data-[sidebar-size=sm]:rtl:mr-vertical-menu-sm px-4 h-14 border-t py-3 flex items-center dark:border-zink-600 print:hidden">
            <div class="w-full">
                <div class="grid items-center grid-cols-1 gap-1 text-center lg:grid-cols-2 text-slate-400 dark:text-zink-200 ltr:lg:text-left rtl:lg:text-right">
                    <div>
                        &copy; <?= e(env('APP_NAME', 'Lexicon')) ?> <?= date('Y') ?>
                    </div>
                    <?php /* Both targets are rows in the `pages` table, edited at /admin/pages. */ ?>
                    <div class="ltr:lg:text-right rtl:lg:text-left">
                        <a href="<?= e(lurl('/terms')) ?>" class="hover:text-custom-500 transition-colors">{{ t('footer.linkTerms') }}</a>
                        <span class="px-1.5" aria-hidden="true">|</span>
                        <a href="<?= e(lurl('/contact')) ?>" class="hover:text-custom-500 transition-colors">{{ t('footer.linkContact') }}</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    
</div>
<!-- end main content -->

<!-- Flash toasts, fixed overlay so they never move the page -->
{% if flash|notempty %}
<div id="toast-stack" class="fixed top-[calc(theme('spacing.header')_+_1rem)] ltr:right-4 rtl:left-4 z-[1050] flex flex-col gap-3 w-80 max-w-[calc(100vw_-_2rem)] pointer-events-none print:hidden">
    {% foreach ($flash as $type => $messages): %}
        {% foreach ($messages as $msg): %}
            {% cmp="msg" type="{$type}" msg="{$msg}" %}
        {% endforeach %}
    {% endforeach %}
</div>
{% endif %}

<script src="/cp-assets/libs/%40popperjs/core/umd/popper.min.js"></script>
<script src="/cp-assets/libs/tippy.js/tippy-bundle.umd.min.js"></script>
<script src="/cp-assets/libs/simplebar/simplebar.min.js"></script>
<!-- <script src="/cp-assets/libs/prismjs/prism.js"></script> -->
<script src="/cp-assets/js/dropdown.js"></script>
<script src="/cp-assets/js/blog-switcher.js"></script>
<script src="/cp-assets/js/table-sort.js"></script>

{% yield scripts %}

<!-- App js -->
<script src="/cp-assets/js/sidebar-state-manager.js"></script>
<script src="/cp-assets/js/components/alert.js"></script>
<script src="/cp-assets/js/app.js"></script>
<script src="/cp-assets/js/lucide.init.js"></script>

</body>

</html>