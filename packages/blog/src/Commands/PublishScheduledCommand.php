<?php

declare(strict_types=1);

namespace Marko\Blog\Commands;

use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Events\Post\PostPublished;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Event\EventDispatcherInterface;

#[Command(name: 'blog:publish-scheduled', description: 'Publish scheduled posts that are due')]
readonly class PublishScheduledCommand implements CommandInterface
{
    public function __construct(
        private PostRepositoryInterface $postRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $posts = $this->postRepository->findScheduledPostsDue();
        $count = 0;
        $verbose = $input->hasOption('verbose');

        foreach ($posts as $post) {
            $previousStatus = $post->getStatus();
            $post->setStatus(PostStatus::Published);
            $this->postRepository->save($post);
            $this->eventDispatcher->dispatch(new PostPublished(
                post: $post,
                previousStatus: $previousStatus,
            ));
            $count++;

            if ($verbose) {
                $output->writeLine(sprintf('Published: %s', $post->getTitle()));
            }
        }

        $output->writeLine(sprintf('%d post(s) published.', $count));

        return 0;
    }
}
