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
            CREATE TABLE comments (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                post_id INT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                parent_id INT UNSIGNED NULL,
                verified_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                PRIMARY KEY (id),
                INDEX idx_comments_post_id (post_id),
                INDEX idx_comments_status (status),
                INDEX idx_comments_parent_id (parent_id),
                INDEX idx_comments_email (email),
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
            )
            SQL);
    }

    public function down(
        ConnectionInterface $connection,
    ): void {
        $this->execute($connection, 'DROP TABLE comments');
    }
};
