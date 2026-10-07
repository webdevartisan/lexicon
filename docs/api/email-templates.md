# Email Templates

Every email the site sends is **its words in a layout**:

| Part | What it is | Shipped as | Saved edits in |
|---|---|---|---|
| **Layout** | A whole HTML document: head, outer wrapper, header, footer, and `{{ content }}` where the email goes. No words of its own, since it is not translated. | `resources/mail/layouts/{slug}.html` | `email_layouts` |
| **Email** | Per language: a subject, a preheader, a body (the table rows that go into the layout) and a footer note. | `resources/mail/emails/{Mailable}.html`, English only | `email_contents`, one row per language |
| **Layout choice** | Which layout an email uses, in every language. | the email file's `layout:` field | `email_settings` |
| **Data** | The facts the email is about: names, titles, links. | the Mailable's `build()` | never stored |

There is no logic in a template, only `{{ placeholders }}`. Where an email needs different words for a different
situation it is a different Mailable class (see `CommentMail::for()` and `ModerationWarningMail::for()`), so each
situation can be worded, and translated, on its own.

## Shipped files and saved edits

The shipped files are the defaults. The tables only hold what was changed or added in the control panel:

- a layout row whose slug matches a shipped file replaces it (`customized`); a new slug is a new layout (`custom`);
- an English content row replaces the shipped English; any other language exists **only** as a row;
- deleting a row is "reset to default".

A fresh install has empty tables and still sends every email. An improvement to a shipped email reaches every
site that has not changed that email. `EmailContentRepository` reads all three tables once per process (a
subscriber fan-out renders the same email thousands of times) and lays them over `ShippedEmailSource`.

### File format

```html
---
subject: Reset your {{ app_name }} password
preheader: Use this link within {{ expires_minutes }} minutes to choose a new password.
footer_note: You received this email because someone asked to reset the password on your {{ app_name }} account.
layout: default
---
          <tr>
            <td class="section-padding" style="padding:32px 40px 8px;">
              <h1 ...>Reset your password</h1>
            </td>
          </tr>
          ...
```

One `name: value` per line between the `---` lines; everything after is the body. A layout file has `name`,
`primary_color`, `background_color`, `support_email` and `company_address` instead. An email that repeats a
section (the weekly digest, once per blog) has a second file, `{Mailable}.repeat.html`.

Bodies are table rows in the style of the default layout, so they hold up in Outlook: buttons have a VML
`v:roundrect` for Outlook and a table button for everyone else, and widths collapse on phones through the
classes the layout's `<style>` defines (`section-padding`, `main-heading`, `mobile-button`).

## Placeholders

| Placeholder | Where | Value |
|---|---|---|
| The email's data, e.g. `{{ post_title }}` | subject, preheader, body, footer note, layout | what `build()` passed |
| `app_name`, `app_url`, `year` | everywhere | |
| `lang`, `dir`, `start`, `end` | everywhere | the language, `ltr`/`rtl`, and `left`/`right` for the reading direction |
| `preferences_url` | everywhere | the account's email settings; an email may pass its own (`NewPostMail` passes its unsubscribe link) |
| `primary_color`, `background_color`, `support_email`, `company_address` | everywhere | the layout's settings; an empty support email is the From address |
| `content`, `footer_note`, `preheader`, `subject` | layout only | the email's own parts |
| A repeated section's data, e.g. `{{ blog_name }}` | that section only | one row of what the email passed to `repeat()` |

Spaces inside the braces are optional: `{{app_name}}` and `{{ app_name }}` are the same.

## Writing a Mailable

A Mailable supplies data, never words or markup. `build()` sets the recipient and calls `fromTemplate()` last:

```php
public function build(): void
{
    $this->to($this->toEmail)
        ->fromTemplate([
            'post_title' => $this->postTitle,
            'reviewer_handle' => $this->reviewerHandle,
            'post_url' => $this->url('/dashboard/posts/'.$this->postId.'/review'),
        ]);
}
```

- The subject is part of the email's words, so a Mailable never calls `subject()`.
- Strings are escaped. Pass `HtmlFragment::fromText()` for text whose line breaks should survive, such as a
  message someone wrote.
- Numbers and dates go through `$this->number()` and `$this->date($date, 'yMMMd')`, which write them the way
  the email's language does (1,240 or 1.240; "Sep 28" or "28 Σεπ").
- Counts are numbers next to labels in the words ("Views: 1,240"), never counted phrases ("1,240 views" and "1
  view"), so no language needs plural rules in the data.
- A section the email repeats is filled with `$this->repeat($rows)`, which returns an `HtmlFragment` to pass
  to `fromTemplate()`; see `InsightsDigestMail`.

A new email needs its class, a file in `resources/mail/emails/`, and an entry in `EmailTemplateRegistry` with
sample data. `EmailDefaultsTest` fails until all three exist and the file builds strictly from the sample.

## Languages

Every email is built in its recipient's language. The code that sends it picks the language with
`RecipientLocale` and builds the Mailable inside `Mailable::inLocale()`:

```php
$mail = Mailable::inLocale($this->locales->forUser($userId), fn () => new PostApprovedMail(...));
```

Outside `inLocale()` the site default is used. The queue stores each email already rendered, so the language
is settled when it is queued.

An email is only written in a language it has words in. The constructor settles it, before `build()`, so numbers
and dates match the words (`EmailRenderer::localeFor()`): the language asked for if the email has words in it,
else the site default if it has words in that, else English, which always ships.

`RecipientLocale` picks, first that applies:

1. the language the person chose in their preferences (`user_preferences.locale`);
2. the page they are reading right now, but only when they are the one acting (registration, the
   forgot-password form, changing their own email, subscribing). Callers pass it as `$readingNow`, because the
   person who triggers an email is often not its reader: an admin's password reset goes to the account owner;
3. what is known about the address: the page a subscription was made from (`blog_subscribers.locale`), or the
   page the account last signed in from (`users.last_locale`, written by `Auth::login()`);
4. the blog's language (`blog_settings.default_locale`), for mail about a blog;
5. the site default.

| Email | Language from |
|---|---|
| Notifications, digest, moderation and reporter warnings | `forUser($id)` |
| Welcome, password reset from the form, email change | `forUser($id, current())` or `current()` |
| Password reset or email change sent by an admin | `forUser($id)`, never the admin's page |
| Post announcement | `forSubscriber($row, forBlog($id))`; `BlogSubscriberModel::forBlog()` joins what it needs |
| Subscription confirmation | `current()`, which is also stored on the subscription |
| Invitation | `forAddress($email, forBlog($id))`: the invitee's account language, else the blog's |
| Contact message | `forAddress($adminEmail)`: the admin's own language, else the site default |

The layout marks the document `<html lang="{{ lang }}" dir="{{ dir }}">` and repeats the direction on `<body>`,
because webmail clients such as Gmail drop the `<html>` attributes. Write `align="{{ start }}"` and
`border-{{ start }}:4px solid` rather than `left`, since email clients ignore the CSS logical properties that
would flip on their own.

Role names (`Editor`) and moderation categories reach an email as data, the way the dashboard shows them.

## Escaping and safety

Escaping is decided by the code that made a value, never by the words:

- Strings are always HTML-escaped. Only an `HtmlFragment` is inserted as markup, and only code can make one
  (`fromText()` escapes and keeps line breaks; `trusted()` is for markup the renderer produced).
- Inside a tag a value is always escaped **text**, never markup, so it cannot close an attribute.
- A placeholder that starts an `href`/`src`/`background`/`action` value must be `http(s)://` or `mailto:`;
  anything else (e.g. `javascript:`) becomes `#`.
- Substitution happens once: a value containing `{{ x }}` stays literal.

`EmailHtmlLinter` checks what admins write: scripts, iframes, forms, event handler attributes and `javascript:`
links are refused, and so are placeholders that are malformed or sit unquoted in a tag. A layout is checked as a
whole document (`lintHtml($html, $appUrl, true)`), so it may also have `<meta>` and a `<style>` block, whose CSS
is checked too; an email's words may not. Outlook conditional comments and VML are allowed everywhere. Images or
CSS loaded from another host are flagged, because every load tells that host when and where the email was opened.
Previews are served with a CSP `sandbox` header (`SandboxesPreviewHtml`).

### Why `{{ name }}` and not `[name]`

Square brackets collide with real email markup and text: Outlook conditional comments
(`<!--[if mso]> … <![endif]-->`), CSS attribute selectors (`a[href]`) and subjects such as `[Contact] …`.
Double braces appear in none of them.

Note for view authors: the `.lex.php` compiler treats `{{ … }}` as its own tag **anywhere in a view's source**,
even inside PHP strings or `<script>`. The Email Templates views build placeholder text at runtime
(`'{'.'{ '.$name.' }'.'}'`) for that reason.

## Strict when checking, forgiving when sending

The renderer is strict: a placeholder nothing fills is an error naming the email, the part and the language
(`PasswordResetEmail body (el) uses {{ nonsense }}, but nothing provides it.`). The control panel uses that to
show which saved versions cannot be built (`EmailManager::problems()`); its previews are lenient and show the
gap in place instead.

When sending, a saved version that fails (an edit that slipped past the checks, a missing layout, tables that
cannot be read) is not allowed to lose the email: it goes out as shipped, in English, the error is logged, and
admins holding `manage_email_templates` get an `admin.email_template_failed` notification, at most once an hour.

## Plain text

The plain-text part is read from the finished HTML (`HtmlToText`): blocks become paragraphs, links keep their
address (`Read it (https://…)`), and comments, which include Outlook's copy of each button, are dropped. The
layout is filled a second time without the preheader for it, since the hidden preview line would otherwise be a
stray first line.

## Control panel

`/admin/email-templates`, gated by the `manage_email_templates` permission (`SystemPolicy::manageEmailTemplates`),
separate from site settings because what is written here reaches every inbox.

- **List**: every email, grouped, with its layout and a badge per language: as shipped, edited, missing, or
  red when that language cannot be built as saved.
- **Email**: a tab per language the site offers, a sandboxed preview of the selected one as HTML or plain text,
  **Send test** (that language as saved, with sample values, subject prefixed `[TEST]`), and the data its words
  can use with sample values.

**Email Delivery** (`/admin/email-test`, `manage_site_settings`) is separate and only about whether mail leaves
the server: the transport settings from the environment and a plain connection test.

## Follow-ups not built yet

- Editing in the control panel: a code editor per language with live preview, "Start from English", reset to
  default, and a layouts editor. Until then, shipped emails are changed in their files and translations can be
  added as `email_contents` rows.
- Outdated-translation hints when the English changes after a translation was written.
