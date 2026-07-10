{% extends "back.lex.php" %}

{% block title %}Pages{% endblock %}
{% block subtitle %}Static pages: about, contact, policies and the getting started guides.{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-5xl">

    <div class="card">
        <div class="card-body p-0 overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead class="text-left bg-slate-100 dark:bg-zink-600">
                    <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                        <th class="px-3.5 py-2.5 font-semibold">Page</th>
                        <th class="px-3.5 py-2.5 font-semibold">Translations</th>
                        <th class="px-3.5 py-2.5 font-semibold text-right">Add translation</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                    {% foreach ($pagesBySlug as $slug => $rows): %}
                    <?php
                        $existingLocales = array_column($rows, 'locale');
                    $missingLocales = array_diff($locales, $existingLocales);
                    // New translations copy from the English row when one exists
                    $sourceRow = $rows[0];
                    foreach ($rows as $row) {
                        if ($row['locale'] === 'en') {
                            $sourceRow = $row;
                            break;
                        }
                    }
                    $firstId = (int) $sourceRow['id'];
                    $pageTitle = (string) $sourceRow['title'];
                    ?>
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zink-700/40 transition-colors">
                        <td class="px-3.5 py-2.5">
                            <span class="font-medium text-slate-900 dark:text-zink-50"><?= e($pageTitle) ?></span>
                            <span class="block text-xs text-slate-400 dark:text-zink-300">/<?= e($slug) ?></span>
                        </td>
                        <td class="px-3.5 py-2.5">
                            <div class="flex items-center gap-2">
                                {% foreach ($rows as $row): %}
                                <a href="/admin/pages/<?= (int) $row['id'] ?>/edit"
                                   class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-md border transition-colors <?= !empty($row['is_published']) ? 'border-green-200 text-green-700 bg-green-50 hover:bg-green-100 dark:border-green-800 dark:text-green-300 dark:bg-green-900/20' : 'border-slate-200 text-slate-500 bg-slate-50 hover:bg-slate-100 dark:border-zink-500 dark:text-zink-300 dark:bg-zink-600' ?>">
                                    <?= e(strtoupper((string) $row['locale'])) ?>
                                    <?php if (empty($row['is_published'])) { ?><span>(draft)</span><?php } ?>
                                </a>
                                {% endforeach; %}
                            </div>
                        </td>
                        <td class="px-3.5 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                {% foreach ($missingLocales as $ml): %}
                                <form method="POST" action="/admin/pages/<?= $firstId ?>/translate">
                                    {{ csrf_field() }}
                                    <input type="hidden" name="locale" value="<?= e($ml) ?>" />
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-md border border-dashed border-slate-300 text-slate-500 hover:text-custom-500 hover:border-custom-300 transition-colors dark:border-zink-500 dark:text-zink-300">
                                        <i data-lucide="plus" class="size-3"></i> <?= e(strtoupper($ml)) ?>
                                    </button>
                                </form>
                                {% endforeach; %}
                            </div>
                        </td>
                    </tr>
                    {% endforeach; %}
                </tbody>
            </table>
        </div>
    </div>
</div>
{% endblock %}
