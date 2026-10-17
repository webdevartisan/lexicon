<?php

declare(strict_types=1);

beforeEach(function () {
    $this->ran = new ArrayObject();
});

test('work runs at once when no transaction is open', function () {
    $this->db->afterCommit(fn () => $this->ran[] = 'now');

    expect($this->ran->getArrayCopy())->toBe(['now']);
});

test('work inside a transaction waits for the commit, then runs in order', function () {
    $this->db->beginTransaction();
    $this->db->afterCommit(fn () => $this->ran[] = 'first');
    $this->db->afterCommit(fn () => $this->ran[] = 'second');

    expect($this->ran->getArrayCopy())->toBe([]);

    $this->db->commit();

    expect($this->ran->getArrayCopy())->toBe(['first', 'second']);
});

test('the transaction helper runs queued work after its commit', function () {
    $this->db->transaction(function () {
        $this->db->afterCommit(fn () => $this->ran[] = 'cleared');
        expect($this->ran->getArrayCopy())->toBe([]);
    });

    expect($this->ran->getArrayCopy())->toBe(['cleared']);
});

test('a rollback throws the queued work away', function () {
    $this->db->beginTransaction();
    $this->db->afterCommit(fn () => $this->ran[] = 'never');
    $this->db->rollback();

    $this->db->beginTransaction();
    $this->db->commit();

    expect($this->ran->getArrayCopy())->toBe([]);
});

test('a failed transaction helper call throws the queued work away', function () {
    expect(fn () => $this->db->transaction(function () {
        $this->db->afterCommit(fn () => $this->ran[] = 'never');
        throw new RuntimeException('save failed');
    }))->toThrow(RuntimeException::class, 'save failed');

    expect($this->ran->getArrayCopy())->toBe([]);
});

test('a commit that fails throws the queued work away', function () {
    $this->db->beginTransaction();
    $this->db->afterCommit(fn () => $this->ran[] = 'never');

    // Ending the transaction behind Database's back makes its own commit fail.
    $this->db->getConnection()->commit();

    expect(fn () => $this->db->commit())->toThrow(RuntimeException::class, 'Failed to commit transaction');
    expect($this->ran->getArrayCopy())->toBe([]);

    $this->db->beginTransaction();
    $this->db->commit();

    expect($this->ran->getArrayCopy())->toBe([]);
});

test('one failing piece of work is logged and the rest still run', function () {
    $log = tempnam(sys_get_temp_dir(), 'aftercommit');
    $previous = ini_set('error_log', $log);

    try {
        $this->db->beginTransaction();
        $this->db->afterCommit(function () {
            throw new RuntimeException('cache unreachable');
        });
        $this->db->afterCommit(fn () => $this->ran[] = 'still ran');

        expect($this->db->commit())->toBeTrue()
            ->and($this->ran->getArrayCopy())->toBe(['still ran'])
            ->and((string) file_get_contents($log))->toContain('After-commit callback failed: cache unreachable');
    } finally {
        ini_set('error_log', (string) $previous);
        @unlink($log);
    }
});
