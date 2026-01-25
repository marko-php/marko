<?php

declare(strict_types=1);

namespace Marko\Blog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('post_categories')]
#[Index('post_category_unique', ['post_id', 'category_id'], unique: true)]
class PostCategory extends Entity implements PostCategoryInterface
{
    public function __construct(
        #[Column('post_id', references: 'posts.id', onDelete: 'CASCADE')]
        public int $postId,
        #[Column('category_id', references: 'categories.id', onDelete: 'CASCADE')]
        public int $categoryId,
    ) {}

    public function getPostId(): int
    {
        return $this->postId;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }
}
