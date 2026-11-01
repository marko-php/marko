<?php

declare(strict_types=1);

use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\FlashDataInterface;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Exceptions\InvalidFlashDataException;
use Marko\Session\Flash\SessionFlashData;
use Marko\Session\Session;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * Drives a real Session across requests: each request() call resumes the
 * session from the previous request's id, runs the callback, then saves.
 */
class FlashDataRequestRunner
{
    public Session $session;

    private string $id = '';

    public function __construct()
    {
        $handler = new class () implements SessionHandlerInterface
        {
            /** @var array<string, string> */
            public array $written = [];

            public function open(
                string $path,
                string $name,
            ): bool {
                return true;
            }

            public function close(): bool
            {
                return true;
            }

            public function read(string $id): string|false
            {
                return $this->written[$id] ?? '';
            }

            public function write(
                string $id,
                string $data,
            ): bool {
                $this->written[$id] = $data;

                return true;
            }

            public function destroy(string $id): bool
            {
                unset($this->written[$id]);

                return true;
            }

            public function gc(int $max_lifetime): int|false
            {
                return 0;
            }

            public function validateId(string $id): bool
            {
                return isset($this->written[$id]);
            }

            public function updateTimestamp(
                string $id,
                string $data,
            ): bool {
                return isset($this->written[$id]);
            }
        };

        $this->session = new Session($handler, new SessionConfig(new FakeConfigRepository([
            'session.lifetime' => 120,
            'session.cookie.name' => 'PHPSESSID',
            'session.gc_probability' => 1,
            'session.gc_divisor' => 100,
        ])));
    }

    public function request(
        Closure $callback,
        bool $lazy = false,
    ): mixed {
        $this->session->reset();

        if ($this->id !== '') {
            $this->session->setId($this->id);
        }

        $lazy ? $this->session->arm() : $this->session->start();

        try {
            return $callback(new SessionFlashData($this->session), $this->session);
        } finally {
            $this->id = $this->session->getId();
            $this->session->save();
        }
    }
}

describe('SessionFlashData', function (): void {
    it('implements FlashDataInterface', function (): void {
        $runner = new FlashDataRequestRunner();

        $runner->request(function (SessionFlashData $flash): void {
            expect($flash)->toBeInstanceOf(FlashDataInterface::class);
        });
    });

    it('makes a value visible on the next request', function (): void {
        $runner = new FlashDataRequestRunner();

        $runner->request(fn (SessionFlashData $flash) => $flash->put('notice', 'Saved'));

        $runner->request(function (SessionFlashData $flash): void {
            expect($flash->has('notice'))->toBeTrue()
                ->and($flash->get('notice'))->toBe('Saved');
        });
    });

    it('does not show a value on the request that put it', function (): void {
        $runner = new FlashDataRequestRunner();

        $runner->request(function (SessionFlashData $flash): void {
            $flash->put('notice', 'Saved');

            expect($flash->has('notice'))->toBeFalse()
                ->and($flash->get('notice', 'none'))->toBe('none');
        });
    });

    it('reads a value any number of times on the next request', function (): void {
        $runner = new FlashDataRequestRunner();

        $runner->request(fn (SessionFlashData $flash) => $flash->put('input', ['email' => 'a@b.c']));

        $runner->request(function (SessionFlashData $flash): void {
            expect($flash->get('input'))->toBe(['email' => 'a@b.c'])
                ->and($flash->get('input'))->toBe(['email' => 'a@b.c']);
        });
    });

    it('drops a value on the request after the next one even when it was never read', function (): void {
        $runner = new FlashDataRequestRunner();

        $runner->request(fn (SessionFlashData $flash) => $flash->put('notice', 'Saved'));
        $runner->request(fn () => null);

        $runner->request(function (SessionFlashData $flash): void {
            expect($flash->has('notice'))->toBeFalse()
                ->and($flash->get('notice'))->toBeNull();
        });
    });

    it('drops a value on the request after the next one after it was read', function (): void {
        $runner = new FlashDataRequestRunner();

        $runner->request(fn (SessionFlashData $flash) => $flash->put('notice', 'Saved'));
        $runner->request(fn (SessionFlashData $flash) => $flash->get('notice'));

        $runner->request(function (SessionFlashData $flash): void {
            expect($flash->has('notice'))->toBeFalse();
        });
    });

    it('ages flash data when an armed session starts on first access', function (): void {
        $runner = new FlashDataRequestRunner();

        $runner->request(fn (SessionFlashData $flash) => $flash->put('notice', 'Saved'));

        $runner->request(function (SessionFlashData $flash): void {
            expect($flash->get('notice'))->toBe('Saved');
        }, lazy: true);

        $runner->request(function (SessionFlashData $flash): void {
            expect($flash->has('notice'))->toBeFalse();
        }, lazy: true);
    });

    it('extends a value for one more request with keep()', function (): void {
        $runner = new FlashDataRequestRunner();

        $runner->request(function (SessionFlashData $flash): void {
            $flash->put('notice', 'Saved');
            $flash->put('other', 'Gone');
        });
        $runner->request(fn (SessionFlashData $flash) => $flash->keep('notice'));

        $runner->request(function (SessionFlashData $flash): void {
            expect($flash->get('notice'))->toBe('Saved')
                ->and($flash->has('other'))->toBeFalse();
        });

        $runner->request(function (SessionFlashData $flash): void {
            expect($flash->has('notice'))->toBeFalse();
        });
    });

    it('keeps every current value when keep() is called without keys', function (): void {
        $runner = new FlashDataRequestRunner();

        $runner->request(function (SessionFlashData $flash): void {
            $flash->put('notice', 'Saved');
            $flash->put('other', 'Kept');
        });
        $runner->request(fn (SessionFlashData $flash) => $flash->keep());

        $runner->request(function (SessionFlashData $flash): void {
            expect($flash->get('notice'))->toBe('Saved')
                ->and($flash->get('other'))->toBe('Kept');
        });
    });

    it('ignores keys passed to keep() that were not flashed', function (): void {
        $runner = new FlashDataRequestRunner();

        $runner->request(fn (SessionFlashData $flash) => $flash->keep('missing'));

        $runner->request(function (SessionFlashData $flash): void {
            expect($flash->has('missing'))->toBeFalse();
        });
    });

    it('tells a flashed null apart from a missing key', function (): void {
        $runner = new FlashDataRequestRunner();

        $runner->request(fn (SessionFlashData $flash) => $flash->put('nothing', null));

        $runner->request(function (SessionFlashData $flash): void {
            expect($flash->has('nothing'))->toBeTrue()
                ->and($flash->get('nothing', 'default'))->toBeNull();
        });
    });

    it('leaves the session untouched when nothing was flashed', function (): void {
        $runner = new FlashDataRequestRunner();

        $runner->request(fn (SessionFlashData $flash, Session $session) => $session->set('user', 1));

        $runner->request(function (SessionFlashData $flash, Session $session): void {
            expect($session->isModified())->toBeFalse()
                ->and($session->has(SessionFlashData::SESSION_KEY))->toBeFalse();
        });
    });

    it('removes the reserved key once the flashed values have expired', function (): void {
        $runner = new FlashDataRequestRunner();

        $runner->request(fn (SessionFlashData $flash) => $flash->put('notice', 'Saved'));
        $runner->request(fn () => null);

        $runner->request(function (SessionFlashData $flash, Session $session): void {
            expect($session->has(SessionFlashData::SESSION_KEY))->toBeFalse();
        });
    });

    it('accepts scalars, null and nested arrays of them', function (): void {
        $runner = new FlashDataRequestRunner();
        $value = ['name' => 'Ann', 'age' => 30, 'ratio' => 1.5, 'admin' => false, 'tags' => ['a', null]];

        $runner->request(fn (SessionFlashData $flash) => $flash->put('input', $value));

        $runner->request(function (SessionFlashData $flash) use ($value): void {
            expect($flash->get('input'))->toBe($value);
        });
    });

    it('throws when a closure is flashed', function (): void {
        $runner = new FlashDataRequestRunner();

        $runner->request(fn (SessionFlashData $flash) => $flash->put('callback', fn (): int => 1));
    })->throws(InvalidFlashDataException::class, "Cannot flash 'callback': Closure is not serialisable flash data");

    it('throws when an object is flashed', function (): void {
        $runner = new FlashDataRequestRunner();

        $runner->request(fn (SessionFlashData $flash) => $flash->put('when', new DateTimeImmutable()));
    })->throws(
        InvalidFlashDataException::class,
        "Cannot flash 'when': DateTimeImmutable is not serialisable flash data",
    );

    it('names the nested path of an object inside a flashed array', function (): void {
        $runner = new FlashDataRequestRunner();

        $runner->request(
            fn (SessionFlashData $flash) => $flash->put('input', ['user' => ['avatar' => new stdClass()]]),
        );
    })->throws(
        InvalidFlashDataException::class,
        "Cannot flash 'input.user.avatar': stdClass is not serialisable flash data",
    );
});
