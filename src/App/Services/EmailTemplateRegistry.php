<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\Mailable;
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
                        'username' => 'johndoe',
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
                    'changedByUsername' => 'blogowner',
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
                    'removedByUsername' => 'blogowner',
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
                    'authorUsername' => 'johndoe',
                    'unassigned' => false,
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
                    'assignedByUsername' => 'blogowner',
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
                    'formerReviewerUsername' => 'oldreviewer',
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
                    'reviewerUsername' => 'janereviewer',
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
                    'reviewerUsername' => 'janereviewer',
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
                    'postId' => 42,
                    'postTitle' => 'Ten Hidden Beaches in Crete',
                    'blogSlug' => 'travel-stories',
                    'postSlug' => 'ten-hidden-beaches-in-crete',
                ],
            ],
            'new_post' => [
                'name' => 'New Post for Subscribers',
                'description' => 'Tells blog subscribers a new post is live, with an unsubscribe link',
                'group' => 'Review workflow',
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
            'new_comment' => [
                'name' => 'New Comment',
                'description' => 'Tells the blog owner, editors, and post author that a reader commented',
                'group' => 'Comments',
                'class' => 'App\\Mail\\NewCommentMail',
                'sample_data' => [
                    'toEmail' => 'owner@example.com',
                    'postTitle' => 'Ten Hidden Beaches in Crete',
                    'blogSlug' => 'travel-stories',
                    'postSlug' => 'ten-hidden-beaches-in-crete',
                    'commenterName' => 'quietreader',
                    'commentExcerpt' => 'Loved the section on Balos — going there next month!',
                    'awaitingModeration' => true,
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

        $missing = [];
        foreach (glob(ROOT_PATH.'/src/App/Mail/*.php') ?: [] as $file) {
            $class = 'App\\Mail\\'.basename($file, '.php');

            if ($class === Mailable::class || in_array($class, $registered, true)) {
                continue;
            }

            // Only concrete Mailable subclasses belong on the page
            if (class_exists($class) && is_subclass_of($class, Mailable::class)) {
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
        $template = $this->get($templateKey);

        if (!$template) {
            throw new Exception("Email template '{$templateKey}' not found");
        }

        $className = $template['class'];

        if (!class_exists($className)) {
            throw new Exception("Email class '{$className}' does not exist");
        }

        try {
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

            return $reflection->newInstanceArgs($params);

        } catch (Exception $e) {
            error_log("Failed to instantiate email template '{$templateKey}': ".$e->getMessage());
            throw new Exception('Failed to create email template: '.$e->getMessage());
        }
    }
}
