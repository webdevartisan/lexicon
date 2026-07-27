{% extends "back.lex.php" %}

{% block title %}Front Page{% endblock %}
{% block subtitle %}Edit the public site text per language. Empty fields fall back to the built-in default.{% endblock %}

{% block body %}
<?php
$sectionTitleClass = 'text-base font-semibold text-slate-900 dark:text-zink-50 mb-1';
$sectionHintClass = 'text-sm text-slate-500 dark:text-zink-300 mb-5';

// Which tab starts open: ?tab= query, else 'hero'. Also drives the redirect after save.
$activeTab = $_GET['tab'] ?? 'hero';
if (!isset($tabs[$activeTab]) && $activeTab !== 'global') {
    $activeTab = 'hero';
}
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-5xl">

    <!-- Language switcher: shown only when a locale-aware tab is active. -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="flex flex-wrap items-center gap-2 locale-switcher" data-hide-on-global="1">
            <span class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-300">Language:</span>
            {% foreach ($locales as $lc): %}
            <a href="/admin/front-page?locale=<?= e($lc) ?>&tab=<?= e($activeTab === 'global' ? 'hero' : $activeTab) ?>"
               class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-medium rounded-md border transition-colors <?= $lc === $activeLocale ? 'border-custom-500 bg-custom-50 text-custom-700 dark:bg-custom-500/10 dark:text-custom-300' : 'border-slate-200 text-slate-500 hover:border-custom-300 hover:text-custom-500 dark:border-zink-500 dark:text-zink-300' ?>">
                <?= e(strtoupper($lc)) ?>
            </a>
            {% endforeach; %}
        </div>
        <p class="text-xs text-slate-400 dark:text-zink-300 flex items-center gap-1.5">
            <i data-lucide="info" class="size-3.5"></i>
            To add a language, edit <code class="px-1 rounded bg-slate-100 dark:bg-zink-700">config/localization.php</code> and add <code class="px-1 rounded bg-slate-100 dark:bg-zink-700">locales/&lt;code&gt;.json</code>.
        </p>
    </div>

    <!-- Tabs -->
    <div class="flex flex-wrap gap-2 mb-5 border-b border-slate-200 dark:border-zink-600" role="tablist">
        {% foreach ($tabs as $tabKey => $tab): %}
        <button type="button" role="tab" data-tab="<?= e($tabKey) ?>"
                class="tab-button inline-flex items-center gap-2 px-4 py-2.5 -mb-px text-sm font-medium border-b-2 border-transparent text-slate-500 dark:text-zink-300 hover:text-slate-700 dark:hover:text-zink-100 transition-colors <?= $tabKey === $activeTab ? 'active' : '' ?>">
            <i data-lucide="<?= e($tab['icon']) ?>" class="size-4"></i> <?= e($tab['label']) ?>
        </button>
        {% endforeach; %}
        <button type="button" role="tab" data-tab="global"
                class="tab-button inline-flex items-center gap-2 px-4 py-2.5 -mb-px text-sm font-medium border-b-2 border-transparent text-slate-500 dark:text-zink-300 hover:text-slate-700 dark:hover:text-zink-100 transition-colors <?= $activeTab === 'global' ? 'active' : '' ?>">
            <i data-lucide="<?= e($globalTab['icon']) ?>" class="size-4"></i> <?= e($globalTab['label']) ?>
        </button>
    </div>

    <!-- Localized tabs: one form per tab so a Save press only touches that section. -->
    {% foreach ($tabs as $tabKey => $tab): %}
    <div class="tab-content <?= $tabKey === $activeTab ? 'active' : '' ?>" data-section="<?= e($tabKey) ?>">
        <form method="POST" action="/admin/front-page" class="card">
            {{ csrf_field() }}
            <input type="hidden" name="locale" value="<?= e($activeLocale) ?>" />
            <input type="hidden" name="tab" value="<?= e($tabKey) ?>" />

            <div class="card-body">
                <?php foreach ($tab['groups'] as $groupLabel => $fields) { ?>
                <h3 class="<?= $sectionTitleClass ?>"><?= e($groupLabel) ?></h3>
                <p class="<?= $sectionHintClass ?>">Shown in <?= e(strtoupper($activeLocale)) ?>. Leave a field empty to use the built-in default.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-8">
                    <?php foreach ($fields as $field) {
                        // Unpack each field into simple vars so component placeholders work
                        $fName = $field['name'];
                        $fLabel = $field['label'];
                        $fValue = $field['value'];
                        $fDefault = $field['default'];
                        $fType = $field['type'];
                        ?>
                        <?php if ($fType === 'textarea') { ?>
                        <div class="md:col-span-2">
                            {% cmp="input" type="textarea" label="{$fLabel}" name="{$fName}" value="{$fValue}" placeholder="{$fDefault}" rows="3" %}
                            <?php if ($fDefault !== '') { ?>
                            <p class="mt-1 text-xs text-slate-400 dark:text-zink-300">Default: <?= e(truncate($fDefault, 140)) ?></p>
                            <?php } ?>
                        </div>
                        <?php } else { ?>
                        <div>
                            {% cmp="input" type="text" label="{$fLabel}" name="{$fName}" value="{$fValue}" placeholder="{$fDefault}" underlabel="{$fDefault}" %}
                        </div>
                        <?php } ?>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
            <div class="card-body flex justify-end border-t border-slate-100 dark:border-zink-600">
                {% cmp="btn" variant="blue" type="submit" icon="save" label="Save changes" %}
            </div>
        </form>
    </div>
    {% endforeach; %}

    <!-- Global tab: locale-independent contact details and social URLs. -->
    <div class="tab-content <?= $activeTab === 'global' ? 'active' : '' ?>" data-section="global">
        <form method="POST" action="/admin/front-page" class="card">
            {{ csrf_field() }}
            <input type="hidden" name="locale" value="<?= e($activeLocale) ?>" />
            <input type="hidden" name="tab" value="global" />

            <div class="card-body">
                <p class="<?= $sectionHintClass ?> flex items-center gap-2">
                    <i data-lucide="globe" class="size-4 text-custom-500"></i>
                    These values are shared across every language. Empty fields hide the element on the site.
                </p>

                <?php foreach ($globalTab['groups'] as $groupLabel => $fields) { ?>
                <h3 class="<?= $sectionTitleClass ?>"><?= e($groupLabel) ?></h3>
                <p class="<?= $sectionHintClass ?>"><?= e($groupLabel === 'Social links' ? 'Full URL for each network. Empty = icon hidden.' : 'Shown in the sidebar and used in system emails.') ?></p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-8">
                    <?php foreach ($fields as $field) {
                        $fName = $field['name'];
                        $fLabel = $field['label'];
                        $fValue = $field['value'];
                        $fType = $field['type'];
                        ?>
                        <?php if ($fType === 'textarea') { ?>
                        <div class="md:col-span-2">
                            {% cmp="input" type="textarea" label="{$fLabel}" name="{$fName}" value="{$fValue}" rows="2" %}
                        </div>
                        <?php } else { ?>
                        <div>
                            {% cmp="input" type="text" label="{$fLabel}" name="{$fName}" value="{$fValue}" %}
                        </div>
                        <?php } ?>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
            <div class="card-body flex justify-end border-t border-slate-100 dark:border-zink-600">
                {% cmp="btn" variant="blue" type="submit" icon="save" label="Save changes" %}
            </div>
        </form>
    </div>
</div>

<style>
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .tab-button.active { color: rgb(var(--tw-colors-custom-500, 59 130 246)); border-bottom-color: currentColor; }
    /* Global tab has no locale — hide the language switcher when it's active. */
    body[data-active-tab="global"] [data-hide-on-global="1"] { display: none; }
</style>
{% endblock %}

{% block scripts %}
<script nonce="<?= csp_nonce() ?>">
    // Tab switch without page reload; keep language switcher visible only on
    // the locale-aware tabs (the "Global" tab shares one row per key).
    document.addEventListener('DOMContentLoaded', () => {
        const tabs = document.querySelectorAll('.tab-button');
        const sections = document.querySelectorAll('.tab-content');
        const body = document.body;
        const activeSection = document.querySelector('.tab-content.active');
        if (activeSection) {
            body.dataset.activeTab = activeSection.dataset.section;
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                sections.forEach(section => {
                    section.classList.toggle('active', section.dataset.section === tab.dataset.tab);
                });
                body.dataset.activeTab = tab.dataset.tab;
            });
        });
    });
</script>
{% endblock %}
