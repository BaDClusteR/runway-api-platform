<?php

declare(strict_types=1);

namespace ApiPlatform\Model;

use ApiPlatform\Core\Trait\RequestTrait;
use DateTime;
use Runway\DataStorage\Attribute as DS;
use Runway\Model\AEntity;

/**
 * @method int getId()
 * @method $this setId(int $id)
 * @method string getToken()
 * @method $this setToken(string $token)
 * @method DateTime getGenerated()
 * @method $this setGenerated(DateTime $generated)
 * @method bool getActive()
 * @method $this setActive(bool $isActive)
 * @method DateTime getValidUntil()
 * @method $this setValidUntil(DateTime $active)
 * @method DateTime getLastActive()
 * @method $this setLastActive(DateTime $lastActive)
 * @method string getIp()
 * @method $this setIp(string $ip)
 * @method string getUserAgent()
 * @method $this setUserAgent(string $userAgent)
 * @method array getServerInfo()
 * @method $this setServerInfo(array $info)
 * @method bool getExpired()
 * @method $this setExpired(bool $expired)
 */
#[DS\Table("tokens")]
class Token extends AEntity {
    use RequestTrait;

    #[DS\Id]
    protected ?int $id = null;

    #[DS\Column]
    protected string $token = "";

    #[DS\Column]
    protected DateTime $generated;

    #[DS\Column]
    protected bool $active = false;

    #[DS\Column]
    protected DateTime $validUntil;

    #[DS\Column]
    protected DateTime $lastActive;

    #[DS\Column]
    protected string $ip = "";

    #[DS\Column]
    protected string $userAgent = "";

    #[DS\Column]
    protected array $serverInfo = [];

    #[DS\Column]
    protected bool $expired = false;

    public function updateClientInfo(): static {
        $request = $this->getRequest();

        $this->setLastActive(new DateTime());
        $this->setIp($request->getIpAddress());
        $this->setUserAgent($request->getUserAgent());
        $this->setServerInfo($request->getServerParameters());

        return $this;
    }
}