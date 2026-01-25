<?php

declare(strict_types=1);

namespace Marko\Blog\Entity;

interface PostCategoryInterface
{
    public function getPostId(): int;

    public function getCategoryId(): int;
}
