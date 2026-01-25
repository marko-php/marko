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
            CREATE TABLE posts (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                summary TEXT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'draft',
                author_id INT UNSIGNED NOT NULL,
                scheduled_at TIMESTAMP NULL,
                published_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                PRIMARY KEY (id),
                UNIQUE INDEX idx_posts_slug (slug),
                INDEX idx_posts_status (status),
                INDEX idx_posts_author_id (author_id),
                INDEX idx_posts_published_at (published_at),
                FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE CASCADE
            )
            SQL);
    }

    public function down(
        ConnectionInterface $connection,
    ): void {
        $this->execute($connection, 'DROP TABLE posts');
    }
};
