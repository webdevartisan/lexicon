{% extends "back.lex.php" %}

{% block title %}Email Templates{% endblock %}
{% block subtitle %}Every email the site sends, the layout it uses and the languages it is written in.{% endblock %}

{% block body %}
<?php
$activeTab = 'emails';
$badge = static function (string $locale, array $language, string $href): string {
    [$class, $title] = match (true) {
        $language['problem'] !== null => ['bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300', 'Cannot be built as saved, so the shipped English is sent: '.$language['problem']],
        $language['outdated'] => ['bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300', 'The English was changed after this was saved; check it still says the same'],
        $language['source'] === 'built-in' => ['bg-slate-100 text-slate-600 dark:bg-zink-600 dark:text-zink-200', 'As shipped'],
        default => ['bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300', 'Written or changed in the control panel'],
    };

    return '<a href="'.e($href).'" class="inline-flex items-center px-1.5 py-0.5 text-xs font-medium uppercase rounded hover:ring-1 hover:ring-custom-500 '.$class.'" title="'.e($title).'">'.e($locale).'</a>';
};
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    {% include "areas/admin/Email/_shared.lex.php" %}

    <?php if (!empty($unregistered)) { ?>
    <!-- A Mailable exists in the codebase but is not registered, so it has no entry here -->
    <div class="flex items-start gap-3 px-4 py-3 mb-5 text-sm text-amber-800 border border-amber-200 rounded-md bg-amber-50 dark:bg-amber-500/10 dark:border-amber-500/30 dark:text-amber-200">
        <i data-lucide="alert-triangle" class="size-4 mt-0.5 shrink-0"></i>
        <div>
            <p class="font-medium">Some email classes are not listed here:</p>
            <ul class="mt-1 list-disc list-inside">
                <?php foreach ($unregistered as $class) { ?>
                <li><code class="text-xs"><?= e($class) ?></code></li>
                <?php } ?>
            </ul>
            <p class="mt-1">Add them to <code class="text-xs">EmailTemplateRegistry</code> with sample data to edit and test them here.</p>
        </div>
    </div>
    <?php } ?>

    <div class="flex flex-col gap-3 mb-5 md:flex-row md:items-center">
        <div class="grow md:max-w-xs">
            <label for="email-search" class="sr-only">Search emails</label>
            <input type="search" id="email-search" value="<?= e($search) ?>" placeholder="Search emails…" class="<?= e($fieldClass) ?>">
        </div>
        <p class="text-sm text-slate-500 dark:text-zink-300 md:ml-auto">
            Open an email to change its words in each language, preview it and send yourself a test.
        </p>
    </div>

    <div data-filter-rows class="space-y-6">
        <?php foreach ($groups as $group => $emails) { ?>
        <section class="card mb-0" data-filter-group>
            <div class="card-body">
                <h2 class="mb-3 text-base font-semibold text-slate-900 dark:text-zink-50"><?= e($group) ?></h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-slate-500 dark:text-zink-200 bg-slate-100 dark:bg-zink-600">
                            <tr>
                                <th class="px-3.5 py-2 font-semibold">Email</th>
                                <th class="px-3.5 py-2 font-semibold">Layout</th>
                                <th class="px-3.5 py-2 font-semibold">Languages</th>
                                <th class="px-3.5 py-2"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($emails as $short => $email) { ?>
                            <tr class="border-b border-slate-200 dark:border-zink-500 align-top"
                                data-search="<?= e(strtolower($email['name'].' '.$short.' '.$email['description'])) ?>" data-category="">
                                <td class="px-3.5 py-2.5">
                                    <a href="<?= e(lurl('/admin/email-templates/'.$short)) ?>" class="font-medium text-slate-900 hover:text-custom-500 dark:text-zink-50"><?= e($email['name']) ?></a>
                                    <p class="text-xs text-slate-500 dark:text-zink-300"><?= e($email['description']) ?></p>
                                </td>
                                <td class="px-3.5 py-2.5 whitespace-nowrap text-slate-600 dark:text-zink-200"><?= e($email['layout']) ?></td>
                                <td class="px-3.5 py-2.5">
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($siteLocales as $locale) {
                                            if (isset($email['languages'][$locale])) { ?>
                                        <?= $badge($locale, $email['languages'][$locale], lurl('/admin/email-templates/'.$short.'?locale='.$locale)) ?>
                                        <?php } else { ?>
                                        <a href="<?= e(lurl('/admin/email-templates/'.$short.'?locale='.$locale)) ?>" class="inline-flex items-center px-1.5 py-0.5 text-xs font-medium uppercase rounded border border-dashed border-slate-300 text-slate-400 hover:text-custom-500 dark:border-zink-500 dark:text-zink-300"
                                              title="Not written in this language yet; readers of it get the site's default language, else English. Click to write it."><?= e($locale) ?></a>
                                        <?php } ?>
                                        <?php } ?>
                                    </div>
                                </td>
                                <td class="px-3.5 py-2.5 text-right whitespace-nowrap">
                                    <a href="<?= e(lurl('/admin/email-templates/'.$short)) ?>" class="inline-flex items-center gap-1 px-2 py-1 text-slate-500 hover:text-custom-500">
                                        <i data-lucide="pencil" class="size-4"></i> Edit<span class="sr-only"> <?= e($email['name']) ?></span>
                                    </a>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <?php } ?>
    </div>
    <p data-filter-empty hidden class="py-8 text-center text-sm text-slate-500">No emails match.</p>
</div>

<script nonce="<?= csp_nonce() ?>">
(function () {
    // Filters the rows as rendered, so there is no round trip; ?q= prefills it.
    var search = document.getElementById('email-search');
    var empty = document.querySelector('[data-filter-empty]');

    function apply() {
        var term = search.value.trim().toLowerCase();
        var shown = 0;

        document.querySelectorAll('[data-filter-rows] [data-search]').forEach(function (row) {
            var match = term === '' || row.getAttribute('data-search').indexOf(term) !== -1;
            row.hidden = !match;
            shown += match ? 1 : 0;
        });

        // Group headings hide when every row under them is filtered out.
        document.querySelectorAll('[data-filter-group]').forEach(function (group) {
            group.hidden = group.querySelector('[data-search]:not([hidden])') === null;
        });

        empty.hidden = shown !== 0;
    }

    search.addEventListener('input', apply);
    apply();
})();
</script>
{% endblock %}
