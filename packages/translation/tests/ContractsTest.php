<?php

declare(strict_types=1);

use Marko\Translation\Contracts\TranslationLoaderInterface;
use Marko\Translation\Contracts\TranslatorInterface;

it('defines TranslatorInterface with get, choice, setLocale, and getLocale methods', function () {
    $reflection = new ReflectionClass(TranslatorInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('get'))->toBeTrue()
        ->and($reflection->hasMethod('choice'))->toBeTrue()
        ->and($reflection->hasMethod('setLocale'))->toBeTrue()
        ->and($reflection->hasMethod('getLocale'))->toBeTrue();

    $get = $reflection->getMethod('get');
    $getParams = $get->getParameters();
    expect($getParams)->toHaveCount(3)
        ->and($getParams[0]->getName())->toBe('key')
        ->and($getParams[0]->getType()->getName())->toBe('string')
        ->and($getParams[1]->getName())->toBe('replacements')
        ->and($getParams[1]->getType()->getName())->toBe('array')
        ->and($getParams[1]->isDefaultValueAvailable())->toBeTrue()
        ->and($getParams[1]->getDefaultValue())->toBe([])
        ->and($getParams[2]->getName())->toBe('locale')
        ->and($getParams[2]->getType()->allowsNull())->toBeTrue()
        ->and($get->getReturnType()->getName())->toBe('string');

    $choice = $reflection->getMethod('choice');
    $choiceParams = $choice->getParameters();
    expect($choiceParams)->toHaveCount(4)
        ->and($choiceParams[0]->getName())->toBe('key')
        ->and($choiceParams[0]->getType()->getName())->toBe('string')
        ->and($choiceParams[1]->getName())->toBe('count')
        ->and($choiceParams[1]->getType()->getName())->toBe('int')
        ->and($choiceParams[2]->getName())->toBe('replacements')
        ->and($choiceParams[2]->getType()->getName())->toBe('array')
        ->and($choiceParams[2]->isDefaultValueAvailable())->toBeTrue()
        ->and($choiceParams[2]->getDefaultValue())->toBe([])
        ->and($choiceParams[3]->getName())->toBe('locale')
        ->and($choiceParams[3]->getType()->allowsNull())->toBeTrue()
        ->and($choice->getReturnType()->getName())->toBe('string');

    $setLocale = $reflection->getMethod('setLocale');
    $setLocaleParams = $setLocale->getParameters();
    expect($setLocaleParams)->toHaveCount(1)
        ->and($setLocaleParams[0]->getName())->toBe('locale')
        ->and($setLocaleParams[0]->getType()->getName())->toBe('string')
        ->and($setLocale->getReturnType()->getName())->toBe('void');

    $getLocale = $reflection->getMethod('getLocale');
    expect($getLocale->getParameters())->toHaveCount(0)
        ->and($getLocale->getReturnType()->getName())->toBe('string');
});

it('defines TranslationLoaderInterface with load method accepting locale, group, and optional namespace', function () {
    $reflection = new ReflectionClass(TranslationLoaderInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('load'))->toBeTrue();

    $load = $reflection->getMethod('load');
    $loadParams = $load->getParameters();
    expect($loadParams)->toHaveCount(3)
        ->and($loadParams[0]->getName())->toBe('locale')
        ->and($loadParams[0]->getType()->getName())->toBe('string')
        ->and($loadParams[1]->getName())->toBe('group')
        ->and($loadParams[1]->getType()->getName())->toBe('string')
        ->and($loadParams[2]->getName())->toBe('namespace')
        ->and($loadParams[2]->getType()->allowsNull())->toBeTrue()
        ->and($loadParams[2]->isDefaultValueAvailable())->toBeTrue()
        ->and($loadParams[2]->getDefaultValue())->toBeNull()
        ->and($load->getReturnType()->getName())->toBe('array');
});
