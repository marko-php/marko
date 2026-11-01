<?php

declare(strict_types=1);

namespace Marko\View\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\View\Exceptions\TemplatePrecompileException;
use Marko\View\TemplatePrecompilerInterface;
use Marko\View\TemplateResolverInterface;

/**
 * Compiles every resolvable template ahead of time with the installed view driver.
 *
 * Every template is attempted; each failure is reported with its file and
 * line, and the command fails when any template does not compile.
 *
 * @noinspection PhpUnused
 */
#[Command(name: 'view:cache', description: 'Compile every template into the view cache')]
readonly class ViewCacheCommand implements CommandInterface
{
    public function __construct(
        private TemplatePrecompilerInterface $templatePrecompiler,
        private TemplateResolverInterface $templateResolver,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $templates = $this->templateResolver->all();
        $failed = 0;

        foreach ($templates as $template) {
            try {
                $this->templatePrecompiler->precompile($template);
            } catch (TemplatePrecompileException $exception) {
                $failed++;
                $output->writeLine($exception->getMessage());
                $output->writeLine('  in ' . $exception->path . ':' . $exception->templateLine);
            }
        }

        $total = count($templates);

        if ($failed > 0) {
            $output->writeLine(sprintf('Compiled %d of %d templates; %d failed.', $total - $failed, $total, $failed));

            return 1;
        }

        $output->writeLine("Compiled $total templates.");

        return 0;
    }
}
