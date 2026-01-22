<?php

declare(strict_types=1);

namespace Marko\Queue\Database\Migration;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;

class CreateJobsTable extends Migration
{
    public function up(
        ConnectionInterface $connection,
    ): void {
        $this->execute($connection, <<<'SQL'
            CREATE TABLE jobs (
                id VARCHAR(36) PRIMARY KEY,
                queue VARCHAR(255) NOT NULL DEFAULT 'default',
                payload TEXT NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                reserved_at TIMESTAMP NULL,
                available_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL);

        $this->execute($connection, <<<'SQL'
            CREATE INDEX idx_queue_available ON jobs (queue, available_at)
            SQL);
    }

    public function down(
        ConnectionInterface $connection,
    ): void {
        $this->execute($connection, 'DROP TABLE IF EXISTS jobs');
    }
}
