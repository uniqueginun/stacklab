<?php

namespace App\Support;

final class PhpVersions
{
    /** @var list<string> */
    public const VERSIONS = ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'];

    public const DEFAULT = '8.3';

    /** @return list<string> */
    public static function all(): array
    {
        return self::VERSIONS;
    }

    public static function default(): string
    {
        return self::DEFAULT;
    }
}
