<?php

declare(strict_types=1);

namespace Framework\Session;

/**
 * A place session data can live, chosen by SESSION_DRIVER.
 *
 * Besides PHP's handler methods, a driver says whether it keeps the
 * user_sessions row itself (so the row can be read without a second query)
 * and how to drop data for sessions revoked from elsewhere. Drivers mint
 * session ids themselves (create_sid), so a session started on this request
 * is known to be new and needs no storage round trip until it is saved.
 */
interface SessionDriver extends \SessionHandlerInterface, \SessionIdInterface, \SessionUpdateTimestampHandlerInterface
{
    /**
     * True when the session data and its user_sessions row are the same record.
     */
    public function storesRecords(): bool;

    /**
     * The user_sessions row loaded for this session id, when storesRecords() is true.
     *
     * @return array<string, mixed>|null
     */
    public function record(string $id): ?array;

    /**
     * Remove the stored data of sessions that were revoked by their hash.
     *
     * Drivers that cannot address data by hash rely on the missing row: the
     * data is discarded the next time that session is opened.
     *
     * @param  list<string>  $hashes
     */
    public function forgetHashes(array $hashes): void;

    /**
     * The address the current request came from, for drivers that record it.
     */
    public function setClientIp(?string $ip): void;
}
