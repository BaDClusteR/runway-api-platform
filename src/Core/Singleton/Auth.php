<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Singleton;

use ApiPlatform\Model\Token;
use DateMalformedStringException;
use DateTime;
use Runway\DataStorage\Exception\DBException;
use Runway\DataStorage\QueryBuilder\Exception\QueryBuilderException;
use ApiPlatform\Exception\InternalErrorException;
use Runway\Env\Provider\IEnvVariablesProvider;
use ApiPlatform\Core\ICrypto;
use Runway\Model\Exception\ModelException;
use Runway\Singleton\Container;

class Auth implements IAuth {
    /**
     * The ENV values are typed by the parser ("true" is a bool, "32" is an int, an empty value is ""), so they are
     * normalized here.
     *
     * @param mixed $enabled   API_AUTH_ENABLED: false makes every endpoint public (e.g. the access is limited by the
     *                         web server). Enabled if not set or invalid.
     * @param mixed $key       API_CRYPTO_KEY, required only if the authorization is enabled.
     * @param mixed $keyLength API_CRYPTO_KEY_LENGTH, the token length, required only if the authorization is enabled.
     */
    public function __construct(
        protected IEnvVariablesProvider $envVariablesProvider,
        protected mixed                 $enabled = true,
        protected mixed                 $key = null,
        protected mixed                 $keyLength = null,
        protected string                $alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890_-",
        protected int                   $tokenTTL = 3600 * 24 * 30,
    ) {}

    public function isEnabled(): bool {
        return filter_var($this->enabled ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    public function isCredentialsCorrect(string $login, string $password): bool {
        $data = "$login&&&$password";
        $crypto = $this->getCrypto()->setKey($this->getKey());

        return $data === $crypto->decrypt(
                $this->envVariablesProvider->getEnvVariable("API_AUTH_HASH")
            );
    }

    /**
     * @throws ModelException
     * @throws QueryBuilderException
     * @throws DBException
     */
    public function isTokenValid(string $token): bool {
        $now = time();
        /** @var Token|null $dbToken */
        $dbToken = Token::findOne([
            'token'   => $token,
            'expired' => false,
            'active'  => true
        ]);

        return $dbToken &&
            $dbToken->getGenerated()->getTimestamp() <= $now &&
            $dbToken->getValidUntil()->getTimestamp() >= $now;
    }

    /**
     * @throws ModelException
     * @throws QueryBuilderException
     * @throws DBException
     * @throws DateMalformedStringException
     */
    public function generateToken(): Token {
        $token = $this->generateUniqueToken();
        $generatedDateTime = new DateTime();
        $validUntilDateTime = new DateTime(
            date(
                DATE_ATOM,
                time() + $this->tokenTTL
            )
        );

        $tokenEntity = new Token()
            ->updateClientInfo()
            ->setGenerated($generatedDateTime)
            ->setValidUntil($validUntilDateTime)
            ->setActive(true)
            ->setToken($token);

        $tokenEntity->persist();

        return $tokenEntity;
    }

    /**
     * @throws DBException
     * @throws ModelException
     * @throws QueryBuilderException
     */
    protected function generateUniqueToken(): string {
        $alphabetLength = strlen($this->alphabet);

        do {
            $result = "";
            for ($i = 0, $keyLength = $this->getKeyLength(); $i < $keyLength; $i++) {
                /** @noinspection RandomApiMigrationInspection */
                $result .= $this->alphabet[mt_rand(0, $alphabetLength - 2)];
            }
        } while ($this->isTokenExists($result));

        return $result;
    }

    /**
     * @throws QueryBuilderException
     * @throws ModelException
     * @throws DBException
     */
    protected function isTokenExists(string $token): bool {
        return (Token::findOne(['token' => $token]) !== null);
    }

    /**
     * @throws QueryBuilderException
     * @throws DBException
     * @throws ModelException
     */
    public function isAuthenticated(string $token): bool {
        return $token
            && $this->isTokenValid($token)
            && $this->isTokenExists($token);
    }

    /**
     * @throws DBException
     * @throws ModelException
     * @throws QueryBuilderException
     */
    public function updateTokenLastActiveDate(string $token): void {
        /** @var Token|null $tokenModel */
        $tokenModel = Token::findOne(['token' => $token]);

        $tokenModel?->setLastActive(new DateTime('now'))
                   ?->persist();
    }

    protected function getCrypto(): ICrypto {
        return Container::getInstance()->getService(ICrypto::class);
    }

    /**
     * @throws InternalErrorException If the key length is not configured.
     */
    public function getKeyLength(): int {
        $keyLength = filter_var($this->keyLength, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return ($keyLength !== false)
            ? $keyLength
            : throw new InternalErrorException('The authorization is not configured: API_CRYPTO_KEY_LENGTH is not set.');
    }

    /**
     * @throws InternalErrorException If the key is not configured: an empty key would be a zero AES key.
     */
    protected function getKey(): string {
        return (is_string($this->key) && $this->key !== '')
            ? $this->key
            : throw new InternalErrorException('The authorization is not configured: API_CRYPTO_KEY is not set.');
    }
}