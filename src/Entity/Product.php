<?php

declare(strict_types=1);

namespace Baraja\Reservation\Entity;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="nordic__product")
 */
class Product
{
	use IdentifierUnsigned;
}
