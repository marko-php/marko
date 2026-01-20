<?php

declare(strict_types=1);

namespace Marko\Tools\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Forces all function/method parameters to be on separate lines with trailing commas.
 */
class MultilineMethodParametersRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Put each parameter on its own line with trailing comma',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
public function register(
    ModuleManifest $module,
): void {
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
public function register(
    ModuleManifest $module,
): void {
}
CODE_SAMPLE
                ),
            ],
        );
    }

    public function getNodeTypes(): array
    {
        return [
            ClassMethod::class,
            Function_::class,
            Closure::class,
            ArrowFunction::class,
        ];
    }

    public function refactor(
        Node $node,
    ): ?Node {
        /** @var ClassMethod|Function_|Closure|ArrowFunction $node */
        if ($node->params === []) {
            return null;
        }

        // Check if already multiline by looking at the node's attributes
        $hasChanges = false;

        foreach ($node->params as $index => $param) {
            // Add newline before each parameter
            $comments = $param->getComments();

            // Set attribute to indicate this param should be on new line
            if (!$param->getAttribute('multiline')) {
                $param->setAttribute('multiline', true);
                $hasChanges = true;
            }
        }

        // Mark the node itself as needing multiline formatting
        if (!$node->getAttribute('multilineParams')) {
            $node->setAttribute('multilineParams', true);
            $hasChanges = true;
        }

        return $hasChanges ? $node : null;
    }
}
