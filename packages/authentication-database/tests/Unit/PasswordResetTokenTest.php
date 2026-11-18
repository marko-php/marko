<?php

declare(strict_types=1);

use Marko\Authentication\Database\Tests\Fixtures\PasswordResetTokenTable;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Index;

it('defines the password_reset_tokens table db:migrate creates', function (): void {
    $table = PasswordResetTokenTable::entityTable();
    $columns = [];

    foreach ($table->columns as $column) {
        /** @var Column $column */
        $columns[$column->name] = [$column->type, $column->length, $column->nullable];
    }

    expect($table->name)->toBe('password_reset_tokens')
        ->and(array_keys($columns))->toBe(['id', 'provider', 'user_id', 'token_hash', 'created_at'])
        ->and($columns['provider'])->toBe(['varchar', 100, false])
        ->and($columns['user_id'])->toBe(['varchar', 255, false])
        ->and($columns['token_hash'])->toBe(['varchar', 64, false])
        ->and($columns['created_at'][0])->toBe('datetime')
        ->and($columns['created_at'][2])->toBeFalse();
});

it('derives the password reset token index names', function (): void {
    $indexes = array_map(
        fn (Index $index): array => [$index->name, $index->columns, $index->type->name],
        PasswordResetTokenTable::entityTable()->indexes,
    );

    expect($indexes)
        ->toContain(['password_reset_tokens_provider_user_id_unique', ['provider', 'user_id'], 'Unique'])
        ->toContain(['password_reset_tokens_created_at_index', ['created_at'], 'Btree']);
});
