<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Interfaces\SchedulableCommandInterface;
use App\Models\AccountErasureRecordModel;
use App\Models\ActivityLogModel;
use App\Models\BlogInvitationModel;
use App\Models\BlogSubscriberModel;
use App\Models\MailQueueModel;
use App\Models\PasswordResetModel;
use App\Models\PendingEmailChangeModel;

/**
 * Deletes personal data that has outlived its purpose, using the periods in
 * config/privacy.php.
 *
 * Usage: php cli privacy:prune
 */
class PrivacyPruneCommand implements SchedulableCommandInterface
{
    public function __construct(
        private ActivityLogModel $activityLog,
        private MailQueueModel $mailQueue,
        private PasswordResetModel $passwordResets,
        private PendingEmailChangeModel $emailChanges,
        private BlogInvitationModel $invitations,
        private BlogSubscriberModel $subscribers,
        private AccountErasureRecordModel $erasureRecords,
    ) {}

    public static function scheduleLabel(): string
    {
        return 'Apply data retention periods';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function argumentSchema(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function handle(array $arguments = []): int
    {
        $days = (require ROOT_PATH.'/config/privacy.php')['retention_days'];

        $deleted = [
            'Audit log entries' => $this->activityLog->pruneOlderThan((int) $days['activity_log']),
            'Delivered emails' => $this->mailQueue->pruneSent((int) $days['mail_queue']),
            'Failed emails' => $this->mailQueue->pruneFailed((int) $days['mail_queue']),
            'Expired password reset links' => $this->passwordResets->deleteExpired(),
            'Expired email change links' => $this->emailChanges->deleteExpired(),
            'Expired invitations' => $this->invitations->deleteExpired(),
            'Answered invitations' => $this->invitations->deleteSettled((int) $days['answered_invitations']),
            'Unconfirmed subscriptions' => $this->subscribers->deleteUnconfirmedOlderThan((int) $days['unconfirmed_subscriptions']),
            'Account erasure records' => $this->erasureRecords->pruneOlderThan((int) $days['account_erasure_records']),
        ];

        foreach ($deleted as $label => $count) {
            echo "{$label}: {$count}\n";
        }

        return 0;
    }
}
