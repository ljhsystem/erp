<?php

namespace App\Services\Auth;

class TwoFactorService
{
    private int $ttl = 300;
    private int $maxAttempts = 5;

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function generateCode(): string
    {
        try {
            return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            return sprintf('%06d', mt_rand(0, 999999));
        }
    }

    public function hashCode(string $code): string
    {
        return hash('sha256', trim($code));
    }

    public function matches(string $plainCode, string $hash): bool
    {
        if ($plainCode === '' || $hash === '') {
            return false;
        }

        return hash_equals($hash, $this->hashCode($plainCode));
    }
}
