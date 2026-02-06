<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Unit\Admin\Api;

use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Admin\Api\CommentApiController;
use Marko\Blog\Entity\Comment;
use Marko\Blog\Enum\CommentStatus;
use Marko\Blog\Repositories\CommentRepositoryInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post as PostRoute;
use Marko\Routing\Http\Request;
use ReflectionClass;

it('creates CommentApiController with list, show, verify, delete actions', function (): void {
    $reflection = new ReflectionClass(CommentApiController::class);

    // Check class-level middleware
    $middlewareAttrs = $reflection->getAttributes(Middleware::class);
    expect($middlewareAttrs)->toHaveCount(1);
    $middleware = $middlewareAttrs[0]->newInstance();
    expect($middleware->middleware)->toContain(AdminAuthMiddleware::class);

    // Check index method (list)
    $index = $reflection->getMethod('index');
    $indexRoute = $index->getAttributes(Get::class);
    expect($indexRoute)->toHaveCount(1);
    expect($indexRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/comments');
    $indexPerm = $index->getAttributes(RequiresPermission::class);
    expect($indexPerm)->toHaveCount(1);
    expect($indexPerm[0]->newInstance()->permission)->toBe('blog.comments.view');

    // Check show method
    $show = $reflection->getMethod('show');
    $showRoute = $show->getAttributes(Get::class);
    expect($showRoute)->toHaveCount(1);
    expect($showRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/comments/{id}');

    // Check verify method
    $verify = $reflection->getMethod('verify');
    $verifyRoute = $verify->getAttributes(PostRoute::class);
    expect($verifyRoute)->toHaveCount(1);
    expect($verifyRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/comments/{id}/verify');
    $verifyPerm = $verify->getAttributes(RequiresPermission::class);
    expect($verifyPerm)->toHaveCount(1);
    expect($verifyPerm[0]->newInstance()->permission)->toBe('blog.comments.edit');

    // Check destroy method
    $destroy = $reflection->getMethod('destroy');
    $destroyRoute = $destroy->getAttributes(Delete::class);
    expect($destroyRoute)->toHaveCount(1);
    expect($destroyRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/comments/{id}');
    $destroyPerm = $destroy->getAttributes(RequiresPermission::class);
    expect($destroyPerm)->toHaveCount(1);
    expect($destroyPerm[0]->newInstance()->permission)->toBe('blog.comments.delete');

    // Test list returns JSON
    $comments = [createApiTestComment(1, 'John', 'john@test.com', 'Great post!', 1)];
    $controller = createCommentApiController(comments: $comments);
    $request = new Request(query: ['page' => '1']);
    $response = $controller->index($request);

    expect($response->statusCode())->toBe(200)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);
    expect($body)->toHaveKey('data')
        ->and($body['data'])->toHaveCount(1)
        ->and($body['data'][0]['name'])->toBe('John')
        ->and($body['data'][0]['content'])->toBe('Great post!');

    // Test show returns single comment
    $comment = createApiTestComment(1, 'John', 'john@test.com', 'Great post!', 1);
    $controller2 = createCommentApiController(findComment: $comment);
    $showResponse = $controller2->show(1);

    expect($showResponse->statusCode())->toBe(200);
    $showBody = json_decode($showResponse->body(), true);
    expect($showBody['data']['name'])->toBe('John');

    // Test verify updates status
    $pendingComment = createApiTestComment(1, 'John', 'john@test.com', 'Great post!', 1);
    $savedEntities = [];
    $controller3 = createCommentApiController(findComment: $pendingComment, savedEntities: $savedEntities);
    $verifyResponse = $controller3->verify(1);

    expect($verifyResponse->statusCode())->toBe(200);
    $verifyBody = json_decode($verifyResponse->body(), true);
    expect($verifyBody['data']['status'])->toBe('verified')
        ->and($savedEntities)->toHaveCount(1);

    // Test destroy returns success
    $deletedEntities = [];
    $comment2 = createApiTestComment(1, 'John', 'john@test.com', 'Great post!', 1);
    $controller4 = createCommentApiController(findComment: $comment2, deletedEntities: $deletedEntities);
    $destroyResponse = $controller4->destroy(1);

    expect($destroyResponse->statusCode())->toBe(200);
    $destroyBody = json_decode($destroyResponse->body(), true);
    expect($destroyBody['data']['deleted'])->toBeTrue()
        ->and($deletedEntities)->toHaveCount(1);
});

// Helper functions

function createApiTestComment(
    int $id,
    string $name,
    string $email,
    string $content,
    int $postId,
    CommentStatus $status = CommentStatus::Pending,
): Comment {
    $comment = new Comment();
    $comment->id = $id;
    $comment->name = $name;
    $comment->email = $email;
    $comment->content = $content;
    $comment->postId = $postId;
    $comment->status = $status;
    $comment->createdAt = '2024-01-01 12:00:00';

    return $comment;
}

function createApiMockCommentRepo(
    array $findAllResult = [],
    ?Comment $findResult = null,
    array &$savedEntities = [],
    array &$deletedEntities = [],
): CommentRepositoryInterface {
    return new class (
        $findAllResult,
        $findResult,
        $savedEntities,
        $deletedEntities,
    ) implements CommentRepositoryInterface
    {
        public function __construct(
            private array $findAllResult,
            private ?Comment $findResult,
            private array &$savedEntities,
            private array &$deletedEntities,
        ) {}

        public function find(
            int $id,
        ): ?Comment {
            return $this->findResult;
        }

        public function findOrFail(
            int $id,
        ): Entity {
            if ($this->findResult === null) {
                throw RepositoryException::entityNotFound(Comment::class, $id);
            }

            return $this->findResult;
        }

        public function findAll(): array
        {
            return $this->findAllResult;
        }

        public function findBy(
            array $criteria,
        ): array {
            return [];
        }

        public function findOneBy(
            array $criteria,
        ): ?Entity {
            return null;
        }

        public function save(
            Entity $entity,
        ): void {
            $this->savedEntities[] = $entity;
        }

        public function delete(
            Entity $entity,
        ): void {
            $this->deletedEntities[] = $entity;
        }

        public function findVerifiedForPost(
            int $postId,
        ): array {
            return [];
        }

        public function findPendingForPost(
            int $postId,
        ): array {
            return [];
        }

        public function getThreadedCommentsForPost(
            int $postId,
        ): array {
            return [];
        }

        public function countForPost(
            int $postId,
        ): int {
            return 0;
        }

        public function countVerifiedForPost(
            int $postId,
        ): int {
            return 0;
        }

        public function findByEmail(
            string $email,
        ): array {
            return [];
        }

        public function calculateDepth(
            int $commentId,
        ): int {
            return 0;
        }
    };
}

function createCommentApiController(
    array $comments = [],
    ?Comment $findComment = null,
    array &$savedEntities = [],
    array &$deletedEntities = [],
): CommentApiController {
    return new CommentApiController(
        commentRepository: createApiMockCommentRepo(
            findAllResult: $comments,
            findResult: $findComment,
            savedEntities: $savedEntities,
            deletedEntities: $deletedEntities,
        ),
    );
}
