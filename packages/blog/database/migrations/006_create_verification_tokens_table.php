<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;

return new class () extends Migration
{
    public function up(
        ConnectionInterface $connection,
    ): void {
        $this->execute($connection, <<<'SQL'
            CREATE TABLE verification_tokens (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                token VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                comment_id INT UNSIGNED NULL,
                created_at TIMESTAMP NULL,
                expires_at TIMESTAMP NULL,
                PRIMARY KEY (id),
                UNIQUE INDEX idx_verification_tokens_token (token),
                INDEX idx_verification_tokens_email (email),
                INDEX idx_verification_tokens_type (type),
                INDEX idx_verification_tokens_expires_at (expires_at),
                FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
            )
            SQL);
    }

    public function down(
        ConnectionInterface $connection,
    ): void {
        $this->execute($connection, 'DROP TABLE verification_tokens');
    }
};
