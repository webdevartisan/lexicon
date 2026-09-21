<?php

declare(strict_types=1);

use App\Models\ModerationCategoryModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use App\Services\ModerationConfigService;
use App\Services\ModerationSettings;
use Tests\Factories\UserFactory;
use Tests\Helpers\ModerationTestHelper;

/**
 * The moderation settings screen refuses anything the rule engine would hold
 * back or that would let the system act on people too easily, and records
 * every change it does make.
 */
beforeEach(function () {
    $this->snapshot = ModerationTestHelper::snapshotCategories($this->db);
    $this->categories = new ModerationCategoryModel($this->db);
    $this->config = new ModerationConfigService(
        $this->db,
        $this->categories,
        new ModerationSettings(new SettingModel($this->db)),
        ModerationTestHelper::audit($this->db),
    );
    $this->adminId = UserFactory::new(new UserModel($this->db))->create();
});

afterEach(function () {
    ModerationTestHelper::restoreCategories($this->db, $this->snapshot);
    Mockery::close();
});

/**
 * The form as it would arrive for a stored category, with some fields changed.
 */
function reasonForm(array $category, array $changes = []): array
{
    return array_merge([
        'label' => $category['label'],
        'description' => $category['description'],
        'severity' => $category['severity'],
        'auto_action' => $category['auto_action'],
        'threshold' => (string) $category['threshold'],
        'suspension_hours' => (string) $category['suspension_hours'],
        'execution' => $category['execution'],
        'is_active' => (int) $category['is_active'] === 1 ? '1' : '',
    ], $changes);
}

function reasonAudit(\Framework\Database $db): array
{
    return $db->query("SELECT details FROM activity_log WHERE action = 'moderation.category_updated' ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
}

it('refuses a rule that acts on its own after a single report', function () {
    $spam = $this->categories->findBySlug('spam');

    $result = $this->config->checkCategory($spam, reasonForm($spam, ['threshold' => '1']), $this->adminId);

    expect($result['errors'])->toHaveKey('threshold');
});

it('never lets a critical reason act on its own', function () {
    $illegal = $this->categories->findBySlug('illegal');

    $result = $this->config->checkCategory($illegal, reasonForm($illegal, [
        'auto_action' => 'hide_content', 'threshold' => '3', 'execution' => 'automatic',
    ]), $this->adminId);

    expect($result['errors'])->toHaveKey('execution');
});

it('needs an administrator to accept a high severity rule acting on its own, and asks again when the rule changes', function () {
    $harassment = $this->categories->findBySlug('harassment');
    $automatic = reasonForm($harassment, ['execution' => 'automatic']);

    $unticked = $this->config->checkCategory($harassment, $automatic, $this->adminId);
    expect($unticked['errors'])->toHaveKey('acknowledge');

    $ticked = $this->config->checkCategory($harassment, $automatic + ['acknowledge' => '1'], $this->adminId);
    expect($ticked['errors'])->toBe([]);
    $this->config->saveCategory($harassment, $ticked['fields'], $this->adminId, null);

    $saved = $this->categories->findBySlug('harassment');
    expect((int) $saved['automation_acknowledged_by'])->toBe($this->adminId)
        ->and($saved['automation_acknowledged_at'])->not->toBeNull();

    // Renaming it leaves the rule alone, so the acceptance stands
    $renamed = $this->config->checkCategory($saved, reasonForm($saved, ['label' => 'Bullying']), $this->adminId);
    expect($renamed['errors'])->toBe([])
        ->and($renamed['fields']['automation_acknowledged_at'])->toBe($saved['automation_acknowledged_at']);

    $stricter = $this->config->checkCategory($saved, reasonForm($saved, ['threshold' => '2']), $this->adminId);
    expect($stricter['errors'])->toHaveKey('acknowledge');
});

it('drops the acceptance once the rule asks a moderator again', function () {
    $this->db->execute(
        "UPDATE moderation_categories SET execution = 'automatic', automation_acknowledged_by = ?, automation_acknowledged_at = UTC_TIMESTAMP() WHERE slug = 'harassment'",
        [$this->adminId]
    );
    $harassment = $this->categories->findBySlug('harassment');

    $result = $this->config->checkCategory($harassment, reasonForm($harassment, ['execution' => 'confirm']), $this->adminId);

    expect($result['errors'])->toBe([])
        ->and($result['fields']['automation_acknowledged_by'])->toBeNull()
        ->and($result['fields']['automation_acknowledged_at'])->toBeNull();
});

it('needs a suspension length within a year for a suspending rule', function (string $hours) {
    $hate = $this->categories->findBySlug('hate');

    $result = $this->config->checkCategory($hate, reasonForm($hate, ['suspension_hours' => $hours]), $this->adminId);

    expect($result['errors'])->toHaveKey('suspension_hours');
})->with(['empty' => [''], 'zero' => ['0'], 'over a year' => ['8761'], 'not a number' => ['7 days']]);

it('clears the numbers of a reason with no rule and keeps escalation with a person', function () {
    $other = $this->categories->findBySlug('other');
    $none = $this->config->checkCategory($other, reasonForm($other, ['threshold' => '4', 'execution' => 'automatic']), $this->adminId);

    $illegal = $this->categories->findBySlug('illegal');
    $escalate = $this->config->checkCategory($illegal, reasonForm($illegal, ['severity' => 'high', 'execution' => 'automatic']), $this->adminId);

    expect($none['errors'])->toBe([])
        ->and($none['fields']['threshold'])->toBeNull()
        ->and($none['fields']['execution'])->toBe('confirm')
        ->and($escalate['errors'])->toBe([])
        ->and($escalate['fields']['execution'])->toBe('confirm');
});

it('refuses a severity, action or execution that is not on the list', function () {
    $spam = $this->categories->findBySlug('spam');

    $result = $this->config->checkCategory($spam, reasonForm($spam, [
        'severity' => 'extreme', 'auto_action' => 'delete_account', 'execution' => 'sometimes',
    ]), $this->adminId);

    expect($result['errors'])->toHaveKeys(['severity', 'auto_action', 'execution']);
});

it('keeps at least one reason for readers to choose', function () {
    $this->db->execute("UPDATE moderation_categories SET is_active = 0 WHERE slug <> 'other'");
    $other = $this->categories->findBySlug('other');

    $result = $this->config->checkCategory($other, reasonForm($other, ['is_active' => '']), $this->adminId);

    expect($result['errors'])->toHaveKey('is_active');
});

it('saves a change with its old and new values on the record, and writes nothing when nothing changed', function () {
    $spam = $this->categories->findBySlug('spam');

    $unchanged = $this->config->checkCategory($spam, reasonForm($spam), $this->adminId);
    expect($this->config->saveCategory($spam, $unchanged['fields'], $this->adminId, null))->toBe([])
        ->and(reasonAudit($this->db))->toBe([]);

    $changed = $this->config->checkCategory($spam, reasonForm($spam, ['threshold' => '5']), $this->adminId);
    $changes = $this->config->saveCategory($spam, $changed['fields'], $this->adminId, null);
    $audit = json_decode(reasonAudit($this->db)[0], true);

    expect($changes)->toBe(['threshold' => ['from' => 3, 'to' => 5]])
        ->and((int) $this->categories->findBySlug('spam')['threshold'])->toBe(5)
        ->and($audit['slug'])->toBe('spam')
        ->and($audit['changes']['threshold'])->toBe(['from' => 3, 'to' => 5]);
});

it('refuses safeguards outside their bounds', function () {
    $result = $this->config->checkSafeguards([
        'min_reporter_age_days' => '-1',
        'unfounded_limit' => '0',
        'unfounded_window_days' => '90',
        'burst_window_minutes' => '1441',
        'auto_suspension_cooldown_days' => 'thirty',
        'auto_warn_reporters' => '2',
    ]);

    expect(array_keys($result['errors']))->toBe(['min_reporter_age_days', 'unfounded_limit', 'burst_window_minutes', 'auto_suspension_cooldown_days', 'auto_warn_reporters']);
});

it('saves only the safeguards that changed, on the record', function () {
    $form = [
        'min_reporter_age_days' => '7',
        'unfounded_limit' => '3',
        'unfounded_window_days' => '90',
        'burst_window_minutes' => '0',
        'auto_suspension_cooldown_days' => '30',
        'auto_warn_reporters' => '1',
    ];

    $checked = $this->config->checkSafeguards($form);
    $changes = $this->config->saveSafeguards($checked['values'], $this->adminId, null);
    $audit = $this->db->query("SELECT details FROM activity_log WHERE action = 'moderation.settings_updated'")->fetchAll(\PDO::FETCH_COLUMN);

    expect($checked['errors'])->toBe([])
        ->and($changes)->toBe(['moderation.burst_window_minutes' => ['from' => '10', 'to' => 0]])
        ->and((new ModerationSettings(new SettingModel($this->db)))->burstWindowMinutes())->toBe(0)
        ->and($audit)->toHaveCount(1)
        ->and($this->config->saveSafeguards($checked['values'], $this->adminId, null))->toBe([]);
});
