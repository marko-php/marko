<?php

declare(strict_types=1);

namespace Marko\DocsVec\Query;

use Marko\DocsVec\Exceptions\VecRuntimeException;
use Marko\DocsVec\Runtime\VecRuntime;

class QueryEmbedder
{
    public function __construct(
        private VecRuntime $runtime,
    ) {}

    /**
     * @return list<float> 384-dim unit vector
     *
     * @throws VecRuntimeException
     */
    public function embed(string $query): array
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            throw VecRuntimeException::emptyQuery();
        }

        $vector = $this->runtime->embed($trimmed);

        return $this->normalize($vector);
    }

    /**
     * @param list<float> $vector
     *
     * @return list<float>
     */
    private function normalize(array $vector): array
    {
        $magnitude = sqrt(array_sum(array_map(fn (float $v) => $v * $v, $vector)));
        if ($magnitude === 0.0) {
            return $vector;
        }

        return array_map(fn (float $v) => $v / $magnitude, $vector);
    }
}
