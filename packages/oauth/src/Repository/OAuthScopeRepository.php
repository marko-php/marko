<?php

declare(strict_types=1);

namespace Marko\OAuth\Repository;

use Marko\Database\Repository\Repository;
use Marko\OAuth\Entity\OAuthScope;

/**
 * @extends Repository<OAuthScope>
 */
class OAuthScopeRepository extends Repository implements OAuthScopeRepositoryInterface
{
    protected const string ENTITY_CLASS = OAuthScope::class;
}
