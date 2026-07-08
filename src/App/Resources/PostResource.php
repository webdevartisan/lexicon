<?php

declare(strict_types=1);

namespace App\Resources;

/**
 * PostResource
 *
 * We wrap a raw post row and give policies/controllers a clean, typed API.
 */
class PostResource
{
    /**
     * @param  array<string, mixed>  $data  Raw post row from the database
     */
    public function __construct(
        private array $data,
        private BlogResource $blog,
    ) {
        // should keep $data raw but only expose via accessors to centralize logic.
    }

    /** Primary key. */
    public function id(): int
    {
        return (int) $this->data['id'];
    }

    /** Blog the post belongs to. */
    public function blogId(): int
    {
        return (int) $this->data['blog_id'];
    }

    /** Author user id. */
    public function authorId(): int
    {
        return (int) $this->data['author_id'];
    }

    public function title(): string
    {
        return $this->data['title'];
    }

    public function slug(): string
    {
        return $this->data['slug'];
    }

    public function content(): string
    {
        return $this->data['content'];
    }

    public function excerpt(): string
    {
        return $this->data['excerpt'];
    }

    public function publishedAt(): ?string
    {
        return $this->data['published_at'];
    }

    public function timezone(): string
    {
        return $this->data['timezone'];
    }

    /** Visibility status: draft/published/archived. */
    public function status(): string
    {
        return $this->data['status'];
    }

    public function comments_enabled(): int
    {
        return $this->data['comments_enabled'];
    }

    public function workflowState(): string
    {
        return $this->data['workflow_state'];
    }

    /** Related BlogResource, for per-blog role checks. */
    public function blog(): BlogResource
    {
        return $this->blog;
    }

    /**
     * Convert back to array for views.
     *
     * @return array<string, mixed> Raw post row
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
