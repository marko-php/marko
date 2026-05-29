<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\TemplateEntry;
use Marko\Lsp\Features\TemplateFeature;

function makeTemplateIndex(array $entries): IndexCache
{
    return new class ($entries) extends IndexCache
    {
        public function __construct(private array $entries)
        {
            // Skip parent constructor
        }

        public function getTemplates(): array
        {
            return $this->entries;
        }
    };
}

function makeTemplateEntry(string $moduleName, string $templateName, string $absolutePath = '', string $extension = 'php'): TemplateEntry
{
    $absolutePath = $absolutePath ?: "/modules/$moduleName/resources/views/$templateName.$extension";
    return new TemplateEntry($moduleName, $templateName, $absolutePath, $extension);
}

it('offers completion for module::template names inside render', function () {
    $index = makeTemplateIndex([
        makeTemplateEntry('Catalog', 'product/view'),
        makeTemplateEntry('Checkout', 'cart/index'),
    ]);
    $feature = new TemplateFeature($index);

    $items = $feature->complete('');

    expect($items)->toHaveCount(2)
        ->and($items[0]['label'])->toBe('Catalog::product/view')
        ->and($items[1]['label'])->toBe('Checkout::cart/index')
        ->and($items[0]['kind'])->toBe(17)
        ->and($items[0]['insertText'])->toBe('Catalog::product/view');
});

it('filters by partial module name and partial template path', function () {
    $index = makeTemplateIndex([
        makeTemplateEntry('Catalog', 'product/view'),
        makeTemplateEntry('Catalog', 'category/list'),
        makeTemplateEntry('Checkout', 'cart/index'),
    ]);
    $feature = new TemplateFeature($index);

    $byModule = $feature->complete('Catalog');
    expect($byModule)->toHaveCount(2)
        ->and($byModule[0]['label'])->toBe('Catalog::product/view')
        ->and($byModule[1]['label'])->toBe('Catalog::category/list');

    $byTemplate = $feature->complete('product');
    expect($byTemplate)->toHaveCount(1)
        ->and($byTemplate[0]['label'])->toBe('Catalog::product/view');
});

it('resolves goto-definition to the template absolute path', function () {
    $index = makeTemplateIndex([
        makeTemplateEntry('Catalog', 'product/view', '/modules/Catalog/resources/views/product/view.php'),
        makeTemplateEntry('Checkout', 'cart/index', '/modules/Checkout/resources/views/cart/index.php'),
    ]);
    $feature = new TemplateFeature($index);

    $result = $feature->gotoDefinition('Catalog::product/view');

    expect($result)->not->toBeNull()
        ->and($result['uri'])->toBe('file:///modules/Catalog/resources/views/product/view.php')
        ->and($result['range']['start']['line'])->toBe(0)
        ->and($result['range']['start']['character'])->toBe(0);

    expect($feature->gotoDefinition('Unknown::template'))->toBeNull();
});

it('publishes a diagnostic when the referenced template does not exist', function () {
    $index = makeTemplateIndex([
        makeTemplateEntry('Catalog', 'product/view'),
    ]);
    $feature = new TemplateFeature($index);

    $doc = '<?php $view->render(\'Catalog::product/view\'); $view->render(\'Missing::template\');';

    $diagnostics = $feature->diagnostics($doc);

    expect($diagnostics)->toHaveCount(1)
        ->and($diagnostics[0]['message'])->toBe('Template not found: Missing::template')
        ->and($diagnostics[0]['severity'])->toBe(1)
        ->and($diagnostics[0]['code'])->toBe('marko.template.not_found');
});

it('suggests the closest known template name in the diagnostic code action', function () {
    $index = makeTemplateIndex([
        makeTemplateEntry('Catalog', 'product/view'),
        makeTemplateEntry('Catalog', 'product/list'),
        makeTemplateEntry('Checkout', 'cart/index'),
    ]);
    $feature = new TemplateFeature($index);

    $suggestions = $feature->suggestSimilar('Catalog::product/vew');

    expect($suggestions)->not->toBeEmpty()
        ->and($suggestions[0])->toBe('Catalog::product/view');
});

it('supports plain template names without module prefix', function () {
    $index = makeTemplateIndex([
        makeTemplateEntry('Catalog', 'product/view', '/modules/Catalog/resources/views/product/view.php'),
        makeTemplateEntry('Checkout', 'cart/index', '/modules/Checkout/resources/views/cart/index.php'),
    ]);
    $feature = new TemplateFeature($index);

    // gotoDefinition resolves plain name — first match wins
    $result = $feature->gotoDefinition('product/view');
    expect($result)->not->toBeNull()
        ->and($result['uri'])->toBe('file:///modules/Catalog/resources/views/product/view.php');

    // diagnostics does not flag a plain template name that exists
    $doc = '<?php $view->render(\'product/view\');';
    $diagnostics = $feature->diagnostics($doc);
    expect($diagnostics)->toBeEmpty();
});
