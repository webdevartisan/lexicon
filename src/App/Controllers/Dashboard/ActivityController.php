<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Models\CommentModel;
use Framework\Core\Response;

/**
 * The reader's interactions: their comments and the replies they received.
 */
class ActivityController extends AppController
{
    public function __construct(
        private CommentModel $comments,
    ) {}

    public function index(): Response
    {
        $userId = (int) auth()->user()['id'];

        return $this->view('activity.index', [
            'replies' => $this->comments->repliesToUser($userId, 20),
            'myComments' => $this->comments->byUserWithContext($userId, 20),
        ]);
    }
}
