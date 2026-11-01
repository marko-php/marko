<?php

declare(strict_types=1);

namespace Marko\View;

/**
 * Supplies variables shared by every template render.
 *
 * Mark the implementing class with #[ViewData] so it is discovered. data() is
 * called on every render, so it may return per-request values.
 */
interface ViewDataProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function data(): array;
}
