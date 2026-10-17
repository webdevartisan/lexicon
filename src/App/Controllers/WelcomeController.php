<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BlogModel;
use Framework\Core\Response;

/**
 * "What would you like to do?" for a new account whose sign-up didn't say:
 * start a blog, or read and follow blogs.
 */
final class WelcomeController extends AppController
{
    public function __construct(
        private BlogModel $blogs,
    ) {}

    /**
     * Someone who already writes somewhere has made that choice, so they go
     * to their dashboard instead.
     */
    public function index(): Response
    {
        $userId = (int) auth()->user()['id'];
        if ($this->blogs->listAccessibleBlogs($userId) !== []) {
            return $this->redirect('/dashboard');
        }

        return $this->view('public.Welcome.index');
    }
}
