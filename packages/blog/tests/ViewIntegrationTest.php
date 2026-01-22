<?php

declare(strict_types=1);

use Marko\Blog\Controllers\PostController;
use Marko\Blog\Entity\Post;
use Marko\Blog\Repositories\PostRepository;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

it('has resources/views directory', function (): void {
    $viewsPath = dirname(__DIR__) . '/resources/views';

    expect(is_dir($viewsPath))->toBeTrue()
        ->and($viewsPath)->toBeDirectory();
});

it('PostController uses ViewInterface', function (): void {
    $reflection = new ReflectionClass(PostController::class);
    $constructor = $reflection->getConstructor();

    expect($constructor)->not->toBeNull();

    $parameters = $constructor->getParameters();
    $parameterTypes = array_map(
        fn (ReflectionParameter $p) => $p->getType()?->getName(),
        $parameters,
    );

    expect($parameterTypes)->toContain(ViewInterface::class);
});

it('has post/index.latte template file', function (): void {
    $templatePath = dirname(__DIR__) . '/resources/views/post/index.latte';

    expect($templatePath)->toBeFile();
});

it('renders post index template', function (): void {
    $posts = [createPost(1, 'First Post', 'first-post'), createPost(2, 'Second Post', 'second-post')];
    $repository = createMockRepository($posts);
    $capture = new stdClass();
    $view = createCapturingMockView($capture);

    $controller = new PostController($repository, $view);
    $response = $controller->index();

    expect($capture->template)->toBe('blog::post/index')
        ->and($capture->data)->toHaveKey('posts')
        ->and($capture->data['posts'])->toHaveCount(2);
});

it('has post/show.latte template file', function (): void {
    $templatePath = dirname(__DIR__) . '/resources/views/post/show.latte';

    expect($templatePath)->toBeFile();
});

it('renders post show template', function (): void {
    $post = createPost(1, 'My Post', 'my-post');
    $repository = createMockRepository([], $post);
    $capture = new stdClass();
    $view = createCapturingMockView($capture);

    $controller = new PostController($repository, $view);
    $response = $controller->show('my-post');

    expect($capture->template)->toBe('blog::post/show')
        ->and($capture->data)->toHaveKey('post')
        ->and($capture->data['post']->title)->toBe('My Post');
});

// Helper functions

function createPost(
    int $id,
    string $title,
    string $slug,
): Post {
    $post = new Post();
    $post->id = $id;
    $post->title = $title;
    $post->slug = $slug;
    $post->content = "Content for $title";

    return $post;
}

function createMockRepository(
    array $posts = [],
    ?Post $findBySlugResult = null,
): PostRepository {
    return new class ($posts, $findBySlugResult) extends PostRepository
    {
        public function __construct(
            private readonly array $posts,
            private readonly ?Post $findBySlugEntity,
        ) {}

        public function findAll(): array
        {
            return $this->posts;
        }

        public function findBySlug(
            string $slug,
        ): ?Post {
            return $this->findBySlugEntity;
        }
    };
}

function createCapturingMockView(
    stdClass $capture,
): ViewInterface {
    return new class ($capture) implements ViewInterface
    {
        public function __construct(
            private stdClass $capture,
        ) {}

        public function render(
            string $template,
            array $data = [],
        ): Response {
            $this->capture->template = $template;
            $this->capture->data = $data;

            return new Response('rendered');
        }

        public function renderToString(
            string $template,
            array $data = [],
        ): string {
            $this->capture->template = $template;
            $this->capture->data = $data;

            return 'rendered';
        }
    };
}
