{% extends "back.lex.php" %}

{% block title %}Edit Blog{% endblock %}
{% block subtitle %}Update the blog's identity and visibility.{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-3xl">
    {% include "areas/admin/_errors.lex.php" %}

    <form method="post" action="/admin/blogs/<?= e((string) ($blog['id'] ?? '')) ?>/update" class="card">
        {{ csrf_field() }}
        <div class="card-body">
            {% include "areas/admin/Blog/form.lex.php" %}
        </div>
        {% cmp="form-footer" cancelHref="/admin/blogs" submitLabel="Save Changes" submitIcon="save" %}
    </form>

    <div class="card" id="ownership">
        <div class="card-body">
            <h6 class="mb-1 text-15">Owner</h6>
            <p class="text-sm text-slate-500 dark:text-zink-200 mb-4">
                Owned by <strong><?= e((string) ($owner['handle'] ?? '')) ?></strong>.
                <?php if (empty($members)) { ?>Ownership can only go to a collaborator, and this blog has none.<?php } else { ?>Hand it to a collaborator; the current owner stays on as an editor.<?php } ?>
            </p>
            <?php if (!empty($members)) { ?>
            <form method="post" action="/admin/blogs/<?= (int) $blog['id'] ?>/transfer" class="flex flex-wrap items-end gap-3">
                {{ csrf_field() }}
                <div>
                    <label for="new_owner_id" class="inline-block mb-2 text-base font-medium">New owner</label>
                    <select id="new_owner_id" name="new_owner_id" required
                        class="form-select border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700">
                        <?php foreach ($members as $m) { ?>
                            <option value="<?= (int) $m['user_id'] ?>"><?= e($m['handle']) ?> (<?= e((string) $m['role']) ?>)</option>
                        <?php } ?>
                    </select>
                </div>
                <?php $transferConfirm = 'data-confirm="'.e('Transfer ownership of this blog?').'"'; ?>
                {% cmp="btn" type="submit" variant="red" icon="key-round" label="Transfer ownership" dataBtn="{$transferConfirm}" %}
            </form>
            <?php } ?>
        </div>
    </div>
</div>
{% endblock %}
