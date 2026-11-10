<?php
$discoverBlogsUrl = lurl('/discover?'.http_build_query(array_filter(['tab' => 'blogs', 'q' => $searchQuery])));
$discoverPostsUrl = lurl('/discover'.($searchQuery !== '' ? '?'.http_build_query(['q' => $searchQuery]) : ''));
$discoverSearchAction = lurl('/discover');
$discoverClearUrl = lurl($tab === 'blogs' ? '/discover?tab=blogs' : '/discover');
$discoverPlaceholder = $t($tab === 'posts' ? 'discover.searchPlaceholderPosts' : 'discover.searchPlaceholderBlogs');
?>

<section class="lx-bleed lx-discover-cover<?= empty($featuredPosts) ? ' is-compact' : '' ?>" aria-labelledby="discover-heading">
    <div class="lx-wrap">
        <div class="lx-discover-head">
            <div class="lx-discover-intro">
                <p class="lx-discover-eyebrow">{{ t('navigation.discover') }}</p>
                <h1 id="discover-heading">{{ t('discover.heroTitle') }}</h1>
                <p class="lx-discover-lede">{{ t('discover.heroSubtitle') }}</p>
            </div>

            <form method="get" action="<?= e($discoverSearchAction) ?>" class="lx-discover-search" role="search" aria-label="{{ t('discover.searchButton') }}">
                {% if ($tab === 'blogs'): %}
                    <input type="hidden" name="tab" value="blogs" />
                {% endif %}
                <label class="lx-visually-hidden" for="discover-q">{{ t('discover.searchButton') }}</label>
                <div class="lx-search-field<?= $searchQuery !== '' ? ' has-value' : ''; ?>">
                    <input type="search" name="q" id="discover-q" value="{{ searchQuery }}" placeholder="<?= e($discoverPlaceholder) ?>" />
                    {% if ($searchQuery !== ''): %}
                        <a href="<?= e($discoverClearUrl) ?>" class="lx-search-field-clear" aria-label="{{ t('discover.searchClear') }}">
                            {% cmp="lx-icon" name="x" %}
                        </a>
                    {% endif %}
                    <button type="submit" class="lx-search-field-submit" aria-label="{{ t('discover.searchButton') }}">
                        {% cmp="lx-icon" name="search" %}
                    </button>
                </div>
            </form>
        </div>

        {% include "partials/public/discover/_featured_posts.lex.php" %}
    </div>
</section>

<div class="lx-explore-toolbar">
    <div class="lx-explore-tabs" role="tablist">
        <a href="<?= e($discoverPostsUrl) ?>" role="tab"
           class="lx-explore-tab <?= $tab === 'posts' ? 'active' : '' ?>"
           aria-selected="<?= $tab === 'posts' ? 'true' : 'false' ?>">{{ t('discover.tabPosts') }}</a>
        <a href="<?= e($discoverBlogsUrl) ?>" role="tab"
           class="lx-explore-tab <?= $tab === 'blogs' ? 'active' : '' ?>"
           aria-selected="<?= $tab === 'blogs' ? 'true' : 'false' ?>">{{ t('discover.tabBlogs') }}</a>
    </div>
    {% if ($tab === 'posts'): %}
        <script src="<?= e(versioned_asset('/assets/js/post-view.js')) ?>"></script>
        <div class="lx-view-switch" role="group" aria-label="{{ t('discover.viewLabel') }}" data-post-view-switch hidden>
            <button type="button" class="lx-view-switch-btn" data-post-view-choice="grid" aria-pressed="true" title="{{ t('discover.viewGrid') }}">
                {% cmp="lx-icon" name="layout-grid" %}<span class="lx-visually-hidden">{{ t('discover.viewGrid') }}</span>
            </button>
            <button type="button" class="lx-view-switch-btn" data-post-view-choice="list" aria-pressed="false" title="{{ t('discover.viewList') }}">
                {% cmp="lx-icon" name="list" %}<span class="lx-visually-hidden">{{ t('discover.viewList') }}</span>
            </button>
        </div>
    {% endif %}
</div>

{% if (!empty($searchQuery)): %}
    <p class="lx-search-meta" role="status">
        <strong><?= (int) ($pagination['total'] ?? 0) ?></strong>
        {{ t('discover.resultsFor') }}
        <strong>"{{ searchQuery }}"</strong>
    </p>
{% endif %}
