<?php

declare(strict_types=1);

namespace Marko\OAuth\Enum;

enum GrantType: string
{
    case AuthorizationCode = 'authorization_code';
    case ClientCredentials = 'client_credentials';
    case RefreshToken = 'refresh_token';
}
