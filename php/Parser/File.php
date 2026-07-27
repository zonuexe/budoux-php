<?php

declare(strict_types=1);

namespace Budoux\Parser;

use Budoux\Parser;

use function array_map;
use function array_sum;

final class File extends Parser
{
    /**
     * @param array<string, array<string, int>> $model
     */
    public function __construct(
        private array $model,
    ) {
    }

    protected function getTotalScore(): int
    {
        return (int) array_sum(array_map(array_sum(...), $this->model));
    }

    protected function getScore(string $featureKey, string $sequence): int
    {
        return $this->model[$featureKey][$sequence] ?? 0;
    }
}
