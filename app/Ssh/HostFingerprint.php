<?php

namespace App\Ssh;

readonly class HostFingerprint
{
    public function __construct(
        public string $key,
        public string $fingerprint,
    ) {}
}
