<?php

declare(strict_types=1);

namespace Kemo\Monri;

enum Environment: string
{
    case Test = 'test';
    case Production = 'production';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Test => 'https://ipgtest.monri.com',
            self::Production => 'https://ipg.monri.com',
        };
    }
}
