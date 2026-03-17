<?php
/**
 * Breadcrumb Navigation Component
 *
 * render breadcrumb trail with SEO-friendly structured data
 * and PHP server-side translations via the $t() function.
 * Automatically hidden if no breadcrumbs are set.
 *
 * No parameters needed - gets data from breadcrumbs() helper automatically.
 */
$items = breadcrumbs()->get();

if (!empty($items)) { ?>

<nav aria-label="Breadcrumb" class="">
    <ol class="flex flex-wrap items-center gap-2 text-sm" itemscope itemtype="https://schema.org/BreadcrumbList">
        <?php foreach ($items as $index => $item) { ?>
            <li class="flex items-center gap-2" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                
                <?php if ($item['url'] !== null) { ?>
                    <!-- Linked breadcrumb item -->
                    <a 
                        href="<?= e($item['url']) ?>" 
                        class="text-slate-600 hover:text-custom-500 dark:text-zink-300 dark:hover:text-custom-400 transition-colors duration-200"
                        itemprop="item"
                    >
                        <span itemprop="name">
                            <?php
                            // render server-side translation with fallback to label
                            echo !empty($item['key']) ? $t($item['key']) : e($item['label']);
                    ?>
                        </span>
                    </a>
                <?php } else { ?>
                    <!-- Current page (no link) -->
                    <span 
                        class="text-slate-900 dark:text-zink-100 font-medium" 
                        itemprop="name"
                    >
                        <?php
                        // render server-side translation with fallback to label
                        echo !empty($item['key']) ? $t($item['key']) : e($item['label']);
                    ?>
                    </span>
                <?php } ?>
                
                <!-- Separator (not shown on last item) -->
                <?php if ($index < count($items) - 1) { ?>
                    <i data-lucide="chevron-right" class="size-4 text-slate-400 dark:text-zink-500"></i>
                <?php } ?>
                
                <!-- Structured data position -->
                <meta itemprop="position" content="<?= $index + 1 ?>">
            </li>
        <?php } ?>
    </ol>
</nav>

<?php } ?>
