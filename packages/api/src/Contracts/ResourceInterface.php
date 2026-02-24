<?php

declare(strict_types=1);

namespace Marko\Api\Contracts;

use Marko\Routing\Http\Response;

interface ResourceInterface
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Transform the resource into a JSON HTTP response.
     */
    public function toResponse(): Response;
}
