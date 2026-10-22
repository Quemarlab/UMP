<?php
declare(strict_types=1);

namespace UMP\Core;

/** Append-only activity trail for the admin panel. */
final class Audit
{
    public static function log(string $action, string $entity = '', ?int $entityId = null, string $detail = ''): void
    {
        try {
            DB::insert('audit_logs', [
                'app' => APP_ID,
                'user_id' => $_SESSION['uid'] ?? null,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'detail' => mb_substr($detail, 0, 255),
                'ip' => client_ip(),
            ]);
        } catch (\Throwable $e) {
            error_log('Audit failure: ' . $e->getMessage());
        }
    }
}
