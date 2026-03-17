## Template Engine

Lexicon ships with a custom template engine inspired by Twig and Blade. Templates are compiled into native PHP and typically use the `.lex.php` extension.

---

## Variable Output

Use `{{ ... }}` for escaped output:

```twig
<p>{{ post.title }}</p>
```

Compiles to:

```php
<p><?= htmlspecialchars($post['title'] ?? '') ?></p>
```

### Dot notation

Dot notation is converted to nested array access:

```twig
{{ post.user.name }}
```

→ `<?= htmlspecialchars($post['user']['name'] ?? '') ?>`

### Raw output

Use `|raw` to disable escaping:

```twig
{{ post.content|raw }}
```

→ `<?= $post['content'] ?? '' ?>`

---

## Conditionals

Basic conditionals:

```twig
{% if post.published %}
    <p>Published</p>
{% endif %}
```

Compiles roughly to:

```php
<?php if ($post['published']): ?>
    <p>Published</p>
<?php endif; ?>
```

For full PHP expressions, wrap them in parentheses:

```twig
{% if ($user['role'] === 'author') %}
    ...
{% endif %}
```

### Modifier support

Modifiers can be used for forgiving checks:

| Template                | Output example                         |
|-------------------------|----------------------------------------|
| `{% if errors|empty %}` | `<?php if (!empty($errors)): ?>`       |
| `{% if user|isset %}`   | `<?php if (isset($user)): ?>`          |
| `{% if items|count %}`  | `<?php if (count($items)): ?>`         |
| `{% if message|trim %}` | `<?php if (trim($message)): ?>`        |

---

## Loops

Simplified `for ... in ...` syntax:

```twig
{% for error in errors %}
    <li>{{ error }}</li>
{% endfor %}
```

Compiles roughly to:

```php
<?php foreach ($errors as $error): ?>
    <li><?= htmlspecialchars($error ?? '') ?></li>
<?php endforeach; ?>
```

You can also use native PHP loop syntax:

```twig
{% for ($i = 0; $i < 5; $i++) %}
    <p>Item {{ i }}</p>
{% endfor %}
```

---

## Control Structures

Common directives map directly to PHP:

| Template              | PHP                            |
|-----------------------|--------------------------------|
| `{% if ... %}`        | `<?php if (...) : ?>`          |
| `{% elseif ... %}`    | `<?php elseif (...) : ?>`      |
| `{% else %}`          | `<?php else: ?>`               |
| `{% endif %}`         | `<?php endif; ?>`              |
| `{% endfor %}`        | `<?php endforeach; ?>`         |

---

## Layouts and Blocks

You can extend a base layout and define blocks:

```twig
{% extends "front.lex.php" %}

{% block title %}Welcome to...{% endblock %}

{% block head %}
  <link rel="stylesheet" href="/assets/vendor/flatpickr.css">
{% endblock %}

{% block body %}
Body HTML goes here
{% endblock %}

{% block scripts %}
  <script src="/assets/vendor/flatpickr.js" defer></script>
  <script>flatpickr('[data-datepicker]');</script>
{% endblock %}
```

The engine will inject these blocks into the base layout’s corresponding sections.

---

## Error Handling Strategy

- Use simple `if` checks for typical truthy/falsey logic.
- Use modifiers like `|empty` and `|isset` when you want guard rails against undefined variables.
- Wrap complex conditions in parentheses to avoid ambiguity.

For more examples, see existing `.lex.php` templates under `views/` and `themes/*/views/`.

