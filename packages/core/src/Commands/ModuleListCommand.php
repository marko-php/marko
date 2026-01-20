<?php

declare(strict_types=1);

namespace Marko\Core\Commands;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Module\ModuleRepositoryInterface;

#[Command(name: 'module:list', description: 'Show all modules and their status')]
class ModuleListCommand implements CommandInterface
{
    public function __construct(
        private ModuleRepositoryInterface $moduleRepository,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $modules = $this->moduleRepository->all();

        // Calculate column widths based on data
        $nameWidth = strlen('NAME');
        $sourceWidth = strlen('SOURCE');

        foreach ($modules as $module) {
            $nameWidth = max($nameWidth, strlen($module->name));
            $sourceWidth = max($sourceWidth, strlen($module->source));
        }

        // Add padding
        $nameWidth += 2;
        $sourceWidth += 2;

        // Output header
        $output->writeLine(
            str_pad('NAME', $nameWidth) .
            str_pad('SOURCE', $sourceWidth) .
            'ENABLED',
        );

        // Output rows
        foreach ($modules as $module) {
            $enabled = $module->enabled ? 'yes' : 'no';
            $output->writeLine(
                str_pad($module->name, $nameWidth) .
                str_pad($module->source, $sourceWidth) .
                $enabled,
            );
        }

        return 0;
    }
}
