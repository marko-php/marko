<?php

declare(strict_types=1);

namespace Marko\OAuth\Repository;

use Marko\Database\Repository\Repository;
use Marko\OAuth\Entity\OAuthApproval;

/**
 * @extends Repository<OAuthApproval>
 */
class OAuthApprovalRepository extends Repository implements OAuthApprovalRepositoryInterface
{
    protected const string ENTITY_CLASS = OAuthApproval::class;
}
