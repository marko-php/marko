<?php

declare(strict_types=1);

namespace Marko\Queue\Database\Migration;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;

class CreateFailedJobsTable extends Migration
{
    public function up(
        ConnectionInterface $connection,
    ): void {
        $this->execute($connection, <<<'SQL'
            CREATE TABLE failed_jobs (
                id VARCHAR(36) PRIMARY KEY,
                queue VARCHAR(255) NOT NULL,
                payload TEXT NOT NULL,
                exception TEXT NOT NULL,
                failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
    }

    public function down(
        ConnectionInterface $connection,
    ): void {
        $this->execute($connection, 'DROP TABLE IF EXISTS failed_jobs');
    }
}
