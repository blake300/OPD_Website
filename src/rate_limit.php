<?php

declare(strict_types=1);

require_once __DIR__ . '/db_conn.php';

/**
 * Initialize rate_limit table if it doesn't exist
 */
function opd_init_rate_limit_table(): void
{
    $pdo = opd_db();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rate_limit (
            id VARCHAR(255) PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            locked_until DATETIME NULL,
            last_attempt DATETIME NOT NULL,
            INDEX idx_identifier_type (identifier, type),
            INDEX idx_locked_until (locked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/**
 * Check if login attempts should be rate limited
 * Returns array with 'allowed' (bool) and 'message' (string)
 */
function opd_check_rate_limit(string $identifier, string $type = 'login'): array
{
    opd_init_rate_limit_table();
    $pdo = opd_db();

    // Clean up old records (older than 24 hours)
    $cleanupStmt = $pdo->prepare(
        "DELETE FROM rate_limit WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
    );
    $cleanupStmt->execute();

    // Get current rate limit record
    $stmt = $pdo->prepare(
        "SELECT * FROM rate_limit WHERE identifier = ? AND type = ? LIMIT 1"
    );
    $stmt->execute([$identifier, $type]);
    $record = $stmt->fetch();

    $now = new DateTime('now', new DateTimeZone('UTC'));

    // No record exists - first attempt
    if (!$record) {
        return ['allowed' => true, 'message' => ''];
    }

    // Check if currently locked
    if ($record['locked_until']) {
        $lockedUntil = new DateTime($record['locked_until'], new DateTimeZone('UTC'));
        if ($now < $lockedUntil) {
            $remaining = $lockedUntil->getTimestamp() - $now->getTimestamp();
            $minutes = ceil($remaining / 60);
            return [
                'allowed' => false,
                'message' => "Too many failed attempts. Account locked for {$minutes} more minute(s)."
            ];
        } else {
            // Lock expired - reset attempts
            $resetStmt = $pdo->prepare(
                "UPDATE rate_limit SET attempts = 0, locked_until = NULL WHERE identifier = ? AND type = ?"
            );
            $resetStmt->execute([$identifier, $type]);
            return ['allowed' => true, 'message' => ''];
        }
    }

    // Check attempts within last 15 minutes
    $lastAttempt = new DateTime($record['last_attempt'], new DateTimeZone('UTC'));
    $fifteenMinutesAgo = (clone $now)->modify('-15 minutes');

    if ($lastAttempt < $fifteenMinutesAgo) {
        // Last attempt was more than 15 minutes ago - reset counter
        $resetStmt = $pdo->prepare(
            "UPDATE rate_limit SET attempts = 0 WHERE identifier = ? AND type = ?"
        );
        $resetStmt->execute([$identifier, $type]);
        return ['allowed' => true, 'message' => ''];
    }

    // Within rate limit window - check attempt count
    if ($record['attempts'] >= 5) {
        return [
            'allowed' => false,
            'message' => 'Too many failed attempts. Please try again in 15 minutes.'
        ];
    }

    return ['allowed' => true, 'message' => ''];
}

/**
 * Record a failed login attempt
 */
function opd_record_failed_attempt(string $identifier, string $type = 'login'): void
{
    opd_init_rate_limit_table();
    $pdo = opd_db();

    $stmt = $pdo->prepare(
        "SELECT * FROM rate_limit WHERE identifier = ? AND type = ? LIMIT 1"
    );
    $stmt->execute([$identifier, $type]);
    $record = $stmt->fetch();

    $now = gmdate('Y-m-d H:i:s');

    if (!$record) {
        // Create new record
        $id = 'rl-' . bin2hex(random_bytes(16));
        $insertStmt = $pdo->prepare(
            "INSERT INTO rate_limit (id, identifier, type, attempts, last_attempt) VALUES (?, ?, ?, 1, ?)"
        );
        $insertStmt->execute([$id, $identifier, $type, $now]);
    } else {
        // Increment attempts
        $newAttempts = $record['attempts'] + 1;
        $lockedUntil = null;

        // Lock account after 5 failed attempts
        if ($newAttempts >= 5) {
            $lockedUntil = gmdate('Y-m-d H:i:s', strtotime('+15 minutes'));
        }

        $updateStmt = $pdo->prepare(
            "UPDATE rate_limit SET attempts = ?, last_attempt = ?, locked_until = ? WHERE identifier = ? AND type = ?"
        );
        $updateStmt->execute([$newAttempts, $now, $lockedUntil, $identifier, $type]);
    }
}

/**
 * Reset rate limit for identifier (called on successful login)
 */
function opd_reset_rate_limit(string $identifier, string $type = 'login'): void
{
    opd_init_rate_limit_table();
    $pdo = opd_db();

    $stmt = $pdo->prepare(
        "DELETE FROM rate_limit WHERE identifier = ? AND type = ?"
    );
    $stmt->execute([$identifier, $type]);
}
