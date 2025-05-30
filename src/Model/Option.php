<?php

declare(strict_types=1);

namespace ApiPlatform\Model;

use Runway\DataStorage\Attribute as DS;
use Runway\Model\AEntity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getValue()
 * @method void setValue(string $value)
 */
#[DS\Table("options")]
class Option extends AEntity {
    #[DS\Id]
    protected ?int $id = null;

    #[DS\Column]
    protected string $name = "";

    #[DS\Column]
    protected string $value = "";
}