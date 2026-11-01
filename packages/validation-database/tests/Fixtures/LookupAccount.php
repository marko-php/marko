<?php

declare(strict_types=1);

namespace Marko\Validation\Database\Tests\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

/**
 * Its email is not a unique column on purpose: the tests store two emails that differ only in case.
 */
#[Table('lookup_accounts')]
class LookupAccount extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(name: 'email_address', length: 191)]
    public string $email;

    #[Column(name: 'tenant_ref')]
    public int $tenantId;
}
