<?php
class Queue {
    public static function push(string $jobType, array $payload): void {
        $pdo = DB::connect();
        $stmt = $pdo->prepare("
            INSERT INTO job_queue (job_type, payload, status)
            VALUES (?, ?, 'pending')
        ");
        $stmt->execute([$jobType, json_encode($payload)]);
    }

    public static function pop(int $limit = 10): array {
        $pdo = DB::connect();
        // Marcar como 'processing' e retornar atomicamente
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            SELECT * FROM job_queue
            WHERE status = 'pending'
              AND attempts < max_attempts
              AND scheduled_at <= NOW()
            ORDER BY created_at ASC
            LIMIT ?
            FOR UPDATE SKIP LOCKED
        ");
        $stmt->execute([$limit]);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($jobs as $job) {
            $pdo->prepare("
                UPDATE job_queue
                SET status = 'processing', started_at = NOW(), attempts = attempts + 1
                WHERE id = ?
            ")->execute([$job['id']]);
        }
        $pdo->commit();
        return $jobs;
    }

    public static function done(string $jobId): void {
        DB::connect()->prepare("
            UPDATE job_queue SET status = 'done', done_at = NOW() WHERE id = ?
        ")->execute([$jobId]);
    }

    public static function fail(string $jobId, string $error): void {
        $pdo = DB::connect();
        // Se excedeu tentativas, marca como failed; senão volta para pending com delay
        $pdo->prepare("
            UPDATE job_queue
            SET status = IF(attempts >= max_attempts, 'failed', 'pending'),
                error = ?,
                scheduled_at = DATE_ADD(NOW(), INTERVAL (attempts * 30) SECOND)
            WHERE id = ?
        ")->execute([$error, $jobId]);
    }
}
