{% extends "back.lex.php" %}

{% block title %}Emails{% endblock %}
{% block subtitle %}Every email the site sends, the template it uses and whether it currently builds.{% endblock %}

{% block body %}
<?php
$activeTab = 'emails';
$basePath = '/admin/email-templates/emails';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    {% include "areas/admin/EmailTemplate/_tabs.lex.php" %}

    <div class="flex flex-col gap-3 mb-5 md:flex-row md:items-center">
        <div class="grow md:max-w-xs">
            <label for="email-search" class="sr-only">Search emails</label>
            <input type="search" id="email-search" value="<?= e($search) ?>" placeholder="Search emails…" class="<?= e($fieldClass) ?>">
        </div>
        <p class="text-sm text-slate-500 dark:text-zink-300 md:ml-auto">
            Change an email's wording here. Its look comes from its template.
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
                                <th class="px-3.5 py-2 font-semibold">Template</th>
                                <th class="px-3.5 py-2 font-semibold">Wording</th>
                                <th class="px-3.5 py-2 font-semibold">Status</th>
                                <th class="px-3.5 py-2"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($emails as $short => $email) { ?>
                            <tr class="border-b border-slate-200 dark:border-zink-500 align-top"
                                data-search="<?= e(strtolower($email['name'].' '.$short.' '.$email['description'])) ?>" data-category="">
                                <td class="px-3.5 py-2.5">
                                    <a href="<?= e(lurl($basePath.'/'.$short.'/edit')) ?>" class="font-medium text-slate-900 hover:text-custom-500 dark:text-zink-50"><?= e($email['name']) ?></a>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-zink-300 max-w-md"><?= e($email['description']) ?></p>
                                </td>
                                <td class="px-3.5 py-2.5 whitespace-nowrap">
                                    <?php if ($email['template'] === null) { ?>
                                    <span class="text-red-600">None</span>
                                    <?php } else { ?>
                                    <a class="hover:text-custom-500" href="<?= e(lurl('/admin/email-templates/'.$email['template']['slug'].'/edit')) ?>"><?= e($email['template']['label']) ?></a>
                                    <?php } ?>
                                </td>
                                <td class="px-3.5 py-2.5 whitespace-nowrap"><?= $sourceBadge($email['state']) ?></td>
                                <td class="px-3.5 py-2.5">
                                    <?php if ($email['problem'] === null) { ?>
                                    <span class="inline-flex items-center gap-1 text-green-600 dark:text-green-400"><i data-lucide="check-circle" class="size-4"></i> Builds</span>
                                    <?php } else { ?>
                                    <span class="inline-flex items-start gap-1 text-red-600 dark:text-red-400"><i data-lucide="alert-circle" class="size-4 shrink-0 mt-0.5"></i> <?= e($email['problem']) ?></span>
                                    <?php } ?>
                                </td>
                                <td class="px-3.5 py-2.5 text-right whitespace-nowrap">
                                    <a href="<?= e(lurl($basePath.'/'.$short.'/edit')) ?>" class="inline-flex items-center gap-1 px-2 py-1 text-slate-500 hover:text-custom-500">
                                        <i data-lucide="pencil" class="size-4"></i> Edit<span class="sr-only"> <?= e($email['name']) ?></span>
                                    </a>
                                    <a href="<?= e(lurl('/admin/email-test/preview?template='.urlencode($email['sample']))) ?>" class="inline-flex items-center gap-1 px-2 py-1 text-slate-500 hover:text-custom-500" title="Preview the real email and send yourself a test">
                                        <i data-lucide="send" class="size-4"></i> Test<span class="sr-only"> <?= e($email['name']) ?></span>
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

{% include "areas/admin/EmailTemplate/_filter.lex.php" %}
{% endblock %}
