<?php

declare(strict_types=1);

namespace Marko\Blog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('post_tags')]
#[Index('idx_post_tags_unique', ['post_id', 'tag_id'], unique: true)]
class PostTag extends Entity implements PostTagInterface
{
    #[Column('post_id', references: 'posts.id', onDelete: 'CASCADE')]
    public int $postId;

    #[Column('tag_id', references: 'tags.id', onDelete: 'CASCADE')]
    public int $tagId;

    public function getPostId(): int
    {
        return $this->postId;
    }

    public function getTagId(): int
    {
        return $this->tagId;
    }
}
