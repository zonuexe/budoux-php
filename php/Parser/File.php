<?php

declare(strict_types=1);

namespace Budoux\Parser;

use Budoux\Parser;

use function array_map;
use function array_sum;

/**
 * @phpstan-import-type Model from Parser
 */
final class File extends Parser
{
    /**
     * @param Model $model
     */
    public function __construct(
        private array $model,
    ) {
    }

    protected function getTotalScore(): int
    {
        return array_sum(array_map(array_sum(...), $this->model));
    }

    /**
     * @return Model
     */
    protected function getModel(): array
    {
        return $this->model;
    }
}
