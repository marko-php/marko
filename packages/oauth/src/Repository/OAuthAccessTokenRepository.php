<?php

declare(strict_types=1);

namespace Marko\OAuth\Repository;

use Marko\Database\Repository\Repository;
use Marko\OAuth\Entity\OAuthAccessToken;

/**
 * @extends Repository<OAuthAccessToken>
 */
class OAuthAccessTokenRepository extends Repository implements OAuthAccessTokenRepositoryInterface
{
    protected const string ENTITY_CLASS = OAuthAccessToken::class;
}
