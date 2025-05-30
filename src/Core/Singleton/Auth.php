<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Singleton;

use ApiPlatform\Model\Token;
use DateMalformedStringException;
use DateTime;
use Runway\DataStorage\Exception\DBException;
use Runway\DataStorage\QueryBuilder\Exception\QueryBuilderException;
use Runway\Env\Provider\IEnvVariablesProvider;
use ApiPlatform\Core\ICrypto;
use Runway\Model\Exception\ModelException;
use Runway\Singleton\Container;

class Auth implements IAuth {
    public function __construct(
        protected IEnvVariablesProvider $envVariablesProvider,
        protected string                $key,
        protected int                   $keyLength,
        protected string                $alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890_-",
        protected int                   $tokenTTL = 3600 * 24 * 30,
    ) {}

    public function isCredentialsCorrect(string $login, string $password): bool {
        $data = "$login&&&$password";
        $crypto = $this->getCrypto()->setKey($this->key);

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
            for ($i = 0; $i < $this->keyLength; $i++) {
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

    public function getKeyLength(): int {
        return $this->keyLength;
    }
}