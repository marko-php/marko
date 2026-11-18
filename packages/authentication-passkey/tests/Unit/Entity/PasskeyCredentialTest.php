<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Tests\Unit\Entity;

use Marko\AuthenticationPasskey\Entity\PasskeyCredential;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;

it('derives the passkey credential owner index name', function (): void {
    $table = new SchemaBuilder()->build(new EntityMetadataFactory()->parse(PasskeyCredential::class));

    expect($table->indexes)->toHaveCount(1)
        ->and($table->indexes[0]->name)->toBe('passkey_credentials_provider_user_id_index')
        ->and($table->indexes[0]->columns)->toBe(['provider', 'user_id']);
});
