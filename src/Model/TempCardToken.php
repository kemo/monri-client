<?php

declare(strict_types=1);

namespace Kemo\Monri\Model;

final class TempCardToken
{
    public function __construct(
        public readonly string $id,
        public readonly int $timestamp,
        public readonly string $digest,
    ) {}
}
