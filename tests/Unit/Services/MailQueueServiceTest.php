<?php

declare(strict_types=1);

use App\Mail\Mailable;
use App\Models\MailQueueModel;
use App\Services\MailQueueService;
use App\Services\MailService;

/**
 * Unit tests for MailQueueService.
 *
 * Covers what enqueue() persists from a Mailable and how processBatch()
 * routes each send outcome, with the model and transport mocked so no
 * database or SMTP connection is involved.
 */

/** A minimal HTML Mailable for enqueue assertions. */
function makeTestMailable(string $to = 'reader@example.test', string $name = ''): Mailable
{
    return new class($to, $name) extends Mailable
    {
        public function __construct(private string $addr, private string $addrName)
        {
            parent::__construct();
        }

        public function build(): void
        {
            $this->to($this->addr, $this->addrName)
                ->subject('New post')
                ->html('<p>Hello</p>')
                ->textAlternative('Hello');
        }
    };
}

/** Build the service with mocked collaborators. */
function makeMailQueueService($queue = null, $mailer = null, array $config = []): MailQueueService
{
    $mailer ??= Mockery::mock(MailService::class);

    // Every test that reaches processBatch() needs mail switched on; the
    // disabled case overrides this with its own expectation.
    if ($mailer instanceof Mockery\MockInterface) {
        $mailer->shouldReceive('enabled')->andReturn(true)->byDefault();
    }

    return new MailQueueService(
        $queue ?? Mockery::mock(MailQueueModel::class),
        $mailer,
        // No delay in tests; pacing is real behaviour but would only slow the suite.
        array_merge(['batch_size' => 10, 'delay_ms' => 0, 'backoff_seconds' => 60], $config)
    );
}

describe('MailQueueService::enqueue', function () {

    test('stores the rendered content and the related source', function () {
        $queue = Mockery::mock(MailQueueModel::class);
        $queue->shouldReceive('enqueue')
            ->once()
            ->with(Mockery::on(fn ($row) => $row['to_email'] === 'reader@example.test'
                && $row['subject'] === 'New post'
                && $row['body_html'] === '<p>Hello</p>'
                && $row['body_text'] === 'Hello'
                && $row['related_type'] === 'post'
                && $row['related_id'] === 42))
            ->andReturn(1);

        $service = makeMailQueueService($queue);

        expect($service->enqueue(makeTestMailable(), 'post', 42))->toBe(1);
    });

    test('queues one row per recipient', function () {
        $mailable = new class() extends Mailable
        {
            public function build(): void
            {
                $this->to('one@example.test')
                    ->to('two@example.test')
                    ->subject('New post')
                    ->html('<p>Hello</p>');
            }
        };

        $queue = Mockery::mock(MailQueueModel::class);
        $queue->shouldReceive('enqueue')->twice()->andReturn(1);

        expect(makeMailQueueService($queue)->enqueue($mailable))->toBe(2);
    });

    test('keeps a plain-text mailable readable instead of queueing an empty body', function () {
        $mailable = new class() extends Mailable
        {
            public function build(): void
            {
                $this->to('reader@example.test')
                    ->subject('Plain')
                    ->text('Just text');
            }
        };

        $queue = Mockery::mock(MailQueueModel::class);
        $queue->shouldReceive('enqueue')
            ->once()
            ->with(Mockery::on(fn ($row) => $row['body_text'] === 'Just text'))
            ->andReturn(1);

        makeMailQueueService($queue)->enqueue($mailable);

        expect(true)->toBeTrue();
    });
});

describe('MailQueueService::processBatch', function () {

    test('marks a delivered row as sent', function () {
        $row = ['id' => 7, 'to_email' => 'a@b.test', 'to_name' => null, 'subject' => 'S', 'body_html' => '<p>x</p>', 'body_text' => 'x'];

        $queue = Mockery::mock(MailQueueModel::class);
        $queue->shouldReceive('claimBatch')->once()->with(10)->andReturn([$row]);
        $queue->shouldReceive('markSent')->once()->with(7)->andReturn(true);

        $mailer = Mockery::mock(MailService::class);
        $mailer->shouldReceive('enabled')->andReturn(true);
        $mailer->shouldReceive('sendOrFail')->once()->andReturn(true);

        $result = makeMailQueueService($queue, $mailer)->processBatch();

        expect($result['sent'])->toBe(1)
            ->and($result['claimed'])->toBe(1)
            ->and($result['failed'])->toBe(0);
    });

    test('counts a requeued row as retrying, not failed', function () {
        $row = ['id' => 7, 'to_email' => 'a@b.test', 'to_name' => null, 'subject' => 'S', 'body_html' => '<p>x</p>', 'body_text' => 'x'];

        $queue = Mockery::mock(MailQueueModel::class);
        $queue->shouldReceive('claimBatch')->andReturn([$row]);
        $queue->shouldReceive('markFailed')->once()->with(7, 'rate limited', 60)->andReturn('pending');

        $mailer = Mockery::mock(MailService::class);
        $mailer->shouldReceive('enabled')->andReturn(true);
        $mailer->shouldReceive('sendOrFail')->andThrow(new Exception('rate limited'));

        $result = makeMailQueueService($queue, $mailer)->processBatch();

        expect($result['retrying'])->toBe(1)
            ->and($result['failed'])->toBe(0);
    });

    test('counts an exhausted row as failed', function () {
        $row = ['id' => 7, 'to_email' => 'a@b.test', 'to_name' => null, 'subject' => 'S', 'body_html' => '<p>x</p>', 'body_text' => 'x'];

        $queue = Mockery::mock(MailQueueModel::class);
        $queue->shouldReceive('claimBatch')->andReturn([$row]);
        $queue->shouldReceive('markFailed')->andReturn('failed');

        $mailer = Mockery::mock(MailService::class);
        $mailer->shouldReceive('enabled')->andReturn(true);
        $mailer->shouldReceive('sendOrFail')->andThrow(new Exception('permanent'));

        expect(makeMailQueueService($queue, $mailer)->processBatch()['failed'])->toBe(1);
    });

    test('one failure does not stop the rest of the batch', function () {
        $rows = [
            ['id' => 1, 'to_email' => 'a@b.test', 'to_name' => null, 'subject' => 'S', 'body_html' => '<p>x</p>', 'body_text' => 'x'],
            ['id' => 2, 'to_email' => 'c@d.test', 'to_name' => null, 'subject' => 'S', 'body_html' => '<p>x</p>', 'body_text' => 'x'],
            ['id' => 3, 'to_email' => 'e@f.test', 'to_name' => null, 'subject' => 'S', 'body_html' => '<p>x</p>', 'body_text' => 'x'],
        ];

        $queue = Mockery::mock(MailQueueModel::class);
        $queue->shouldReceive('claimBatch')->andReturn($rows);
        $queue->shouldReceive('markSent')->twice()->andReturn(true);
        $queue->shouldReceive('markFailed')->once()->andReturn('pending');

        $mailer = Mockery::mock(MailService::class);
        $mailer->shouldReceive('enabled')->andReturn(true);
        $mailer->shouldReceive('sendOrFail')
            ->times(3)
            ->andReturnUsing(function ($mail) {
                if (array_key_first($mail->getTo()) === 'c@d.test') {
                    throw new Exception('refused');
                }

                return true;
            });

        $result = makeMailQueueService($queue, $mailer)->processBatch();

        expect($result['sent'])->toBe(2)
            ->and($result['retrying'])->toBe(1);
    });

    test('leaves the queue untouched when mail is disabled', function () {
        $queue = Mockery::mock(MailQueueModel::class);
        // Claiming would spend attempts on a transport that cannot run.
        $queue->shouldReceive('claimBatch')->never();

        $mailer = Mockery::mock(MailService::class);
        $mailer->shouldReceive('enabled')->andReturn(false);
        $mailer->shouldReceive('sendOrFail')->never();

        $result = makeMailQueueService($queue, $mailer)->processBatch();

        expect($result['skipped'])->toBeTrue()
            ->and($result['claimed'])->toBe(0);
    });

    test('honours a configured batch size', function () {
        $queue = Mockery::mock(MailQueueModel::class);
        $queue->shouldReceive('claimBatch')->once()->with(3)->andReturn([]);

        makeMailQueueService($queue, null, ['batch_size' => 3])->processBatch();

        expect(true)->toBeTrue();
    });
});
