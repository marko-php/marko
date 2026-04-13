<?php

declare(strict_types=1);

namespace Marko\Vite;

use Marko\Core\Event\EventDispatcherInterface;
use Marko\Vite\Contracts\TagRendererInterface;
use Marko\Vite\Events\AssetTagsRendered;
use Marko\Vite\ValueObjects\AssetTag;
use Marko\Vite\ValueObjects\ResolvedAsset;
use Marko\Vite\ValueObjects\ResolvedEntrypointCollection;

class TagRenderer implements TagRendererInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $events,
    ) {}

    public function renderTags(ResolvedEntrypointCollection $collection): string
    {
        $tags = [
            ...$this->buildPreloadTags($collection),
            ...$this->buildStyleTags($collection),
            ...$this->buildScriptTags($collection),
        ];

        return $this->render($collection, $tags, 'all');
    }

    public function renderScripts(ResolvedEntrypointCollection $collection): string
    {
        return $this->render($collection, $this->buildScriptTags($collection), 'scripts');
    }

    public function renderStyles(ResolvedEntrypointCollection $collection): string
    {
        return $this->render($collection, [
            ...$this->buildPreloadTags($collection),
            ...$this->buildStyleTags($collection),
        ], 'styles');
    }

    /**
     * @return array<AssetTag>
     */
    protected function buildPreloadTags(ResolvedEntrypointCollection $collection): array
    {
        return array_map(
            fn (ResolvedAsset $asset): AssetTag => new AssetTag('link', [
                'rel' => 'modulepreload',
                'href' => $asset->url,
            ]),
            $collection->preloads,
        );
    }

    /**
     * @return array<AssetTag>
     */
    protected function buildStyleTags(ResolvedEntrypointCollection $collection): array
    {
        return array_map(
            fn (ResolvedAsset $asset): AssetTag => new AssetTag('link', [
                'rel' => 'stylesheet',
                'href' => $asset->url,
            ]),
            $collection->styles,
        );
    }

    /**
     * @return array<AssetTag>
     */
    protected function buildScriptTags(ResolvedEntrypointCollection $collection): array
    {
        return array_map(
            fn (ResolvedAsset $asset): AssetTag => new AssetTag('script', [
                'type' => 'module',
                'src' => $asset->url,
            ]),
            $collection->scripts,
        );
    }

    /**
     * @param array<AssetTag> $tags
     */
    protected function render(
        ResolvedEntrypointCollection $collection,
        array $tags,
        string $kind,
    ): string {
        $html = implode("\n", array_map($this->renderTag(...), $tags));

        $this->events->dispatch(new AssetTagsRendered(
            assets: $collection,
            html: $html,
            kind: $kind,
        ));

        return $html;
    }

    protected function renderTag(AssetTag $tag): string
    {
        $attributes = [];

        foreach ($tag->attributes as $name => $value) {
            $attributes[] = sprintf('%s="%s"', $name, htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
        }

        $attributeString = implode(' ', $attributes);

        if ($tag->tag === 'script') {
            return sprintf('<script %s></script>', $attributeString);
        }

        return sprintf('<%s %s>', $tag->tag, $attributeString);
    }
}
