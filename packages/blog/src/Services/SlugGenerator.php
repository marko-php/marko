<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

use Closure;
use Transliterator;

class SlugGenerator implements SlugGeneratorInterface
{
    /**
     * @param Closure(string): bool|null $uniquenessChecker
     */
    public function generate(
        string $title,
        ?Closure $uniquenessChecker = null,
    ): string {
        $transliterator = Transliterator::create('Any-Latin; Latin-ASCII');
        $slug = $transliterator->transliterate($title);
        $slug = strtolower($slug);
        $slug = preg_replace('/\s+/', '-', $slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        if ($uniquenessChecker === null) {
            return $slug;
        }

        $baseSlug = $slug;
        $counter = 1;

        while (!$uniquenessChecker($slug)) {
            $slug = "$baseSlug-$counter";
            $counter++;
        }

        return $slug;
    }
}
