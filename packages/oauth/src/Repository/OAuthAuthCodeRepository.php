<?php

declare(strict_types=1);

namespace Marko\OAuth\Repository;

use Marko\Database\Repository\Repository;
use Marko\OAuth\Entity\OAuthAuthCode;

/**
 * @extends Repository<OAuthAuthCode>
 */
class OAuthAuthCodeRepository extends Repository implements OAuthAuthCodeRepositoryInterface
{
    protected const string ENTITY_CLASS = OAuthAuthCode::class;
}
