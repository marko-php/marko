<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Container\Container;
use Marko\View\Command\ViewCacheCommand;
use Marko\View\Exceptions\NoDriverException;
use Marko\View\Exceptions\TemplatePrecompileException;
use Marko\View\TemplatePrecompilerInterface;
use Marko\View\TemplateResolverInterface;

/**
 * @param list<string> $templates
 */
function viewCacheResolver(
    array $templates,
): TemplateResolverInterface {
    return new readonly class ($templates) implements TemplateResolverInterface
    {
        /**
         * @param list<string> $templates
         */
        public function __construct(
            private array $templates,
        ) {}

        public function resolve(string $template): string
        {
            return "/app/resources/views/$template.latte";
        }

        public function getSearchedPaths(string $template): array
        {
            return [];
        }

        public function all(): array
        {
            return $this->templates;
        }
    };
}

/**
 * A precompiler that records each template and fails the ones listed in $broken.
 *
 * @param list<string> $broken
 */
function fakeTemplatePrecompiler(
    array $broken = [],
): TemplatePrecompilerInterface {
    return new class ($broken) implements TemplatePrecompilerInterface
    {
        /** @var list<string> */
        public array $compiled = [];

        /**
         * @param list<string> $broken
         */
        public function __construct(
            private readonly array $broken,
        ) {}

        public function precompile(string $template): void
        {
            if (in_array($template, $this->broken, true)) {
                throw TemplatePrecompileException::forTemplate(
                    template: $template,
                    path: "/app/resources/views/$template.latte",
                    line: 3,
                    reason: 'Unexpected end',
                );
            }

            $this->compiled[] = $template;
        }
    };
}

/**
 * @return array{0: int, 1: string}
 */
function runViewCache(
    ViewCacheCommand $command,
): array {
    $stream = fopen('php://memory', 'w+');
    $exitCode = $command->execute(new Input(['marko', 'view:cache']), new Output($stream));
    rewind($stream);

    return [$exitCode, stream_get_contents($stream)];
}

describe('ViewCacheCommand', function (): void {
    it('is registered as view:cache', function (): void {
        $attribute = new ReflectionClass(ViewCacheCommand::class)->getAttributes(Command::class)[0]->newInstance();

        expect($attribute->name)->toBe('view:cache');
    });

    it('precompiles every template the resolver lists', function (): void {
        $precompiler = fakeTemplatePrecompiler();
        $command = new ViewCacheCommand($precompiler, viewCacheResolver(['blog::post/show', 'blog::layout']));

        [$exitCode, $output] = runViewCache($command);

        expect($exitCode)->toBe(0)
            ->and($precompiler->compiled)->toBe(['blog::post/show', 'blog::layout'])
            ->and($output)->toContain('Compiled 2 templates.');
    });

    it('keeps going after a broken template and fails naming its path and line', function (): void {
        $precompiler = fakeTemplatePrecompiler(broken: ['blog::broken']);
        $command = new ViewCacheCommand(
            $precompiler,
            viewCacheResolver(['blog::broken', 'blog::post/show']),
        );

        [$exitCode, $output] = runViewCache($command);

        expect($exitCode)->toBe(1)
            ->and($precompiler->compiled)->toBe(['blog::post/show'])
            ->and($output)->toContain("Template 'blog::broken' failed to compile: Unexpected end")
            ->and($output)->toContain('/app/resources/views/blog::broken.latte:3')
            ->and($output)->toContain('Compiled 1 of 2 templates; 1 failed.');
    });

    it('fails loudly when no template precompiler is bound', function (): void {
        new Container()->get(ViewCacheCommand::class);
    })->throws(NoDriverException::class, 'No template precompiler is bound.');
});
