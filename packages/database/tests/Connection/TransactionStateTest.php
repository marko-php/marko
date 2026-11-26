<?php

declare(strict_types=1);

use Marko\Database\Connection\TransactionState;

describe('TransactionState', function (): void {
    it('starts at level zero', function (): void {
        expect((new TransactionState())->level())->toBe(0);
    });

    it('increments the level on begin and decrements on commit and rollback', function (): void {
        $state = new TransactionState();

        $state->begin();
        $state->begin();
        $afterTwoBegins = $state->level();
        $state->commit();
        $afterCommit = $state->level();
        $state->rollback();

        expect($afterTwoBegins)->toBe(2)
            ->and($afterCommit)->toBe(1)
            ->and($state->level())->toBe(0);
    });

    it('runs an after-commit callback immediately at level zero', function (): void {
        $state = new TransactionState();
        $ran = false;

        $state->afterCommit(function () use (&$ran): void {
            $ran = true;
        });

        expect($ran)->toBeTrue();
    });

    it('runs after-commit callbacks only after the outermost commit', function (): void {
        $state = new TransactionState();
        $log = [];

        $state->begin();
        $state->afterCommit(function () use (&$log, $state): void {
            $log[] = 'outer@' . $state->level();
        });
        $state->begin();
        $state->afterCommit(function () use (&$log, $state): void {
            $log[] = 'inner@' . $state->level();
        });
        $state->commit();
        $afterInnerCommit = $log;
        $state->commit();

        expect($afterInnerCommit)->toBe([])
            ->and($log)->toBe(['outer@0', 'inner@0']);
    });

    it('merges inner after-commit callbacks into the parent level on inner commit', function (): void {
        $state = new TransactionState();
        $log = [];

        $state->begin();
        $state->begin();
        $state->afterCommit(function () use (&$log): void {
            $log[] = 'inner';
        });
        $state->commit();
        $state->rollback();

        expect($log)->toBe([]);
    });

    it('discards after-commit callbacks of a rolled-back level', function (): void {
        $state = new TransactionState();
        $log = [];

        $state->begin();
        $state->afterCommit(function () use (&$log): void {
            $log[] = 'outer';
        });
        $state->begin();
        $state->afterCommit(function () use (&$log): void {
            $log[] = 'inner';
        });
        $state->rollback();
        $state->commit();

        expect($log)->toBe(['outer']);
    });

    it('runs after-rollback callbacks of a rolled-back level', function (): void {
        $state = new TransactionState();
        $log = [];

        $state->begin();
        $state->afterRollback(function () use (&$log): void {
            $log[] = 'outer';
        });
        $state->begin();
        $state->afterRollback(function () use (&$log, $state): void {
            $log[] = 'inner@' . $state->level();
        });
        $state->rollback();
        $afterInnerRollback = $log;
        $state->commit();

        expect($afterInnerRollback)->toBe(['inner@1'])
            ->and($log)->toBe(['inner@1']);
    });

    it(
        'runs after-rollback callbacks merged from a committed inner level when the outer level rolls back',
        function (): void {
            $state = new TransactionState();
            $log = [];

            $state->begin();
            $state->begin();
            $state->afterRollback(function () use (&$log): void {
                $log[] = 'inner';
            });
            $state->commit();
            $state->rollback();

            expect($log)->toBe(['inner']);
        },
    );

    it('ignores after-rollback callbacks registered at level zero', function (): void {
        $state = new TransactionState();
        $ran = false;

        $state->afterRollback(function () use (&$ran): void {
            $ran = true;
        });
        $state->begin();
        $state->rollback();

        expect($ran)->toBeFalse();
    });

    it('propagates an exception thrown by a callback', function (): void {
        $state = new TransactionState();
        $state->begin();
        $state->afterCommit(function (): void {
            throw new RuntimeException('Mail server down');
        });

        expect(fn () => $state->commit())->toThrow(RuntimeException::class, 'Mail server down')
            ->and($state->level())->toBe(0);
    });

    it('clears every level without running callbacks', function (): void {
        $state = new TransactionState();
        $ran = false;

        $state->begin();
        $state->afterRollback(function () use (&$ran): void {
            $ran = true;
        });
        $state->begin();
        $state->clear();

        expect($state->level())->toBe(0)
            ->and($ran)->toBeFalse();
    });

    it('drops the current level without running its callbacks on discard', function (): void {
        $state = new TransactionState();
        $ran = false;

        $state->begin();
        $state->begin();
        $state->afterRollback(function () use (&$ran): void {
            $ran = true;
        });
        $state->discard();

        expect($state->level())->toBe(1)
            ->and($ran)->toBeFalse();
    });

    it(
        'runs the after-commit callbacks of every open level in registration order without closing a level',
        function (): void {
            $state = new TransactionState();
            $log = [];

            $state->begin();
            $state->afterCommit(function () use (&$log): void {
                $log[] = 'outer';
            });
            $state->begin();
            $state->afterCommit(function () use (&$log): void {
                $log[] = 'inner';
            });
            $state->runAfterCommitCallbacks();

            expect($log)->toBe(['outer', 'inner'])
                ->and($state->level())->toBe(2);
        },
    );

    it('removes the callbacks it ran so a later commit does not run them again', function (): void {
        $state = new TransactionState();
        $runs = 0;

        $state->begin();
        $state->afterCommit(function () use (&$runs): void {
            $runs++;
        });
        $state->runAfterCommitCallbacks();
        $state->runAfterCommitCallbacks();
        $state->commit();

        expect($runs)->toBe(1);
    });

    it('does not run a callback twice when an earlier callback throws', function (): void {
        $state = new TransactionState();
        $log = [];

        $state->begin();
        $state->afterCommit(function () use (&$log): void {
            $log[] = 'first';

            throw new RuntimeException('callback failed');
        });
        $state->afterCommit(function () use (&$log): void {
            $log[] = 'second';
        });

        try {
            $state->runAfterCommitCallbacks();
        } catch (RuntimeException) {
            // Expected: the exception propagates.
        }

        $state->runAfterCommitCallbacks();

        expect($log)->toBe(['first', 'second']);
    });

    it('queues callbacks registered while running instead of running them in the same call', function (): void {
        $state = new TransactionState();
        $log = [];

        $state->begin();
        $state->afterCommit(function () use ($state, &$log): void {
            $log[] = 'first';
            $state->afterCommit(function () use (&$log): void {
                $log[] = 'registered while running';
            });
        });
        $state->runAfterCommitCallbacks();
        $afterFirstRun = $log;
        $state->runAfterCommitCallbacks();

        expect($afterFirstRun)->toBe(['first'])
            ->and($log)->toBe(['first', 'registered while running']);
    });

    it('does nothing when no transaction is open', function (): void {
        $state = new TransactionState();

        $state->runAfterCommitCallbacks();

        expect($state->level())->toBe(0);
    });

    it('keeps after-rollback callbacks registered', function (): void {
        $state = new TransactionState();
        $ran = false;

        $state->begin();
        $state->afterRollback(function () use (&$ran): void {
            $ran = true;
        });
        $state->runAfterCommitCallbacks();
        $state->rollback();

        expect($ran)->toBeTrue();
    });

    it('marks an open transaction as lost', function (): void {
        $state = new TransactionState();

        $state->begin();
        $state->markLost();

        expect($state->isLost())->toBeTrue()
            ->and($state->level())->toBe(1);
    });

    it('ignores markLost when no transaction is open', function (): void {
        $state = new TransactionState();

        $state->markLost();

        expect($state->isLost())->toBeFalse();
    });

    it('keeps the lost marker while outer levels remain open', function (): void {
        $state = new TransactionState();

        $state->begin();
        $state->begin();
        $state->begin();
        $state->markLost();
        $state->rollback();
        $afterRollback = $state->isLost();
        $state->discard();
        $afterDiscard = $state->isLost();
        $state->commit();

        expect($afterRollback)->toBeTrue()
            ->and($afterDiscard)->toBeTrue()
            ->and($state->level())->toBe(0);
    });

    it('clears the lost marker when the last level is rolled back, discarded or cleared', function (): void {
        $state = new TransactionState();
        $cleared = [];

        $state->begin();
        $state->markLost();
        $state->rollback();
        $cleared[] = $state->isLost();

        $state->begin();
        $state->markLost();
        $state->discard();
        $cleared[] = $state->isLost();

        $state->begin();
        $state->begin();
        $state->markLost();
        $state->clear();
        $cleared[] = $state->isLost();

        $state->begin();

        expect($cleared)->toBe([false, false, false])
            ->and($state->isLost())->toBeFalse();
    });
});
