<?php

declare(strict_types=1);

/**
 * Built-in email blocks, templates and bindings.
 *
 * Every install starts from these, and "Reset to default" in the control panel
 * comes back here. A saved customization in the database takes precedence over
 * the entry with the same slug (or, for bindings, the same Mailable class).
 *
 * The split is:
 * - components: the design of one block, with {{ placeholders }}
 * - templates: blocks in order
 * - bindings: for each Mailable, which template it uses and the wording of
 *   each placeholder, written as HTML that may use the email's own data
 *
 * Placeholders a template uses must all be mapped by every binding to it,
 * even if only to '' (which leaves that block out). The Mailable data keys
 * each binding can use are listed in the Mailable's build() method.
 *
 * Text: null works the plain text out from the HTML, '' leaves the block out
 * of the plain-text part, anything else is the plain text itself.
 */

use App\Mail;

$notification = [
    'heading' => '', 'intro' => '', 'quote' => '', 'callout' => '',
    'button_label' => '', 'button_url' => '', 'details' => '', 'footer_note' => '',
];

$security = [
    'heading' => '', 'intro' => '', 'button_label' => '', 'button_url' => '',
    'details' => '', 'security_notice' => '', 'footer_note' => '',
];

return [
    'components' => [
        'header' => [
            'label' => 'Header',
            'category' => 'layout',
            'description' => 'The site name across the top, linking home.',
            'html' => <<<'HTML'
            <div style="padding:0 0 16px;margin:0 0 24px;border-bottom:1px solid #e5e7eb;">
                <a href="{{ app_url }}" style="font-size:18px;font-weight:bold;color:#111827;text-decoration:none;">{{ app_name }}</a>
            </div>
            HTML,
            'text' => '',
        ],
        'banner' => [
            'label' => 'Banner',
            'category' => 'layout',
            'description' => 'A coloured band with a large title, for a warm opening.',
            'html' => <<<'HTML'
            <div style="background:#4F46E5;color:#ffffff;padding:24px;margin:0 0 24px;text-align:center;border-radius:6px;">
                <h1 style="margin:0;font-size:24px;line-height:1.3;">{{ banner }}</h1>
            </div>
            HTML,
            'preview_data' => ['banner' => 'Welcome to Lexicon!'],
        ],
        'heading' => [
            'label' => 'Heading',
            'category' => 'content',
            'description' => 'The headline of the email.',
            'html' => '<h2 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#111827;">{{ heading }}</h2>',
            'preview_data' => ['heading' => 'Something happened on your blog'],
        ],
        'intro' => [
            'label' => 'Main text',
            'category' => 'content',
            'description' => 'The opening message. Use <p> tags for more than one paragraph.',
            'html' => '<div style="margin:0 0 16px;">{{ intro }}</div>',
            'preview_data' => ['intro' => 'Someone did something worth telling you about. Here is what happened.'],
        ],
        'quote' => [
            'label' => 'Quote',
            'category' => 'callout',
            'description' => 'Words someone else wrote, such as a comment.',
            'html' => '<blockquote style="margin:0 0 16px;padding:12px 16px;background:#F8FAFC;border-left:3px solid #2563EB;">{{ quote }}</blockquote>',
            'preview_data' => ['quote' => 'Loved the section on Balos, going there next month!'],
        ],
        'callout' => [
            'label' => 'Callout',
            'category' => 'callout',
            'description' => 'An amber box for something the reader should not miss.',
            'html' => '<div style="margin:0 0 16px;padding:12px 16px;background:#FFFBEB;border-left:3px solid #F59E0B;">{{ callout }}</div>',
            'preview_data' => ['callout' => 'This needs your attention before it can go any further.'],
        ],
        'security-notice' => [
            'label' => 'Security notice',
            'category' => 'callout',
            'description' => 'A red box for what to do if the reader did not ask for this.',
            'html' => '<div style="margin:16px 0;padding:12px 16px;background:#FEF2F2;border-left:4px solid #DC2626;">{{ security_notice }}</div>',
            'preview_data' => ['security_notice' => '<strong>If this was not you</strong>, ignore this message. Nothing changes.'],
        ],
        'button' => [
            'label' => 'Button',
            'category' => 'action',
            'description' => 'The one thing the reader should do next.',
            'html' => <<<'HTML'
            <p style="margin:24px 0;"><a href="{{ button_url }}" style="display:inline-block;padding:12px 24px;background:#2563EB;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;">{{ button_label }}</a></p>
            HTML,
            'text' => '{{ button_label }}: {{ button_url }}',
            'preview_data' => ['button_label' => 'Open it', 'button_url' => 'https://example.com/'],
        ],
        'link-fallback' => [
            'label' => 'Link to copy',
            'category' => 'action',
            'description' => "The button's address written out, for clients that hide buttons.",
            'html' => '<p style="margin:0 0 16px;font-size:12px;color:#6b7280;word-break:break-all;">Or paste this into your browser: {{ button_url }}</p>',
            'text' => '',
            'preview_data' => ['button_url' => 'https://example.com/'],
        ],
        'details' => [
            'label' => 'Further details',
            'category' => 'content',
            'description' => 'Secondary text after the main action.',
            'html' => '<div style="margin:0 0 16px;">{{ details }}</div>',
            'preview_data' => ['details' => 'A little more about what happens next.'],
        ],
        'footer' => [
            'label' => 'Footer',
            'category' => 'layout',
            'description' => 'Why the reader got this, and the copyright line.',
            'html' => <<<'HTML'
            <div style="margin-top:32px;padding-top:16px;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;">
                <div>{{ footer_note }}</div>
                <p style="margin:8px 0 0;">&copy; {{ year }} {{ app_name }}</p>
            </div>
            HTML,
            'preview_data' => ['footer_note' => 'You get this because you asked to be told. Switch it off in your notification settings.'],
        ],
        'divider' => [
            'label' => 'Divider',
            'category' => 'layout',
            'description' => 'A thin line between sections.',
            'html' => '<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">',
            'text' => '',
        ],

        // Rows a Mailable repeats itself, such as one section per blog in the digest.
        'section-heading' => [
            'label' => 'Section heading',
            'category' => 'data',
            'description' => 'Title of one repeated section, linking to it.',
            'html' => '<h3 style="margin:24px 0 4px;font-size:17px;"><a href="{{ section_url }}" style="color:#1f2937;">{{ section_title }}</a></h3>',
            'text' => '{{ section_title }}',
            'preview_data' => ['section_title' => 'Travel Stories', 'section_url' => 'https://example.com/blog/travel-stories'],
        ],
        'stat-line' => [
            'label' => 'Statistic',
            'category' => 'data',
            'description' => 'A labelled figure with a link to the page behind it.',
            'html' => '<div style="margin:8px 0;"><strong>{{ stat_label }}</strong> &middot; <a href="{{ stat_url }}">Open</a><br>{{ stat_text }}</div>',
            'text' => "{{ stat_label }}: {{ stat_text }}\n{{ stat_url }}",
            'preview_data' => ['stat_label' => 'Overview', 'stat_text' => '1,240 views from 860 daily visitors.', 'stat_url' => 'https://example.com/'],
        ],
        'list-item' => [
            'label' => 'List item',
            'category' => 'data',
            'description' => 'One bulleted line.',
            'html' => '<div style="margin:2px 0 2px 12px;">&bull; {{ item }}</div>',
            'text' => '- {{ item }}',
            'preview_data' => ['item' => 'Ten Days in Crete: 410 views'],
        ],
    ],

    'templates' => [
        'notification' => [
            'label' => 'Notification',
            'category' => 'transactional',
            'description' => 'Tells someone what happened and what to do about it. Leave a part empty to drop its block.',
            'layout' => ['header', 'heading', 'intro', 'quote', 'callout', 'button', 'details', 'footer'],
        ],
        'security' => [
            'label' => 'Account security',
            'category' => 'transactional',
            'description' => 'Links that act on an account, with the address written out and a warning for anyone who did not ask.',
            'layout' => ['header', 'heading', 'intro', 'button', 'link-fallback', 'details', 'security-notice', 'footer'],
        ],
        'welcome' => [
            'label' => 'Welcome',
            'category' => 'promotional',
            'description' => 'A friendly first email with a banner.',
            'layout' => ['banner', 'heading', 'intro', 'button', 'details', 'footer'],
        ],
        'digest' => [
            'label' => 'Digest',
            'category' => 'digest',
            'description' => 'A summary made of repeated sections the email assembles itself.',
            'layout' => ['header', 'heading', 'intro', 'details', 'footer'],
        ],
    ],

    'bindings' => [
        // Account
        Mail\WelcomeEmail::class => [
            'template' => 'welcome',
            'mapping' => [
                'banner' => 'Welcome to {{ app_name }}!',
                'heading' => 'Hello {{ first_name }},',
                'intro' => '<p>Thank you for joining our community! Your account has been successfully created.</p>'
                    .'<p><strong>Your tag:</strong> @{{ handle }}</p>'
                    .'<p>Save posts for later, follow the blogs you love, and join the discussions. When you feel like writing, you can start a blog of your own any time.</p>',
                'button_label' => 'Find something to read',
                'button_url' => '{{ explore_url }}',
                'details' => 'If you have any questions, feel free to reach out to our support team.',
                'footer_note' => 'You received this email because you registered an account.',
            ],
        ],
        Mail\PasswordResetEmail::class => [
            'template' => 'security',
            'mapping' => [
                'heading' => 'Password reset request',
                'intro' => '<p>Hello {{ first_name }},</p><p>We received a request to reset your password. Click the button below to create a new password:</p>',
                'button_label' => 'Reset password',
                'button_url' => '{{ reset_url }}',
                'details' => '<p>This link will expire in {{ expires_in_minutes }} minutes.</p>'
                    ."<p>For security reasons, we cannot send your existing password. If you're having trouble, contact our support team.</p>",
                'security_notice' => "<strong>Security notice:</strong> If you didn't request this password reset, please ignore this email. Your password will remain unchanged.",
            ] + $security,
        ],
        Mail\EmailChangeVerificationMail::class => [
            'template' => 'security',
            'mapping' => [
                'heading' => 'Confirm your new email address',
                'intro' => '<p>Someone asked to change the email address on a {{ app_name }} account to <strong>{{ new_email }}</strong>.</p><p>Confirm it to finish the change:</p>',
                'button_label' => 'Confirm this address',
                'button_url' => '{{ confirm_url }}',
                'details' => 'The link stops working in {{ expires_in_minutes }} minutes, and the account keeps its current address until you follow it.',
                'security_notice' => '<strong>If you were not expecting this</strong>, ignore this message. Nothing changes and this address is not added to the account.',
            ] + $security,
        ],
        Mail\EmailChangedMail::class => [
            'template' => 'security',
            'mapping' => [
                'heading' => 'Your email address was changed',
                'intro' => 'The email address on your {{ app_name }} account was changed to <strong>{{ new_email }}</strong> on {{ changed_at }} (UTC).',
                'details' => 'If you made this change, no action is needed.',
                'security_notice' => '<strong>If this was not you</strong>, your account may have been accessed by someone else. Reset your password immediately and contact support.',
                'footer_note' => 'You are receiving this at your previous address because it was the one on the account when the change was made.',
            ] + $security,
        ],
        Mail\ModerationWarningMail::class => [
            'template' => 'notification',
            'mapping' => [
                'heading' => 'A warning about your {{ subject_kind }}',
                'intro' => '<p>Hi @{{ handle }},</p><p>Readers reported your {{ subject_kind }} <strong>{{ subject_label }}</strong> for <strong>{{ category }}</strong>, and a moderator agreed with them.</p>',
                'callout' => '{{ message }}',
                'details' => 'Further reports that are upheld can lead to your account being suspended.',
            ] + $notification,
        ],
        Mail\ReporterWarningMail::class => [
            'template' => 'notification',
            'mapping' => [
                'heading' => 'About the reports you have been sending',
                'intro' => '<p>Hi @{{ handle }},</p><p>Moderators found {{ unfounded_reports }} to be unfounded.</p>',
                'callout' => '{{ message }}',
                'details' => 'Reports help keep the site safe, so please keep sending them when something is wrong. If more reports turn out to be unfounded, your reporting may be paused for a while.',
            ] + $notification,
        ],

        // Collaboration
        Mail\BlogInviteMail::class => [
            'template' => 'notification',
            'mapping' => [
                'heading' => "You've been invited to collaborate",
                'intro' => "You've been invited to join <strong>{{ blog_name }}</strong> as <strong>{{ role }}</strong>.",
                'button_label' => 'View invitation',
                'button_url' => '{{ invite_url }}',
                'details' => "This invitation expires in 7 days. If you don't have an account yet, you'll be guided through registration first.",
            ] + $notification,
        ],
        Mail\CollaboratorRoleChangedMail::class => [
            'template' => 'notification',
            'mapping' => [
                'heading' => 'Role updated',
                'intro' => 'Your role on <strong>{{ blog_name }}</strong> is now <strong>{{ new_role }}</strong>.',
                'details' => 'Changed by {{ actor_handle }}.',
            ] + $notification,
        ],
        Mail\CollaboratorRemovedMail::class => [
            'template' => 'notification',
            'mapping' => [
                'heading' => 'Access removed',
                'intro' => 'Your access to <strong>{{ blog_name }}</strong> was revoked by {{ actor_handle }}.',
                'details' => 'If you think this was a mistake, contact the blog owner.',
            ] + $notification,
        ],
        Mail\InviteDeclinedMail::class => [
            'template' => 'notification',
            'mapping' => [
                'heading' => 'Invite declined',
                'intro' => '<strong>{{ declined_email }}</strong> declined your invitation to <strong>{{ blog_name }}</strong>.',
            ] + $notification,
        ],

        // Insights
        Mail\InsightsMilestoneMail::class => [
            'template' => 'notification',
            'mapping' => [
                'heading' => '{{ views }} views',
                'intro' => 'Your post <strong>{{ post_title }}</strong> has now been read more than {{ views }} times.',
                'button_label' => 'See how it got there',
                'button_url' => '{{ insights_url }}',
                'footer_note' => 'Switch these off in your notification settings.',
            ] + $notification,
        ],
        Mail\InsightsSpikeMail::class => [
            'template' => 'notification',
            'mapping' => [
                'heading' => '{{ blog_name }} is busy today',
                'intro' => '{{ summary }}',
                'button_label' => 'See where readers are coming from',
                'button_url' => '{{ insights_url }}',
                'footer_note' => 'You get this at most once a day per blog. Switch it off in your notification settings.',
            ] + $notification,
        ],
        Mail\InsightsDigestMail::class => [
            'template' => 'digest',
            'mapping' => [
                'heading' => 'Your week: {{ week_label }}',
                'intro' => '{{ sections }}',
                'details' => '',
                'footer_note' => 'Sent on Mondays. Switch it off in your notification settings.',
            ],
        ],

        // Review workflow
        Mail\PostSubmittedMail::class => [
            'template' => 'notification',
            'mapping' => [
                'heading' => 'Review requested',
                'intro' => '<strong>{{ author_handle }}</strong> has submitted <strong>{{ post_title }}</strong> for review.',
                'callout' => '{{ unassigned_note }}',
                'button_label' => 'Open review page',
                'button_url' => '{{ review_url }}',
            ] + $notification,
        ],
        Mail\ReviewerAssignedMail::class => [
            'template' => 'notification',
            'mapping' => [
                'heading' => "You're up for review",
                'intro' => '<strong>{{ actor_handle }}</strong> assigned you to review <strong>{{ post_title }}</strong>.',
                'button_label' => 'Open review page',
                'button_url' => '{{ review_url }}',
            ] + $notification,
        ],
        Mail\ReviewerStaleMail::class => [
            'template' => 'notification',
            'mapping' => [
                'heading' => 'Reviewer reset',
                'intro' => '<strong>{{ former_reviewer_handle }}</strong> can no longer review <strong>{{ post_title }}</strong> (role changed or revoked). The post was reopened to all reviewers.',
                'button_label' => 'Open post',
                'button_url' => '{{ review_url }}',
            ] + $notification,
        ],
        Mail\PostApprovedMail::class => [
            'template' => 'notification',
            'mapping' => [
                'heading' => 'Approved',
                'intro' => '<strong>{{ reviewer_handle }}</strong> approved <strong>{{ post_title }}</strong>. An editor can now publish it.',
                'button_label' => 'Open post',
                'button_url' => '{{ post_url }}',
            ] + $notification,
        ],
        Mail\PostNeedsChangesMail::class => [
            'template' => 'notification',
            'mapping' => [
                'heading' => 'Changes requested',
                'intro' => '<strong>{{ reviewer_handle }}</strong> reviewed <strong>{{ post_title }}</strong> and asked for changes:',
                'callout' => '{{ feedback }}',
                'button_label' => 'Edit post',
                'button_url' => '{{ edit_url }}',
                'details' => "Resubmit when you're ready. The same review gate kicks in again.",
            ] + $notification,
        ],
        Mail\PostPublishedMail::class => [
            'template' => 'notification',
            'mapping' => [
                'heading' => 'Published',
                'intro' => '<strong>{{ post_title }}</strong> is now live.',
                'button_label' => 'View on site',
                'button_url' => '{{ post_url }}',
            ] + $notification,
        ],
        Mail\WorkflowDisabledMail::class => [
            'template' => 'notification',
            'mapping' => [
                'heading' => 'Workflow disabled',
                'intro' => 'The owner of <strong>{{ blog_name }}</strong> turned off the review workflow. Your post <strong>{{ post_title }}</strong> was reset to draft, and nothing was published automatically.',
                'button_label' => 'Edit post',
                'button_url' => '{{ edit_url }}',
            ] + $notification,
        ],

        // Subscribers and comments
        Mail\NewPostMail::class => [
            'template' => 'notification',
            'mapping' => [
                'heading' => '{{ blog_name }} just published a new post',
                'intro' => '<strong>{{ post_title }}</strong>',
                'button_label' => 'Read it',
                'button_url' => '{{ post_url }}',
                'footer_note' => 'You are receiving this because you subscribed to {{ blog_name }}. <a href="{{ unsubscribe_url }}" style="color:#6b7280;">Unsubscribe</a>',
            ] + $notification,
        ],
        Mail\SubscriptionConfirmMail::class => [
            'template' => 'security',
            'mapping' => [
                'heading' => 'Confirm your subscription',
                'intro' => 'Someone asked to send new posts from <strong>{{ blog_name }}</strong> to <strong>{{ email }}</strong>.',
                'button_label' => 'Yes, subscribe me',
                'button_url' => '{{ confirm_url }}',
                'details' => "<strong>If this wasn't you</strong>, ignore this message. You won't get any more emails and the address is removed within a week.",
            ] + $security,
        ],
        Mail\NewCommentMail::class => [
            'template' => 'notification',
            'mapping' => [
                'heading' => 'New comment',
                'intro' => '{{ lead }}',
                'quote' => '{{ comment_excerpt }}',
                'callout' => '{{ moderation_note }}',
                'button_label' => '{{ action_label }}',
                'button_url' => '{{ comment_url }}',
            ] + $notification,
        ],

        // Platform
        Mail\ContactMessageMail::class => [
            'template' => 'notification',
            'mapping' => [
                'heading' => 'New contact message',
                'intro' => '<p><strong>From:</strong> {{ sender_name }} &lt;{{ sender_email }}&gt;</p><p><strong>Subject:</strong> {{ message_subject }}</p>',
                'quote' => '{{ message_body }}',
            ] + $notification,
        ],
    ],
];
