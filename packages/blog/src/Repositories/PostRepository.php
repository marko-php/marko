<?php

declare(strict_types=1);

namespace Marko\Blog\Repositories;

use Marko\Blog\Entity\Post;
use Marko\Database\Repository\Repository;

class PostRepository extends Repository
{
    protected const string ENTITY_CLASS = Post::class;

    /**
     * Find a post by its slug.
     */
    public function findBySlug(
        string $slug,
    ): ?Post {
        return $this->findOneBy(['slug' => $slug]);
    }
}
