<?php

declare(strict_types=1);

namespace ApiPlatform\Core;

use ApiPlatform\Core\Exception\CryptoException;
use Runway\Logger\ILogger;

class Crypto implements ICrypto {
    protected string $key = "";

    public function __construct(
        protected ILogger      $logger,
        public readonly string $cipher,
        public readonly int    $options,
        public readonly string $hashAlgo,
        public readonly int    $sha2Length
    ) {}

    public function setKey(string $key): static {
        $this->key = $key;

        return $this;
    }

    public function getKey(): string {
        return $this->key;
    }

    /**
     * @return string|null Encrypted string on success, null on failure.
     */
    public function encrypt(string $data): ?string {
        try {
            $ivLen = $this->getIvLength();

            /** @noinspection CryptographicallySecureRandomnessInspection */
            $iv = openssl_random_pseudo_bytes($ivLen);

            /** @noinspection PhpStrictComparisonWithOperandsOfDifferentTypesInspection */
            if ($iv === false) {
                throw new CryptoException("cannot get cipher iv.");
            }

            $cipherTextRaw = openssl_encrypt($data, $this->cipher, $this->key, $this->options, $iv);
            if ($cipherTextRaw === false) {
                $errorText = openssl_error_string();

                throw new CryptoException("cannot SSL encrypt ($errorText).");
            }
        } catch (CryptoException $e) {
            $this->handleCryptoException(
                $e,
                "Encryption error: {$e->getMessage()}."
            );

            return null;
        }

        $hmac = hash_hmac($this->hashAlgo, $cipherTextRaw, $this->key, true);
        return base64_encode($iv . $hmac . $cipherTextRaw);
    }


    /**
     * @return string|null Decrypted string of false on failure.
     */
    public function decrypt(string $data): ?string {
        try {
            $ivLen = $this->getIvLength();
            $data = base64_decode($data);
            $iv = substr($data, 0, $ivLen);
            $hmac = substr($data, $ivLen, $this->sha2Length);
            $cipherTextRaw = substr($data, $ivLen + $this->sha2Length);
            $plainText = openssl_decrypt($cipherTextRaw, $this->cipher, $this->key, $this->options, $iv);

            if ($plainText === false) {
                $errorText = openssl_error_string();

                throw new CryptoException(
                    "Cannot SSL decrypt: $errorText."
                );
            }
        } catch (CryptoException $e) {
            $this->handleCryptoException(
                $e,
                "Decryption error: {$e->getMessage()}."
            );

            return null;
        }

        $calcMac = hash_hmac($this->hashAlgo, $cipherTextRaw, $this->key, true);

        return (hash_equals($hmac, $calcMac)) ? $plainText : null;
    }

    /**
     * @throws CryptoException
     */
    protected function getIvLength(): int {
        $ivLen = openssl_cipher_iv_length($this->cipher);

        if ($ivLen === false) {
            throw new CryptoException("Cannot get cipher iv length.");
        }

        return $ivLen;
    }

    protected function handleCryptoException(CryptoException $e, string $errorText): void {
        $this->logger->error(
            $errorText,
            [
                'cipher'     => $this->cipher,
                'options'    => $this->options,
                'hashAlgo'   => $this->hashAlgo,
                'sha2Length' => $this->sha2Length
            ]
        );
    }
}