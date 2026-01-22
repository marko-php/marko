<?php

declare(strict_types=1);

namespace Marko\Filesystem\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'storage:link', description: 'Create public storage symlink')]
class StorageLinkCommand implements CommandInterface
{
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $publicPath = getcwd() . '/public/storage';
        $targetPath = '../storage/public';

        if (file_exists($publicPath)) {
            $output->writeLine('Storage link already exists.');

            return 1;
        }

        $storagePublicDir = getcwd() . '/storage/public';

        if (!is_dir($storagePublicDir)) {
            mkdir($storagePublicDir, 0755, true);
        }

        if (!symlink($targetPath, $publicPath)) {
            $output->writeLine('Failed to create symlink.');

            return 1;
        }

        $output->writeLine("Storage symlink created: public/storage -> $targetPath");

        return 0;
    }
}
