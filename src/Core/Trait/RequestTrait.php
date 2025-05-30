<?php

namespace ApiPlatform\Core\Trait;

use Runway\Request\IRequest;
use Runway\Singleton\Container;

trait RequestTrait {
    protected function getRequest(): IRequest {
        return Container::getInstance()->getService(IRequest::class);
    }
}