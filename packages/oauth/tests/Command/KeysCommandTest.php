<?php

declare(strict_types=1);

use Marko\OAuth\Exceptions\OAuthException;
use Marko\OAuth\Service\KeyGenerator;

it('generates oauth key files', function (): void {
    $directory = sys_get_temp_dir() . '/marko-oauth-' . bin2hex(random_bytes(8));
    $private = $directory . '/private.key';
    $public = $directory . '/public.key';

    (new KeyGenerator())->generate($private, $public);

    expect(file_exists($private))->toBeTrue()
        ->and(file_exists($public))->toBeTrue()
        ->and(file_get_contents($private))->toContain('PRIVATE KEY')
        ->and(file_get_contents($public))->toContain('PUBLIC KEY');

    unlink($private);
    unlink($public);
    rmdir($directory);
});

it('refuses to overwrite keys without force', function (): void {
    $directory = sys_get_temp_dir() . '/marko-oauth-' . bin2hex(random_bytes(8));
    mkdir($directory, 0700, true);
    $private = $directory . '/private.key';
    $public = $directory . '/public.key';
    file_put_contents($private, 'existing');
    file_put_contents($public, 'existing');

    expect(fn () => (new KeyGenerator())->generate($private, $public))
        ->toThrow(OAuthException::class, 'OAuth key file already exists.');

    unlink($private);
    unlink($public);
    rmdir($directory);
});
