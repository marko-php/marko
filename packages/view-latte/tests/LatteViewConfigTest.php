<?php

declare(strict_types=1);

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\View\Latte\LatteViewConfig;

it('LatteViewConfig::strictTypes() returns the configured bool value', function (): void {
    $config = new FakeConfigRepository(['view.strict_types' => true]);
    $latteViewConfig = new LatteViewConfig($config);

    $config2 = new FakeConfigRepository(['view.strict_types' => false]);
    $latteViewConfig2 = new LatteViewConfig($config2);

    expect($latteViewConfig->strictTypes())->toBeTrue()
        ->and($latteViewConfig2->strictTypes())->toBeFalse();
});

it(
    'LatteViewConfig::strictTypes() throws ConfigNotFoundException when view.strict_types is missing',
    function (): void {
        $config = new FakeConfigRepository([]);
        $latteViewConfig = new LatteViewConfig($config);

        $latteViewConfig->strictTypes();
    },
)->throws(ConfigNotFoundException::class);
