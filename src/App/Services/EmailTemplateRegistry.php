<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\Mailable;
use App\Mail\QueuedMail;
use Exception;

/**
 * Email Template Registry Service
 *
 * provide a centralized registry for discovering and instantiating
 * email templates with sample data for testing and preview purposes.
 *
 * Every Mailable in src/App/Mail must be registered here so it shows up
 * on the admin Email Templates page; unregisteredClasses() reports any
 * that were added to the codebase but never registered.
 *
 * Register each class exactly once. The admin page lists one email per class,
 * and its wording is stored per class, so an email sent for several different
 * reasons should be one class per reason (see CommentMail and its subclasses)
 * rather than one class registered several times.
 */
class EmailTemplateRegistry
{
    /**
     * Get all available email templates with metadata.
     *
     * register each template with sample data needed for instantiation,
     * making it easy to test templates with realistic content. The group
     * key drives the section headings on the admin page.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAll(): array
    {
        // register each template with sample data for testing
        return [
            // Account lifecycle
            'welcome' => [
                'name' => 'Welcome Email',
                'description' => 'Sent to new users after registration',
                'group' => 'Account',
                'class' => 'App\\Mail\\WelcomeEmail',
                'sample_data' => [
                    'user' => [
                        'first_name' => 'John',
                        'handle' => 'johndoe',
                        'email' => 'john@example.com',
                    ],
                ],
            ],
            'password_reset' => [
                'name' => 'Password Reset',
                'description' => 'Sent when user requests password reset',
                'group' => 'Account',
                'class' => 'App\\Mail\\PasswordResetEmail',
                'sample_data' => [
                    'user' => [
                        'first_name' => 'Jane',
                        'email' => 'jane@example.com',
                    ],
                    'token' => 'SAMPLE_TOKEN_HERE',
                    'expiresInMinutes' => 60,
                ],
            ],
            'email_change_verification' => [
                'name' => 'Email Change Verification',
                'description' => 'Sent to the new address to confirm an email change before it takes effect',
                'group' => 'Account',
                'class' => 'App\\Mail\\EmailChangeVerificationMail',
                'sample_data' => [
                    'newEmail' => 'new@example.com',
                    'token' => 'SAMPLE_TOKEN_HERE',
                    'expiresInMinutes' => 60,
                ],
            ],
            'email_changed' => [
                'name' => 'Email Address Changed',
                'description' => 'Sent to the previous address after an email change completes',
                'group' => 'Account',
                'class' => 'App\\Mail\\EmailChangedMail',
                'sample_data' => [
                    'oldEmail' => 'old@example.com',
                    'newEmail' => 'new@example.com',
                    'changedAt' => '2026-01-01T12:00:00+00:00',
                ],
            ],

            // Collaboration and team management
            'blog_invite' => [
                'name' => 'Blog Invitation',
                'description' => 'Invites someone to join a blog with a specific role',
                'group' => 'Collaboration',
                'class' => 'App\\Mail\\BlogInviteMail',
                'sample_data' => [
                    'toEmail' => 'invitee@example.com',
                    'rawToken' => 'SAMPLE_INVITE_TOKEN',
                    'blogName' => 'Travel Stories',
                    'role' => 'author',
                ],
            ],
            'collaborator_role_changed' => [
                'name' => 'Collaborator Role Changed',
                'description' => 'Notifies a collaborator their role on a blog changed',
                'group' => 'Collaboration',
                'class' => 'App\\Mail\\CollaboratorRoleChangedMail',
                'sample_data' => [
                    'toEmail' => 'collaborator@example.com',
                    'blogName' => 'Travel Stories',
                    'newRole' => 'reviewer',
                    'actorHandle' => 'blogowner',
                ],
            ],
            'moderation_warning_post' => [
                'name' => 'Moderation Warning: Post',
                'description' => 'Tells an author a moderator upheld reports against one of their posts',
                'group' => 'Account',
                'class' => 'App\\Mail\\ModerationWarningPostMail',
                'sample_data' => [
                    'toEmail' => 'author@example.com',
                    'handle' => 'johndoe',
                    'subjectLabel' => 'Ten Hidden Beaches in Crete',
                    'category' => 'Misleading content',
                    'message' => 'Several of the beaches listed are private resorts. Please correct the post.',
                ],
            ],
            'moderation_warning_comment' => [
                'name' => 'Moderation Warning: Comment',
                'description' => 'Tells an author a moderator upheld reports against one of their comments',
                'group' => 'Account',
                'class' => 'App\\Mail\\ModerationWarningCommentMail',
                'sample_data' => [
                    'toEmail' => 'author@example.com',
                    'handle' => 'johndoe',
                    'subjectLabel' => 'Buy cheap followers at...',
                    'category' => 'Spam',
                    'message' => 'Please stop posting the same link under every post.',
                ],
            ],
            'reporter_warning' => [
                'name' => 'Reporter Warning',
                'description' => 'Warns a reader that several of their reports were unfounded, before any pause of their reporting',
                'group' => 'Account',
                'class' => 'App\\Mail\\ReporterWarningMail',
                'sample_data' => [
                    'toEmail' => 'reader@example.com',
                    'handle' => 'johndoe',
                    'unfounded' => 4,
                    'message' => 'Several of your recent reports were about posts you disagreed with, not posts that broke the rules.',
                ],
            ],
            'collaborator_removed' => [
                'name' => 'Collaborator Removed',
                'description' => 'Notifies a collaborator they were removed from a blog',
                'group' => 'Collaboration',
                'class' => 'App\\Mail\\CollaboratorRemovedMail',
                'sample_data' => [
                    'toEmail' => 'collaborator@example.com',
                    'blogName' => 'Travel Stories',
                    'actorHandle' => 'blogowner',
                ],
            ],
            'invite_declined' => [
                'name' => 'Invitation Declined',
                'description' => 'Tells the blog owner an invitation was declined',
                'group' => 'Collaboration',
                'class' => 'App\\Mail\\InviteDeclinedMail',
                'sample_data' => [
                    'toEmail' => 'owner@example.com',
                    'blogName' => 'Travel Stories',
                    'declinedEmail' => 'invitee@example.com',
                ],
            ],

            // Insights news
            'insights_milestone' => [
                'name' => 'View Milestone',
                'description' => 'Tells an author a post passed 100, 1,000, 10,000 or 100,000 views',
                'group' => 'Insights',
                'class' => 'App\\Mail\\InsightsMilestoneMail',
                'sample_data' => [
                    'toEmail' => 'author@example.com',
                    'postTitle' => 'Ten Days in Crete',
                    'threshold' => 1000,
                    'blogId' => 1,
                    'postId' => 1,
                ],
            ],
            'insights_spike' => [
                'name' => 'Views Spike',
                'description' => 'Tells a blog owner the blog is far busier than usual today',
                'group' => 'Insights',
                'class' => 'App\\Mail\\InsightsSpikeMail',
                'sample_data' => [
                    'toEmail' => 'owner@example.com',
                    'blogName' => 'Travel Stories',
                    'blogId' => 1,
                    'views' => 640,
                    'usual' => 85.0,
                    'topSource' => 'Hacker News',
                ],
            ],
            'insights_digest' => [
                'name' => 'Weekly Insights Digest',
                'description' => 'Monday summary of each blog an owner has',
                'group' => 'Insights',
                'class' => 'App\\Mail\\InsightsDigestMail',
                'sample_data' => [
                    'toEmail' => 'owner@example.com',
                    'weekStart' => '2026-09-28',
                    'weekEnd' => '2026-10-04',
                    'blogs' => [[
                        'name' => 'Travel Stories', 'id' => 1, 'slug' => 'travel-stories', 'from' => '2026-09-28', 'to' => '2026-10-04',
                        'views' => 1240, 'previous' => 980, 'visitors' => 860, 'source' => 'Google',
                        'posts' => [['title' => 'Ten Days in Crete', 'views' => 410]],
                        'goals' => ['subscribe' => 3, 'comment' => 12, 'like' => 1],
                    ]],
                ],
            ],

            // Review workflow notifications
            'post_submitted' => [
                'name' => 'Post Submitted for Review',
                'description' => 'Notifies reviewers a draft is waiting for review',
                'group' => 'Review workflow',
                'class' => 'App\\Mail\\PostSubmittedMail',
                'sample_data' => [
                    'toEmail' => 'reviewer@example.com',
                    'postId' => 42,
                    'postTitle' => 'Ten Hidden Beaches in Crete',
                    'authorHandle' => 'johndoe',
                ],
            ],
            'post_submitted_unassigned' => [
                'name' => 'Post Waiting for a Reviewer',
                'description' => 'Tells every owner, editor and reviewer a submitted post has nobody assigned yet',
                'group' => 'Review workflow',
                'class' => 'App\\Mail\\PostSubmittedUnassignedMail',
                'sample_data' => [
                    'toEmail' => 'editor@example.com',
                    'postId' => 42,
                    'postTitle' => 'Ten Hidden Beaches in Crete',
                    'authorHandle' => 'johndoe',
                ],
            ],
            'reviewer_assigned' => [
                'name' => 'Reviewer Assigned',
                'description' => 'Notifies a reviewer they were assigned to a post',
                'group' => 'Review workflow',
                'class' => 'App\\Mail\\ReviewerAssignedMail',
                'sample_data' => [
                    'toEmail' => 'reviewer@example.com',
                    'postId' => 42,
                    'postTitle' => 'Ten Hidden Beaches in Crete',
                    'actorHandle' => 'blogowner',
                ],
            ],
            'reviewer_stale' => [
                'name' => 'Reviewer Unassigned (Stale)',
                'description' => 'Tells a former reviewer the assignment moved on without them',
                'group' => 'Review workflow',
                'class' => 'App\\Mail\\ReviewerStaleMail',
                'sample_data' => [
                    'toEmail' => 'reviewer@example.com',
                    'postId' => 42,
                    'postTitle' => 'Ten Hidden Beaches in Crete',
                    'formerReviewerHandle' => 'oldreviewer',
                ],
            ],
            'post_approved' => [
                'name' => 'Post Approved',
                'description' => 'Tells the author their post passed review',
                'group' => 'Review workflow',
                'class' => 'App\\Mail\\PostApprovedMail',
                'sample_data' => [
                    'toEmail' => 'author@example.com',
                    'postId' => 42,
                    'postTitle' => 'Ten Hidden Beaches in Crete',
                    'reviewerHandle' => 'janereviewer',
                ],
            ],
            'post_needs_changes' => [
                'name' => 'Post Needs Changes',
                'description' => 'Sends the author reviewer feedback asking for changes',
                'group' => 'Review workflow',
                'class' => 'App\\Mail\\PostNeedsChangesMail',
                'sample_data' => [
                    'toEmail' => 'author@example.com',
                    'postId' => 42,
                    'postTitle' => 'Ten Hidden Beaches in Crete',
                    'reviewerHandle' => 'janereviewer',
                    'feedback' => 'Great start! Please add photo credits and tighten the intro paragraph.',
                ],
            ],
            'post_published' => [
                'name' => 'Post Published',
                'description' => 'Tells the author their post is live with a public link',
                'group' => 'Review workflow',
                'class' => 'App\\Mail\\PostPublishedMail',
                'sample_data' => [
                    'toEmail' => 'author@example.com',
                    'postTitle' => 'Ten Hidden Beaches in Crete',
                    'blogSlug' => 'travel-stories',
                    'postSlug' => 'ten-hidden-beaches-in-crete',
                ],
            ],
            'new_post' => [
                'name' => 'New Post for Subscribers',
                'description' => 'Tells blog subscribers a new post is live, with an unsubscribe link',
                'group' => 'Subscribers',
                'class' => 'App\\Mail\\NewPostMail',
                'sample_data' => [
                    'toEmail' => 'reader@example.com',
                    'blogName' => 'Travel Stories',
                    'postTitle' => 'Ten Hidden Beaches in Crete',
                    'blogSlug' => 'travel-stories',
                    'postSlug' => 'ten-hidden-beaches-in-crete',
                    'unsubscribeToken' => str_repeat('ab', 32),
                ],
            ],
            'subscription_confirm' => [
                'name' => 'Confirm Subscription',
                'description' => 'Asks a new subscriber to confirm the address before any post notifications go out',
                'group' => 'Subscribers',
                'class' => 'App\\Mail\\SubscriptionConfirmMail',
                'sample_data' => [
                    'toEmail' => 'reader@example.com',
                    'blogName' => 'Travel Stories',
                    'token' => str_repeat('cd', 32),
                ],
            ],
            'comment_reply' => [
                'name' => 'Reply to Your Comment',
                'description' => 'Tells you someone replied to a comment you wrote',
                'group' => 'Comments',
                'class' => 'App\\Mail\\CommentReplyMail',
                'sample_data' => [
                    'toEmail' => 'reader@example.com',
                    'postTitle' => 'Ten Hidden Beaches in Crete',
                    'blogSlug' => 'travel-stories',
                    'postSlug' => 'ten-hidden-beaches-in-crete',
                    'commenterName' => 'quietreader',
                    'commentExcerpt' => 'Same! Balos is unreal at sunrise, get there early.',
                    'awaitingModeration' => false,
                    'commentId' => 128,
                    'blogId' => 7,
                ],
            ],
            'comment_reply_pending' => [
                'name' => 'Reply to Your Comment (Awaiting Approval)',
                'description' => 'Tells you someone replied to a comment you wrote, while the reply waits for approval',
                'group' => 'Comments',
                'class' => 'App\\Mail\\CommentReplyPendingMail',
                'sample_data' => [
                    'toEmail' => 'reader@example.com',
                    'postTitle' => 'Ten Hidden Beaches in Crete',
                    'blogSlug' => 'travel-stories',
                    'postSlug' => 'ten-hidden-beaches-in-crete',
                    'commenterName' => 'quietreader',
                    'commentExcerpt' => 'Same! Balos is unreal at sunrise, get there early.',
                    'awaitingModeration' => true,
                    'commentId' => 128,
                    'blogId' => 7,
                ],
            ],
            'post_comment' => [
                'name' => 'Comment on Your Post',
                'description' => 'Tells a post author a reader commented on their post',
                'group' => 'Comments',
                'class' => 'App\\Mail\\PostCommentMail',
                'sample_data' => [
                    'toEmail' => 'author@example.com',
                    'postTitle' => 'Ten Hidden Beaches in Crete',
                    'blogSlug' => 'travel-stories',
                    'postSlug' => 'ten-hidden-beaches-in-crete',
                    'commenterName' => 'quietreader',
                    'commentExcerpt' => 'Loved the section on Balos — going there next month!',
                    'awaitingModeration' => false,
                    'commentId' => 128,
                    'blogId' => 7,
                ],
            ],
            'post_comment_pending' => [
                'name' => 'Comment on Your Post (Awaiting Approval)',
                'description' => 'Tells a post\'s author about a new comment that is waiting for approval',
                'group' => 'Comments',
                'class' => 'App\\Mail\\PostCommentPendingMail',
                'sample_data' => [
                    'toEmail' => 'author@example.com',
                    'postTitle' => 'Ten Hidden Beaches in Crete',
                    'blogSlug' => 'travel-stories',
                    'postSlug' => 'ten-hidden-beaches-in-crete',
                    'commenterName' => 'quietreader',
                    'commentExcerpt' => 'Loved the section on Balos — going there next month!',
                    'awaitingModeration' => true,
                    'commentId' => 128,
                    'blogId' => 7,
                ],
            ],
            'comment_moderation' => [
                'name' => 'Comment Awaiting Moderation',
                'description' => 'Asks a blog owner or editor to approve a comment held for moderation',
                'group' => 'Comments',
                'class' => 'App\\Mail\\CommentModerationMail',
                'sample_data' => [
                    'toEmail' => 'owner@example.com',
                    'postTitle' => 'Ten Hidden Beaches in Crete',
                    'blogSlug' => 'travel-stories',
                    'postSlug' => 'ten-hidden-beaches-in-crete',
                    'commenterName' => 'quietreader',
                    'commentExcerpt' => 'Loved the section on Balos — going there next month!',
                    'awaitingModeration' => true,
                    'commentId' => 128,
                    'blogId' => 7,
                ],
            ],
            'blog_comment' => [
                'name' => 'Comment on Your Blog',
                'description' => 'Tells a blog owner a reader commented anywhere on their blog',
                'group' => 'Comments',
                'class' => 'App\\Mail\\BlogCommentMail',
                'sample_data' => [
                    'toEmail' => 'owner@example.com',
                    'postTitle' => 'Ten Hidden Beaches in Crete',
                    'blogSlug' => 'travel-stories',
                    'postSlug' => 'ten-hidden-beaches-in-crete',
                    'commenterName' => 'quietreader',
                    'commentExcerpt' => 'Loved the section on Balos — going there next month!',
                    'awaitingModeration' => false,
                    'commentId' => 128,
                    'blogId' => 7,
                ],
            ],
            'blog_comment_pending' => [
                'name' => 'Comment on Your Blog (Awaiting Approval)',
                'description' => 'Tells a blog owner about a new comment that is waiting for approval',
                'group' => 'Comments',
                'class' => 'App\\Mail\\BlogCommentPendingMail',
                'sample_data' => [
                    'toEmail' => 'owner@example.com',
                    'postTitle' => 'Ten Hidden Beaches in Crete',
                    'blogSlug' => 'travel-stories',
                    'postSlug' => 'ten-hidden-beaches-in-crete',
                    'commenterName' => 'quietreader',
                    'commentExcerpt' => 'Loved the section on Balos — going there next month!',
                    'awaitingModeration' => true,
                    'commentId' => 128,
                    'blogId' => 7,
                ],
            ],
            'workflow_disabled' => [
                'name' => 'Review Workflow Disabled',
                'description' => 'Notifies authors with pending posts that review was switched off',
                'group' => 'Review workflow',
                'class' => 'App\\Mail\\WorkflowDisabledMail',
                'sample_data' => [
                    'toEmail' => 'author@example.com',
                    'postId' => 42,
                    'postTitle' => 'Ten Hidden Beaches in Crete',
                    'blogName' => 'Travel Stories',
                ],
            ],

            // Platform
            'contact_message' => [
                'name' => 'Contact Message',
                'description' => 'A visitor message from the public contact form, delivered to the admin email',
                'group' => 'Platform',
                'class' => 'App\\Mail\\ContactMessageMail',
                'sample_data' => [
                    'toEmail' => 'admin@example.com',
                    'senderName' => 'Maria Papadopoulou',
                    'senderEmail' => 'maria@example.com',
                    'messageSubject' => 'Question about team roles',
                    'messageBody' => "Hi,\n\nCan a reviewer also write posts on the same blog?\n\nThanks!",
                ],
            ],
        ];
    }

    /**
     * Templates grouped for display, keyed by group label.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getGrouped(): array
    {
        $grouped = [];
        foreach ($this->getAll() as $key => $template) {
            $grouped[$template['group'] ?? 'Other'][$key] = $template;
        }

        return $grouped;
    }

    /**
     * Mailable classes on disk that are missing from the registry.
     *
     * Guards against new notification emails silently not appearing on
     * the admin Email Templates page.
     *
     * @return string[] Fully qualified class names
     */
    public function unregisteredClasses(): array
    {
        $registered = array_column($this->getAll(), 'class');

        // Infrastructure Mailables that are not user-facing templates and so
        // never belong on the preview page. QueuedMail only re-wraps an
        // already-rendered queue row; it has no sample data to preview.
        $infrastructure = [Mailable::class, QueuedMail::class];

        $missing = [];
        foreach (glob(ROOT_PATH.'/src/App/Mail/*.php') ?: [] as $file) {
            $class = 'App\\Mail\\'.basename($file, '.php');

            if (in_array($class, $infrastructure, true) || in_array($class, $registered, true)) {
                continue;
            }

            // Only concrete Mailable subclasses belong on the page; a shared
            // base such as CommentMail is never sent itself.
            if (class_exists($class) && is_subclass_of($class, Mailable::class) && !(new \ReflectionClass($class))->isAbstract()) {
                $missing[] = $class;
            }
        }

        return $missing;
    }

    /**
     * Get metadata for a specific template.
     *
     * @param  string  $templateKey  Template identifier
     * @return array<string, mixed>|null Template data or null if not found
     */
    public function get(string $templateKey): ?array
    {
        $templates = $this->getAll();

        return $templates[$templateKey] ?? null;
    }

    /**
     * Instantiate email template with sample data.
     *
     * use reflection to create instances of Mailable classes,
     * injecting sample data for preview and testing purposes.
     *
     * @param  string  $templateKey  Template identifier
     * @return Mailable Instantiated email template
     *
     * @throws Exception If template not found or instantiation fails
     */
    public function instantiate(string $templateKey): Mailable
    {
        try {
            return $this->build($templateKey);
        } catch (Exception $e) {
            error_log("Failed to instantiate email template '{$templateKey}': ".$e->getMessage());
            throw new Exception('Failed to create email template: '.$e->getMessage());
        }
    }

    /**
     * Instantiate with sample data, letting whatever goes wrong surface as is.
     *
     * For the template editor, which builds every email against a draft to see
     * which ones it would break. Those failures are the expected answer to a
     * question, not errors, so nothing is logged.
     *
     * @throws Exception If the template is unknown or the email cannot be built
     */
    public function build(string $templateKey): Mailable
    {
        $template = $this->get($templateKey);

        if (!$template) {
            throw new Exception("Email template '{$templateKey}' not found");
        }

        $className = $template['class'];

        if (!class_exists($className)) {
            throw new Exception("Email class '{$className}' does not exist");
        }

        $data = $template['sample_data'];

        // use reflection to determine constructor parameters
        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return new $className();
        }

        // map sample data to constructor parameters
        $params = [];
        foreach ($constructor->getParameters() as $param) {
            $paramName = $param->getName();

            if (isset($data[$paramName])) {
                $params[] = $data[$paramName];
            } elseif ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
            } else {
                throw new Exception("Missing required parameter '{$paramName}' for {$className}");
            }
        }

        /** @var Mailable */
        return $reflection->newInstanceArgs($params);
    }
}
