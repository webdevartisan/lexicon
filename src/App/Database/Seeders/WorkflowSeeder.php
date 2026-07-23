<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use Faker\Generator;

/**
 * Seeds the editorial pipeline for blogs that have workflow enabled: a slice of
 * their reviewable posts get reviewer assignments and review feedback, and each
 * such blog collects a few contributor submissions. This gives the review queue
 * and submissions screens real rows to work with.
 */
final class WorkflowSeeder extends Seeder
{
    public function seed(SeedConfig $config, SeedContext $context, Generator $faker): void
    {
        $reviewers = [];
        $reviews = [];
        $submissions = [];
        $postsUnderReview = [];

        foreach ($context->blogs as $blog) {
            if (!$blog['workflow']) {
                continue;
            }

            $blogId = $blog['id'];
            $ownerId = $blog['owner_id'];
            $members = $blog['members'];

            $reviewable = $context->reviewablePostsByBlog[$blogId] ?? [];
            foreach ($this->randomSubset($reviewable, 0, 5) as $postId) {
                $postId = (int) $postId;
                $postsUnderReview[] = $postId;

                foreach ($this->randomSubset($members, 1, 2) as $reviewerId) {
                    $reviewerId = (int) $reviewerId;

                    $reviewers[] = [
                        'post_id' => $postId,
                        'reviewer_id' => $reviewerId,
                        'assigned_by' => $ownerId,
                        'review_status' => $faker->randomElement(['pending', 'in_progress', 'completed']),
                    ];

                    // Roughly half of assignments have left feedback so far.
                    if ($faker->boolean(50)) {
                        $reviews[] = [
                            'post_id' => $postId,
                            'reviewer_id' => $reviewerId,
                            'feedback' => $faker->paragraph(),
                            'decision' => $faker->randomElement(['pending', 'approved', 'rejected', 'needs_revision']),
                        ];
                    }
                }
            }

            for ($n = random_int(1, 4); $n > 0; $n--) {
                $submissions[] = [
                    'blog_id' => $blogId,
                    'contributor_id' => (int) $faker->randomElement($members),
                    'title' => rtrim($faker->sentence(6), '.'),
                    'content' => $faker->paragraphs(2, true),
                    'notes' => $faker->sentence(12),
                    'status' => $faker->randomElement(['submitted', 'under_review', 'accepted', 'rejected']),
                ];
            }
        }

        $this->insertMany('post_reviewers', $reviewers);
        $this->insertMany('reviews', $reviews);
        $this->insertMany('submissions', $submissions);
        $this->markUnderReview($postsUnderReview);
    }

    /**
     * Flip the assigned posts to the in_review workflow state so the pipeline is
     * internally consistent with the reviewer rows we just created.
     *
     * @param  array<int, int>  $postIds
     */
    private function markUnderReview(array $postIds): void
    {
        if ($postIds === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($postIds), '?'));
        $this->db->execute(
            "UPDATE posts SET workflow_state = 'in_review' WHERE id IN ({$placeholders})",
            $postIds
        );
    }
}
