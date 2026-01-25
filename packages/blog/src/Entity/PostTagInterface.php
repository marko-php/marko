<?php

declare(strict_types=1);

namespace Marko\Blog\Entity;

interface PostTagInterface
{
    public function getPostId(): int;

    public function getTagId(): int;
}
