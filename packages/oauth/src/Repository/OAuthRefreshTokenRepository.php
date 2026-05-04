<?php

declare(strict_types=1);

namespace Marko\OAuth\Repository;

use Marko\Database\Repository\Repository;
use Marko\OAuth\Entity\OAuthRefreshToken;

/**
 * @extends Repository<OAuthRefreshToken>
 */
class OAuthRefreshTokenRepository extends Repository implements OAuthRefreshTokenRepositoryInterface
{
    protected const string ENTITY_CLASS = OAuthRefreshToken::class;
}
