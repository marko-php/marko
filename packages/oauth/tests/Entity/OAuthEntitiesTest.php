<?php

declare(strict_types=1);

use Marko\Database\Attributes\Table;
use Marko\OAuth\Entity\OAuthAccessToken;
use Marko\OAuth\Entity\OAuthApproval;
use Marko\OAuth\Entity\OAuthAuthCode;
use Marko\OAuth\Entity\OAuthClient;
use Marko\OAuth\Entity\OAuthRefreshToken;
use Marko\OAuth\Entity\OAuthScope;

it('maps oauth entities to expected tables', function (string $class, string $table): void {
    $reflection = new ReflectionClass($class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe($table);
})->with([
    [OAuthClient::class, 'oauth_clients'],
    [OAuthAuthCode::class, 'oauth_auth_codes'],
    [OAuthAccessToken::class, 'oauth_access_tokens'],
    [OAuthRefreshToken::class, 'oauth_refresh_tokens'],
    [OAuthApproval::class, 'oauth_approvals'],
    [OAuthScope::class, 'oauth_scopes'],
]);

it('models confidential clients with hashed secrets', function (): void {
    $client = new OAuthClient();
    $client->id = 'client-id';
    $client->name = 'Example App';
    $client->secretHash = password_hash('secret', PASSWORD_BCRYPT);
    $client->confidential = true;

    expect($client->id)->toBe('client-id')
        ->and($client->confidential)->toBeTrue()
        ->and(password_verify('secret', (string) $client->secretHash))->toBeTrue();
});
