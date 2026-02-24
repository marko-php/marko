<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Webhook\Entity\WebhookAttempt;
use ReflectionClass;
use ReflectionProperty;

describe('WebhookAttempt', function (): void {
    it('defines WebhookAttempt entity with table and column attributes', function (): void {
        $ref = new ReflectionClass(WebhookAttempt::class);

        // Must extend Entity
        expect($ref->getParentClass()->getName())->toBe(Entity::class);

        // Must have #[Table(name: 'webhook_attempts')]
        $tableAttrs = $ref->getAttributes(Table::class);
        expect($tableAttrs)->toHaveCount(1)
            ->and($tableAttrs[0]->newInstance()->name)->toBe('webhook_attempts');

        // Check properties exist with #[Column] attributes
        $columnProps = array_filter(
            $ref->getProperties(),
            fn (ReflectionProperty $p) => !empty($p->getAttributes(Column::class)),
        );

        $columnNames = array_map(
            fn (ReflectionProperty $p) => $p->getName(),
            $columnProps,
        );

        expect($columnNames)->toContain('id')
            ->toContain('webhookUrl')
            ->toContain('event')
            ->toContain('statusCode')
            ->toContain('responseBody')
            ->toContain('errorMessage')
            ->toContain('attemptNumber')
            ->toContain('attemptedAt');

        // Verify id is primary key with autoIncrement
        $idProp = $ref->getProperty('id');
        $idColumnAttr = $idProp->getAttributes(Column::class)[0]->newInstance();
        expect($idColumnAttr->primaryKey)->toBeTrue()
            ->and($idColumnAttr->autoIncrement)->toBeTrue();

        // Verify nullable columns
        $attempt = new WebhookAttempt(
            webhookUrl: 'https://example.com/webhook',
            event: 'order.created',
            attemptNumber: 1,
        );

        expect($attempt->id)->toBeNull()
            ->and($attempt->statusCode)->toBeNull()
            ->and($attempt->responseBody)->toBeNull()
            ->and($attempt->errorMessage)->toBeNull()
            ->and($attempt->attemptedAt)->toBeNull()
            ->and($attempt->webhookUrl)->toBe('https://example.com/webhook')
            ->and($attempt->event)->toBe('order.created')
            ->and($attempt->attemptNumber)->toBe(1);
    });
});
