<?php

namespace App\Ssh;

readonly class SshResult
{
    public function __construct(
        public int $exitCode,
        public string $output,
        public string $errorOutput = '',
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }
}
