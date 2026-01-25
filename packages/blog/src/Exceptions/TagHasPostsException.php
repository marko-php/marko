<?php

declare(strict_types=1);

namespace Marko\Blog\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class TagHasPostsException extends MarkoException
{
    public static function cannotDelete(
        string $tagName,
        int $postCount,
    ): self {
        $postWord = $postCount === 1 ? 'post' : 'posts';

        return new self(
            message: "Cannot delete tag '$tagName' because it has $postCount associated $postWord",
            context: "Attempting to delete tag '$tagName'",
            suggestion: 'Remove the tag from all posts before deleting it',
        );
    }
}
