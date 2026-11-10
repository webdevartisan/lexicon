<?php
/**
 * A small "?" that shows one line of help when tapped or clicked, for help
 * text that should not sit on the page all the time.
 *
 * Attributes:
 * - text: the help text, plain
 * - label: what the button is called for screen readers
 */
$text = (string) ($text ?? '');
$label = (string) ($label ?? 'More about this');
?>
<?php if ($text !== '') { ?>
<button type="button" data-tooltip data-tooltip-trigger="click" data-tooltip-content="<?= e($text) ?>"
    aria-label="<?= e($label) ?>"
    class="inline-flex size-5 shrink-0 items-center justify-center rounded-full align-middle text-slate-400 transition-colors hover:text-custom-500 focus:text-custom-500 focus:outline-none dark:text-zink-300">
    {% cache 'lucide:hint:help-circle' ttl=31536000 %}<i data-lucide="help-circle" class="size-3.5" aria-hidden="true"></i>{% endcache %}
</button>
<?php } ?>
