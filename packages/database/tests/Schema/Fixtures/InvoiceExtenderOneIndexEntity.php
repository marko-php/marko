<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table(extends: InvoiceEntity::class)]
#[Index(name: 'idx_invoices_ref', columns: ['reference'])]
class InvoiceExtenderOneIndexEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(length: 100)]
    public string $reference;
}
