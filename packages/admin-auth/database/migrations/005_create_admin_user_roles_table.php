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
            CREATE TABLE admin_user_roles (
                user_id INT UNSIGNED NOT NULL,
                role_id INT UNSIGNED NOT NULL,
                UNIQUE INDEX idx_admin_user_roles_unique (user_id, role_id),
                INDEX idx_admin_user_roles_role_id (role_id),
                FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
            )
            SQL);
    }

    public function down(
        ConnectionInterface $connection,
    ): void {
        $this->execute($connection, 'DROP TABLE admin_user_roles');
    }
};
