<?php

declare(strict_types=1);

namespace Marko\View\Latte\Extensions;

use Generator;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Extension;

class SlotExtension extends Extension
{
    public function getTags(): array
    {
        return [
            'slot' => [SlotNode::class, 'create'],
        ];
    }
}

class SlotNode extends StatementNode
{
    public string $name;

    public static function create(Tag $tag): Generator
    {
        $tag->expectArguments();
        $node = $tag->node = new static();
        $node->name = $tag->parser->stream->consume()->text;
        yield;
        return $node;
    }

    public function print(PrintContext $context): string
    {
        return $context->format(
            'echo ($slots[%dump] ?? \'\') %line;',
            $this->name,
            $this->position,
        );
    }

    public function &getIterator(): Generator
    {
        false && yield;
    }
}
