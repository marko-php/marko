<?php

declare(strict_types=1);

namespace Marko\Blog\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class AuthorHasPostsException extends MarkoException
{
    public static function cannotDelete(
        string $authorName,
        int $postCount,
    ): self {
        $postWord = $postCount === 1 ? 'post' : 'posts';

        return new self(
            message: "Cannot delete author '$authorName' because they have $postCount associated $postWord",
            context: "Attempting to delete author '$authorName'",
            suggestion: 'Reassign or delete all posts by this author before deleting the author',
        );
    }
}
