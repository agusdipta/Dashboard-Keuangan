<?php
/** Sesi dan lock tinggal di PostgreSQL agar konsisten antar-instance Vercel. */
final class DatabaseSession implements SessionHandlerInterface
{
    public function __construct(private PDO $db) {}
    public function open(string $path, string $name): bool { return true; }
    public function read(string $id): string|false
    {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
        $lock = $this->db->prepare('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))');
        $lock->execute([$id]);
        $lock->closeCursor();
        $stmt = $this->db->prepare('SELECT data FROM app_session WHERE id = ? AND expires_at > CURRENT_TIMESTAMP');
        $stmt->execute([$id]);
        $data = $stmt->fetchColumn() ?: '';
        $stmt->closeCursor();
        return $data;
    }
    public function write(string $id, string $data): bool
    {
        // Jangan menyimpan/commit setelah handler error melakukan rollback.
        if (!$this->db->inTransaction()) {
            return false;
        }
        $stmt = $this->db->prepare("INSERT INTO app_session (id, data, expires_at)
            VALUES (?, ?, CURRENT_TIMESTAMP + INTERVAL '2 hours')
            ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, expires_at = EXCLUDED.expires_at");
        $written = $stmt->execute([$id, $data]);
        $stmt->closeCursor();
        return $written;
    }
    public function destroy(string $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM app_session WHERE id = ?');
        $deleted = $stmt->execute([$id]);
        $stmt->closeCursor();
        return $deleted;
    }
    public function gc(int $max_lifetime): int|false
    {
        return $this->db->exec('DELETE FROM app_session WHERE expires_at <= CURRENT_TIMESTAMP');
    }
    public function close(): bool
    {
        return !$this->db->inTransaction() || $this->db->commit();
    }
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    session_set_cookie_params([
        'secure' => (bool) getenv('VERCEL') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true, 'samesite' => 'Lax', 'path' => '/',
    ]);
    session_set_save_handler(new DatabaseSession($koneksi), true);
    session_start();
}
