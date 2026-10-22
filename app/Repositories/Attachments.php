<?php
declare(strict_types=1);

namespace UMP\Repositories;

use UMP\Core\DB;

/**
 * Scanned delivery notes / GRV / GIV documents attached to a day's record.
 * Files are stored under storage/uploads with random names (blocked from the web) and are streamed only to signed-in users.
 */
final class Attachments
{
    public const MAX_BYTES = 8 * 1024 * 1024;
    public const MAX_PER_RECORD = 8;
    private const TYPES = ['application/pdf' => 'PDF', 'image/jpeg' => 'JPG', 'image/png' => 'PNG', 'image/webp' => 'WebP'];

    public static function dir(): string
    {
        return UMP_ROOT . '/storage/uploads';
    }

    public static function forRecord(int $recordId): array
    {
        return DB::all('SELECT a.*, u.name AS uploader FROM stock_attachments a LEFT JOIN users u ON u.id = a.uploaded_by WHERE a.record_id = ? ORDER BY a.id', [$recordId]);
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM stock_attachments WHERE id = ?', [$id]);
    }

    public static function path(array $row): string
    {
        return self::dir() . '/' . basename($row['stored_name']);
    }

    /** Normalise $_FILES['x'] (single or multiple) into a list of single-file arrays. */
    public static function normalise(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return [$files];
        }
        $out = [];
        foreach (array_keys($files['name']) as $i) {
            $out[] = ['name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
        }
        return $out;
    }

    /** @throws \RuntimeException with a user-presentable message */
    public static function add(int $recordId, array $file, int $userId): int
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new \RuntimeException('Choose a file first.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('The upload failed (error ' . (int) $file['error'] . '). Try a smaller file.');
        }
        if ($file['size'] > self::MAX_BYTES) {
            throw new \RuntimeException('Files must be 8 MB or smaller.');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Invalid upload.');
        }
        if ((int) DB::val('SELECT COUNT(*) FROM stock_attachments WHERE record_id = ?', [$recordId]) >= self::MAX_PER_RECORD) {
            throw new \RuntimeException('A record can have at most ' . self::MAX_PER_RECORD . ' documents.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::TYPES[$mime])) {
            throw new \RuntimeException('Only PDF, JPG, PNG or WebP files are allowed.');
        }
        if ($mime === 'application/pdf' && file_get_contents($file['tmp_name'], false, null, 0, 5) !== '%PDF-') {
            throw new \RuntimeException('That file is not a valid PDF.');
        }
        if ($mime !== 'application/pdf' && @getimagesize($file['tmp_name']) === false) {
            throw new \RuntimeException('That file is not a valid image.');
        }

        $dir = self::dir();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('The upload folder is not writable.');
        }
        $stored = bin2hex(random_bytes(20));
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
            throw new \RuntimeException('Could not save the uploaded file.');
        }
        $name = preg_replace('/[^\p{L}\p{N} ._()\-]+/u', '_', basename((string) $file['name']));
        return DB::insert('stock_attachments', [
            'record_id' => $recordId,
            'original_name' => mb_substr($name !== '' ? $name : 'document', 0, 190),
            'stored_name' => $stored,
            'mime' => $mime,
            'size' => (int) $file['size'],
            'uploaded_by' => $userId,
        ]);
    }

    public static function remove(int $id): void
    {
        $row = self::find($id);
        if (!$row) {
            return;
        }
        DB::run('DELETE FROM stock_attachments WHERE id = ?', [$id]);
        $path = self::path($row);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
