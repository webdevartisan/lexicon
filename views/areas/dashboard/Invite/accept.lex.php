{% extends "back.lex.php" %}
{% block title %}Blog Invitation{% endblock %}
{% block subtitle %}You've been invited to collaborate on a blog.{% endblock %}
{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    <div class="max-w-xl mx-auto">
        <section class="card">
            <div class="card-body text-center">
                <div class="mx-auto mb-4 inline-flex items-center justify-center size-12 rounded-full bg-custom-100 text-custom-600 dark:bg-custom-500/20 dark:text-custom-300">
                    <i data-lucide="mail-open" class="size-6"></i>
                </div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-zink-50 mb-2">You've been invited</h2>
                <p class="text-sm text-slate-500 dark:text-zink-300 mb-6">
                    You've been invited to collaborate as <strong class="capitalize">{{ invite.role }}</strong>.
                </p>

                <div class="flex flex-wrap items-center justify-center gap-3">
                    <form method="POST" action="/invite/{{ token }}/accept" class="m-0">
                        {{ csrf_field() }}
                        {% cmp="btn" type="submit" variant="green" icon="check" label="Accept" %}
                    </form>
                    <form method="POST" action="/invite/{{ token }}/decline" class="m-0">
                        {{ csrf_field() }}
                        {% cmp="btn" type="submit" variant="slate" icon="x" label="Decline" %}
                    </form>
                </div>

                <p class="mt-6 text-xs text-slate-500 dark:text-zink-300">
                    Declining will notify the blog owner.
                </p>
            </div>
        </section>
    </div>
</div>
{% endblock %}
