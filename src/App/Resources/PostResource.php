<?php

namespace App\Resources;

use App\Models\PostModel;

/**
 * PostResource
 *
 * We wrap a raw post row and give policies/controllers a clean, typed API.
 */
final class PostResource
{
    public function __construct(
        private array $data,
        private PostModel $model,
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

    public function comments_enabled(): string
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

    /** Convert back to array for views. */
    public function toArray(): array
    {
        return $this->data;
    }
}
