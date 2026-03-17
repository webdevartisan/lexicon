<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ProfileService;
use Framework\Core\Response;

/**
 * Handles rendering of public user profiles.
 *
 * Displays public profile information at /profile/{slug} including
 * social links and recent public posts. Only shows profiles explicitly
 * marked as public.
 */
final class PublicProfileController extends AppController
{
    public function __construct(
        private ProfileService $profileService
    ) {}

    /**
     * Display a public profile page.
     *
     * Returns 404 for both nonexistent and private profiles to avoid
     * information disclosure about profile existence or privacy status.
     *
     * @param string $slug Public profile slug from URL
     * @return Response Rendered profile view
     * @throws \Framework\Exceptions\NotFoundException If profile not found or not public
     */
    public function show(string $slug): Response
    {
        $data = $this->profileService->getPublicProfile($slug);

        return $this->view('profile.show', $data);
    }
}
