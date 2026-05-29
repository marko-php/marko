<?php

declare(strict_types=1);

namespace Marko\Docs\Contract;

use Marko\Docs\Exceptions\DocsException;
use Marko\Docs\ValueObject\DocsNavEntry;
use Marko\Docs\ValueObject\DocsPage;
use Marko\Docs\ValueObject\DocsQuery;
use Marko\Docs\ValueObject\DocsResult;

interface DocsSearchInterface
{
    /**
     * @return list<DocsResult>
     *
     * @throws DocsException
     */
    public function search(
        DocsQuery $query,
    ): array;

    /**
     * @throws DocsException
     */
    public function getPage(
        string $id,
    ): DocsPage;

    /**
     * @return list<DocsNavEntry>
     *
     * @throws DocsException
     */
    public function listNav(): array;

    public function driverName(): string;
}
