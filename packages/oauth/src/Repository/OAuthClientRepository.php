<?php

declare(strict_types=1);

namespace Marko\OAuth\Repository;

use Marko\Database\Repository\Repository;
use Marko\OAuth\Entity\OAuthClient;

/**
 * @extends Repository<OAuthClient>
 */
class OAuthClientRepository extends Repository implements OAuthClientRepositoryInterface
{
    protected const string ENTITY_CLASS = OAuthClient::class;
}
