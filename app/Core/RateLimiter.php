<?php
declare(strict_types=1);

namespace UMP\Core;

/** Fixed-window limiter stored in MySQL (works on shared hosting, no Redis). */
final class RateLimiter
{
    /** Registers a hit; returns false once $max hits occurred inside $seconds. */
    public static function hit(string $bucket, int $max, int $seconds): bool
    {
        $bucket = substr(hash('sha256', $bucket), 0, 40);
        $row = DB::one('SELECT hits, window_start FROM rate_limits WHERE bucket = ?', [$bucket]);
        $now = time();

        if (!$row || strtotime($row['window_start']) + $seconds < $now) {
            DB::run(
                'INSERT INTO rate_limits (bucket, hits, window_start) VALUES (?, 1, NOW())
                 ON DUPLICATE KEY UPDATE hits = 1, window_start = NOW()',
                [$bucket]
            );
            return true;
        }
        if ((int) $row['hits'] >= $max) {
            return false;
        }
        DB::run('UPDATE rate_limits SET hits = hits + 1 WHERE bucket = ?', [$bucket]);
        return true;
    }
}
