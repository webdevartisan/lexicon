{% extends "back.lex.php" %}
{% block title %}Blog Invitation{% endblock %}
{% block subtitle %}Join a blog as a collaborator.{% endblock %}
{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    <div class="max-w-xl mx-auto">
        <section class="card">
            <div class="card-body text-center">
                <div class="mx-auto mb-4 inline-flex items-center justify-center size-12 rounded-full bg-custom-100 text-custom-600 dark:bg-custom-500/20 dark:text-custom-300">
                    <i data-lucide="mail-open" class="size-6"></i>
                </div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-zink-50 mb-2">{{ blogName }}</h2>
                <p class="text-sm text-slate-500 dark:text-zink-300">
                    {% if inviterName|notempty %}
                    <strong>{{ inviterName }}</strong> invited you to collaborate as
                    {% else %}
                    You have been invited to collaborate as
                    {% endif %}
                    <strong>{{ roleLabel }}</strong>.
                </p>
                {% if roleDescription|notempty %}
                <p class="mt-2 text-sm text-slate-500 dark:text-zink-300">{{ roleDescription }}</p>
                {% endif %}

                {% if wrongAccount %}
                <div class="mt-6 px-4 py-3 text-sm text-left rounded-md border border-amber-300 bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-200">
                    This invitation was sent to <strong>{{ invitedEmail }}</strong>, but you are signed in as <strong>{{ accountEmail }}</strong>.
                    Sign out and open the link again with the invited account to accept it.
                </div>
                {% else %}
                <div class="flex flex-wrap items-center justify-center gap-3 mt-6">
                    <form method="POST" action="/invite/{{ token }}/accept" class="m-0">
                        {{ csrf_field() }}
                        {% cmp="btn" type="submit" variant="green" icon="check" label="Accept" %}
                    </form>
                    <form method="POST" action="/invite/{{ token }}/decline" class="m-0">
                        {{ csrf_field() }}
                        {% cmp="btn" type="submit" variant="slate" icon="x" label="Decline" %}
                    </form>
                </div>
                {% endif %}
            </div>
        </section>
    </div>
</div>
{% endblock %}
