<?php

declare(strict_types=1);

namespace Baraja\Reservation\Entity;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Baraja\Shop\Product\Entity\Product;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="reservation__reservation_product_item")
 */
class ReservationProductItem
{
	use IdentifierUnsigned;

	/** @ORM\ManyToOne(targetEntity="Reservation", inversedBy="productItems") */
	private Reservation $reservation;

	/** @ORM\ManyToOne(targetEntity="\Baraja\Shop\Product\Entity\Product") */
	private Product $product;

	private int $quantity;


	public function __construct(Reservation $reservation, Product $product, int $quantity = 1)
	{
		$this->reservation = $reservation;
		$this->product = $product;
		$this->setQuantity($quantity);
	}


	public function getReservation(): Reservation
	{
		return $this->reservation;
	}


	public function getProduct(): Product
	{
		return $this->product;
	}


	public function getQuantity(): int
	{
		return $this->quantity;
	}


	public function setQuantity(int $quantity): void
	{
		if ($quantity < 1) {
			$quantity = 1;
		}
		$this->quantity = $quantity;
	}


	public function addQuantity(int $quantity): void
	{
		$this->setQuantity($this->getQuantity() + $quantity);
	}
}
