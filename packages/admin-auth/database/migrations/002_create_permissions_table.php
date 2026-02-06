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
            CREATE TABLE permissions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `key` VARCHAR(255) NOT NULL,
                label VARCHAR(255) NOT NULL,
                `group` VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NULL,
                PRIMARY KEY (id),
                UNIQUE INDEX idx_permissions_key (`key`),
                INDEX idx_permissions_group (`group`)
            )
            SQL);
    }

    public function down(
        ConnectionInterface $connection,
    ): void {
        $this->execute($connection, 'DROP TABLE permissions');
    }
};
