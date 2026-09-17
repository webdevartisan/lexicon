<?php

declare(strict_types=1);

use App\Console\Commands\PrivacyPruneCommand;
use App\Models\AccountErasureRecordModel;
use App\Models\ActivityLogModel;
use App\Models\BlogInvitationModel;
use App\Models\BlogSubscriberModel;
use App\Models\MailQueueModel;
use App\Models\PasswordResetModel;
use App\Models\PendingEmailChangeModel;

test('old audit entries and mail copies are removed and recent ones stay', function () {
    $this->db->execute("INSERT INTO activity_log (action, resource_type, ip_address, created_at) VALUES ('old', 'user', '10.0.0.1', DATE_SUB(NOW(), INTERVAL 400 DAY))");
    $this->db->execute("INSERT INTO activity_log (action, resource_type, ip_address, created_at) VALUES ('recent', 'user', '10.0.0.2', NOW())");
    $this->db->execute("INSERT INTO mail_queue (to_email, subject, body_html, body_text, status, tier, updated_at) VALUES ('a@example.test', 'Hi', 'Hi', 'Hi', 'failed', 'standard', DATE_SUB(NOW(), INTERVAL 40 DAY))");
    $this->db->execute("INSERT INTO account_erasure_records (original_user_id, handle, email, post_ids, comment_ids, created_at) VALUES (999, 'old-erased', 'old@example.test', '[]', '[]', DATE_SUB(NOW(), INTERVAL 91 DAY))");
    $this->db->execute("INSERT INTO account_erasure_records (original_user_id, handle, email, post_ids, comment_ids, created_at) VALUES (998, 'recent-erased', 'recent@example.test', '[]', '[]', NOW())");

    $command = new PrivacyPruneCommand(
        new ActivityLogModel($this->db),
        new MailQueueModel($this->db),
        new PasswordResetModel($this->db),
        new PendingEmailChangeModel($this->db),
        new BlogInvitationModel($this->db),
        new BlogSubscriberModel($this->db),
        new AccountErasureRecordModel($this->db),
    );

    ob_start();
    $exit = $command->handle();
    ob_end_clean();

    $actions = $this->db->query('SELECT action FROM activity_log')->fetchAll(\PDO::FETCH_COLUMN);
    $erasureHandles = $this->db->query('SELECT handle FROM account_erasure_records')->fetchAll(\PDO::FETCH_COLUMN);

    expect($exit)->toBe(0)
        ->and($actions)->toBe(['recent'])
        ->and((int) $this->db->query('SELECT COUNT(*) FROM mail_queue')->fetchColumn())->toBe(0)
        ->and($erasureHandles)->toBe(['recent-erased']);
});
