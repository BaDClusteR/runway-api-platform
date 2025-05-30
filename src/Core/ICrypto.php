<?php

declare(strict_types=1);

namespace ApiPlatform\Core;

interface ICrypto {
    public function setKey(string $key): static;

    public function getKey(): string;

    /**
     * @return string|null Encrypted string on success, null on failure.
     */
    public function encrypt(string $data): ?string;

    /**
     * @return string|null Decrypted string of false on failure.
     */
    public function decrypt(string $data): ?string;
}