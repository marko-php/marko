<?php

declare(strict_types=1);

namespace Marko\Validation\Database\Tests\Fixtures;

use Marko\Validation\Database\Exceptions\RecordLookupException;

/**
 * The cases the PgSql and MySql integration tests run against their own server. Each file's beforeEach sets
 * $this->rules to RecordRules from LookupTables::recordRules() over the seeded tables.
 */
class RecordLookupCases
{
    /**
     * Register the cases inside the calling test file.
     *
     * @param bool $caseSensitiveByDefault Whether the server's default collation compares text exactly
     *                                     (PostgreSQL) rather than case-insensitively (MySQL/MariaDB)
     */
    public static function register(
        bool $caseSensitiveByDefault,
    ): void {
        it('passes exists for an existing id and fails for a missing one', function (): void {
            $rule = $this->rules->exists(LookupChannel::class);

            expect($rule->passes('channel_id', 1, []))->toBeTrue()
                ->and($rule->passes('channel_id', '2', []))->toBeTrue()
                ->and($rule->passes('channel_id', 99, []))->toBeFalse()
                ->and($rule->passes('channel_id', 'abc', []))->toBeFalse()
                ->and($rule->passes('channel_id', '1abc', []))->toBeFalse();
        });

        it('honours where() on exists, including a bool and an enum', function (): void {
            $active = $this->rules->exists(LookupChannel::class)->where('archived', false);
            $paused = $this->rules->exists(LookupChannel::class, 'slug')->where('status', ChannelStatus::Paused);
            $noNotes = $this->rules->exists(LookupChannel::class)->where('notes', null);

            expect($active->passes('channel_id', 1, []))->toBeTrue()
                ->and($active->passes('channel_id', 2, []))->toBeFalse()
                ->and($paused->passes('channel', 'old', []))->toBeTrue()
                ->and($paused->passes('channel', 'news', []))->toBeFalse()
                ->and($noNotes->passes('channel_id', 1, []))->toBeTrue();
        });

        it('maps a property to a column with a different name', function (): void {
            $rule = $this->rules->exists(LookupChannel::class, 'title');

            expect($rule->passes('channel', 'News', []))->toBeTrue()
                ->and($rule->passes('channel', 'Sport', []))->toBeFalse()
                ->and($this->rules->unique(LookupAccount::class, 'email')->where('tenantId', 2)
                    ->passes('email', 'grace@example.com', []))->toBeFalse();
        });

        it('fails unique for a taken value and passes for a free one', function (): void {
            $rule = $this->rules->unique(LookupAccount::class, 'email');

            expect($rule->passes('email', 'grace@example.com', []))->toBeFalse()
                ->and($rule->passes('email', 'new@example.com', []))->toBeTrue();
        });

        it('passes unique for the ignored record itself', function (): void {
            $rule = $this->rules->unique(LookupAccount::class, 'email');

            expect($rule->ignore(2)->passes('email', 'grace@example.com', []))->toBeTrue()
                ->and($rule->ignore(1)->passes('email', 'grace@example.com', []))->toBeFalse()
                ->and($rule->ignore('2')->passes('email', 'grace@example.com', []))->toBeTrue();
        });

        it('honours where() on unique', function (): void {
            $rule = $this->rules->unique(LookupAccount::class, 'email');

            expect($rule->where('tenantId', 1)->passes('email', 'grace@example.com', []))->toBeTrue()
                ->and($rule->where('tenantId', 2)->passes('email', 'grace@example.com', []))->toBeFalse();
        });

        it(
            'compares case-insensitively with caseInsensitive() and by collation without it',
            function () use ($caseSensitiveByDefault): void {
                $rule = $this->rules->unique(LookupAccount::class, 'email');

                expect($rule->caseInsensitive()->passes('email', 'ada@EXAMPLE.com', []))->toBeFalse()
                    ->and($rule->caseInsensitive()->passes('email', 'GRACE@example.com', []))->toBeFalse()
                    ->and($rule->caseInsensitive()->ignore(1)->passes('email', 'ADA@example.com', []))->toBeTrue()
                    ->and($rule->passes('email', 'ada@example.com', []))->toBe($caseSensitiveByDefault);
            },
        );

        it('throws a helpful error for an unknown entity or property', function (): void {
            expect(fn () => $this->rules->exists('App\Entity\Missing')->passes('id', 1, []))
                ->toThrow(RecordLookupException::class, 'no such class')
                ->and(fn () => $this->rules->unique(LookupAccount::class, 'email_address')->passes('email', 'a', []))
                ->toThrow(RecordLookupException::class, 'has no column property \'email_address\'');
        });
    }
}
