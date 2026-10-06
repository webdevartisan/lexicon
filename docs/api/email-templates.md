# Email Templates

Every email the platform sends is built from editable templates. Admins change how emails look and
what they say under **System → Email Templates** in the control panel; nothing in `src/App/Mail`
contains markup any more.

← [`Back to docs index`](../README.md)

---

## The three layers

| Layer | Owned by | Lives in | Example |
|---|---|---|---|
| **Data** | The Mailable (code) | `build()` → `fromTemplate([...])` | `post_title`, `post_url`, a comment someone wrote |
| **Wording** | The email's *binding* | catalog / `mailable_template_bindings` | heading = `{{ blog_name }} just published a new post` |
| **Design** | *Templates* made of *blocks* | catalog / `email_templates`, `email_components` | the `notification` template: header, heading, intro, quote, callout, button, details, footer |

- A **block** (component) is a piece of HTML with `{{ placeholders }}`, e.g. the `button` block uses
  `{{ button_label }}` and `{{ button_url }}`.
- A **template** is blocks in order. One template serves many emails.
- A **binding** says which template an email uses and gives the *wording* for each of the template's
  placeholders. Wording is a short piece of HTML that may refer to the email's data, so admins can change
  copy without touching code, and the same `notification` template can say different things in each email.

Logic stays in code: plurals, whether a note applies at all. The Mailable passes such phrases as data
(`moderation_note`, …).

## Built-in catalog and overrides

The shipped blocks, templates and bindings live in [`resources/mail/catalog.php`](../../resources/mail/catalog.php).
The database only holds what was changed in the control panel:

- a row with a built-in slug (or Mailable class) **overrides** it and shows as *Customized*;
- a row with a new slug is *Custom*;
- deleting the row is **Reset to default**.

So a fresh install has empty tables and still sends every email, upgrades that improve a built-in design
reach every install that has not customized it, and there is nothing to seed or keep in sync.
A binding can also be saved *switched off*: the edits are kept, the built-in version is sent.

## Writing a Mailable

```php
public function build(): void
{
    $this->to($this->toEmail)
        ->subject('New on '.$this->blogName.': '.$this->postTitle)   // default subject, before fromTemplate()
        ->fromTemplate([
            'blog_name' => $this->blogName,
            'post_title' => $this->postTitle,
            'post_url' => $this->postUrl(),
            'message' => HtmlFragment::fromText($this->message),      // user text with line breaks
        ]);
}
```

Then add a binding for the class to `resources/mail/catalog.php`, and a sample to
`EmailTemplateRegistry` (which the control panel and the tests use). The tests fail if either is missing, if
a sample does not render strictly, if a sample passes a value the constructor does not take, or if any HTML
is left in `src/App/Mail/*.php`.

**One class per email.** The control panel lists one email per class and stores wording per class, so a
message sent for several different reasons is one class per reason, sharing an abstract base for the data
and links. The comment emails are the example: `CommentMail` builds the data, and `CommentReplyMail`,
`PostCommentMail`, `CommentModerationMail` and `BlogCommentMail` each add only their subject and, for
moderation, where the button goes. `PostSubmittedMail` and `PostSubmittedUnassignedMail` (no reviewer
assigned yet) are split the same way. Each is registered once; `EmailTemplateRegistryTest` fails on a class
registered twice. Separate classes also give each email its own `utm_campaign` (`comment-reply`, …).

Rules for data:

- Keys are `snake_case`. Values are strings, numbers, `null` (empty) or an `HtmlFragment`.
- Give the template the same keys every time (pass `''` for "not applicable"); the editor lists them
  from the email's sample.
- `{{ app_name }}`, `{{ app_url }}`, `{{ year }}` and `{{ subject }}` are always available, and so are
  `{{ dir }}`, `{{ start }}` and `{{ end }}` for block styles (see Languages).

For repeated structure (one section per blog in the weekly digest), render blocks from code with
`$this->component('stat-line', [...])`, join them with `HtmlFragment::join()`, and pass the result as one
value. The order is code; the look of each row is still an editable block.

## Languages

Every email is built in its recipient's language. The code that sends it picks the language with
`RecipientLocale` and builds the Mailable inside `Mailable::inLocale()`:

```php
$mail = Mailable::inLocale($this->locales->forUser($userId), fn () => new PostApprovedMail(...));
```

Outside `inLocale()` the site default is used. The queue stores each email already rendered, so the language
is settled when it is queued and the worker never needs to know it.

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

The document is marked `<html lang=".." dir="..">`, with the direction repeated on the content wrapper because
webmail clients such as Gmail drop the `<html>` attributes. For right-to-left languages (`LocaleRegistry::RTL`,
which includes `ar`) blocks are laid out from the right: write `border-{{ start }}:3px solid` rather than
`border-left`, since email clients ignore the CSS logical properties (`border-inline-start`) that would flip on
their own. `{{ start }}` is `left` or `right`, `{{ end }}` the other side, and `{{ dir }}` is `ltr` or `rtl`.
These three never count when deciding whether a block is empty.

The wording itself is still English in every language; translating it, and editing it per language in the
control panel, are the next steps.

## Escaping and safety

Escaping is decided by the code that made a value, never by the template:

- Strings are always HTML-escaped. Only an `HtmlFragment` is inserted as markup, and only code can make one
  (`fromText()` escapes and keeps line breaks; `trusted()` is for markup the renderer produced).
- Inside a tag a value is always escaped **text**, never markup, so it cannot close an attribute.
- A placeholder that starts an `href`/`src`/`background`/`action` value must be `http(s)://` or `mailto:`;
  anything else (e.g. `javascript:`) becomes `#`.
- Substitution happens once: a value containing `{{ x }}` stays literal.

What admins write is linted on save (`EmailHtmlLinter`): scripts, iframes, forms, `<style>`, event handler
attributes and `javascript:` links are refused, and so are placeholders that are malformed or sit unquoted in
a tag. Images or CSS loaded from another host save with a warning, because every load tells that host when and
where the email was opened. Previews are served with a CSP `sandbox` header (`SandboxesPreviewHtml`).

### Why `{{ name }}` and not `[name]`

Square brackets collide with real email markup: Outlook conditional comments (`<!--[if mso]> … <![endif]-->`)
and CSS attribute selectors (`a[href]`). Double braces appear in neither.

Note for view authors: the `.lex.php` compiler treats `{{ … }}` as its own tag **anywhere in a view's source**,
even inside PHP strings or `<script>`. The Email Templates views build placeholder text at runtime
(`'{'.'{ '.$name.' }'.'}'`) for that reason.

## Strict when editing, forgiving when sending

- **Every save is a dry run.** `EmailTemplateManager` builds a draft (`DraftTemplateSource`) and renders every
  registered email sample against it in strict mode. If any email would no longer build, nothing is saved and
  the admin sees which email and which placeholder. This covers edits to blocks, templates, bindings, resets
  and deletes, including the blocks the digest renders from code.
- **Sending degrades rather than fails.** If a stored customization still cannot render at send time (a row
  edited by hand, the tables unreadable), `TemplateRendererService` logs it, renders the email from the
  built-in catalog instead, and notifies admins holding `manage_email_templates`
  (`admin.email_template_failed`, at most hourly). A password reset is never lost to a template edit.
- Previews are lenient: a missing value is shown highlighted as `{{ name }}` instead of failing.

## Rendering details

- **Empty blocks are dropped.** A block whose placeholders all come out empty is left out of that email, so
  one template serves emails with and without a quote, a button, a footer note. A block with no placeholders
  (a divider) always shows. Map a placeholder to nothing to drop its block.
- **Plain text** is generated per block: *automatic* (from the HTML, keeping link addresses and each value's
  own line breaks), *custom* (a text template with the same placeholders), or *left out*.
- Each block's CSS is added to the `<head>` once. Inline styles remain the safest choice for email clients.
- Placeholders mean the same thing across a template: if two blocks use `{{ body }}`, both show the same value.
- The repository reads the template tables once per process, so a subscriber fan-out does not query per
  recipient. Writes through the manager flush it.

## Control panel

`/admin/email-templates`, gated by the `manage_email_templates` permission (`SystemPolicy::manageEmailTemplates`),
separate from site settings because what is written here reaches every inbox.

- **Emails**: every email, its template, whether its wording is customized, and whether it currently builds.
  The editor shows the template's placeholders as fields, the email's data with sample values (click to insert),
  a subject override, and a live preview of the draft as HTML or plain text.
- **Templates**: list with search and category filter; a builder with a block palette, drag-and-drop layout
  (with keyboard up/down/remove buttons), the placeholders an email must provide, the emails using it, and a
  live preview; a read-only preview page with HTML, plain text and placeholder tabs.
- **Blocks**: library grouped by category; an editor that detects placeholders as you type, creates sample-value
  fields for them, and previews live.

Built-in items can be edited and reset but not deleted. Custom blocks and templates can be deleted once nothing
uses them. Every change is written to the audit log (`email_template.*`). To send yourself a real test of an
email, use **Test** on the Emails list (the existing Email Delivery page).

## Decisions

| Question | Decision |
|---|---|
| Placeholder syntax | `{{ snake_case }}`, see above |
| Strict or forgiving | Strict on save (dry run of every email), forgiving on send (fallback + admin alert) |
| Text alternative | Auto-generated per block by default; a block can define its own or opt out |
| Component versioning | Live: editing a block updates every template using it, and the save is checked against all of them |
| Preview data | Per block, editable in the block editor; real emails use their own registry samples |
| Draft / published | No separate status: saves are always validated, a binding can be saved switched off, and the live preview covers trying things out |
| Reordering | In place on the canvas by drag and drop, or with up/down buttons |
| Bindings | Per Mailable class, edited from the Emails tab rather than inside the template editor, since one template serves many emails |

## Follow-ups not built yet

- Version history for blocks and templates (the audit log records who changed what, not the old content).
- Help text per placeholder, and A/B variants.
