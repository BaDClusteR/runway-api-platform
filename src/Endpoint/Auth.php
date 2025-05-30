<?php

declare(strict_types=1);

namespace ApiPlatform\Endpoint;

use ApiPlatform\Attribute as API;
use ApiPlatform\Attribute\Assert;
use ApiPlatform\Attribute\Docs;
use ApiPlatform\Core\Singleton\IAuth;
use ApiPlatform\Core\Storage\ITokenStorage;
use ApiPlatform\Endpoint\DTO\Auth\AuthenticateDTO;
use ApiPlatform\Endpoint\DTO\Auth\LogoffDTO;
use ApiPlatform\Exception\AuthFailedException;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\Model\Token;
use Runway\DataStorage\Exception\DBException;
use Runway\DataStorage\QueryBuilder\Exception\QueryBuilderException;
use Runway\Model\Exception\ModelException;

#[Docs\Group("Authorization")]
class Auth {
    public function __construct(
        protected IAuth         $auth,
        protected ITokenStorage $tokenStorage
    ) {}

    /**
     * @throws InternalErrorException
     * @throws AuthFailedException
     */
    #[API\Endpoint(path: "auth", method: "POST", public: true)]
    #[Docs\Endpoint("Authenticate user")]
    public function authenticate(
        #[API\Parameter(source: "body")]
        #[Assert\NotEmpty]
        #[Docs\Argument(example: "myLogin", description: "User login")]
        string $login,

        #[API\Parameter(source: "body")]
        #[Docs\Argument(format: "password", example: "myPassword", description: "User password")]
        #[Assert\NotEmpty]
        string $password
    ): AuthenticateDTO {
        $this->deactivateActiveToken();

        if ($this->auth->isCredentialsCorrect($login, $password)) {
            $tokenEntity = $this->auth->generateToken();

            return new AuthenticateDTO(
                token: $tokenEntity->getToken(),
                valid_until: $tokenEntity->getValidUntil()->format(DATE_ATOM)
            );
        }

        throw new AuthFailedException();
    }

    /**
     * @throws InternalErrorException
     */
    #[API\Endpoint(path: "logoff", method: "GET")]
    #[Docs\Endpoint("Log off")]
    public function logoff(): LogoffDTO {
        $this->deactivateActiveToken();

        return new LogoffDTO();
    }

    /**
     * @throws InternalErrorException
     */
    protected function deactivateActiveToken(): void {
        $token = $this->tokenStorage->getToken();

        if ($this->auth->isAuthenticated($token)) {
            try {
                /** @var Token|null $tokenModel */
                $tokenModel = Token::findOne([
                    'token' => $token
                ]);

                $tokenModel?->setActive(false)?->persist();
            } catch (DBException|QueryBuilderException|ModelException $e) {
                throw new InternalErrorException($e->getMessage(), $e);
            }
        }
    }
}