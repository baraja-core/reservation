<?php

declare(strict_types=1);

namespace Baraja\Reservation\Entity;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Baraja\Shop\Product\Entity\Product;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nette\Utils\Strings;

/**
 * @ORM\Entity()
 * @ORM\Table(name="reservation__season")
 */
class Season
{
	use IdentifierUnsigned;

	/** @ORM\Column(type="string") */
	private string $name = 'Season';

	/** @ORM\Column(type="text", nullable=true) */
	private ?string $description = null;

	/** @ORM\Column(type="integer") */
	private int $price;

	/** @ORM\Column(type="integer") */
	private int $minimalDays = 1;

	/** @ORM\Column(type="boolean") */
	private bool $active = false;

	/** @ORM\ManyToOne(targetEntity="\Baraja\Shop\Product\Entity\Product") */
	private ?Product $product = null;

	/**
	 * @var Date[]|Collection
	 * @ORM\OneToMany(targetEntity="Date", mappedBy="season")
	 */
	private $dates;


	public function __construct(int $price, ?Product $product = null)
	{
		$this->setPrice($price);
		$this->setProduct($product);
	}


	public function getName(): string
	{
		return $this->name;
	}


	public function setName(string $name): void
	{
		$this->name = Strings::firstUpper(trim($name)) ?: 'Season';
	}


	public function getDescription(): ?string
	{
		return $this->description;
	}


	public function setDescription(?string $description): void
	{
		$this->description = trim($description ?? '') ?: null;
	}


	public function getPrice(): int
	{
		return $this->price;
	}


	public function setPrice(int $price): void
	{
		if ($price < 1) {
			throw new \InvalidArgumentException('Minimal season price is 1, but "' . $price . '" given.');
		}
		$this->price = $price;
	}


	public function getMinimalDays(): int
	{
		return $this->minimalDays;
	}


	public function setMinimalDays(int $minimalDays): void
	{
		if ($minimalDays < 1) {
			throw new \InvalidArgumentException(
				'Minimal reservation days for season is 1, but "' . $minimalDays . '" given.'
			);
		}
		$this->minimalDays = $minimalDays;
	}


	public function isActive(): bool
	{
		return $this->active;
	}


	public function setActive(bool $active): void
	{
		$this->active = $active;
	}


	public function getFromDate(): \DateTimeInterface
	{
		$date = $this->getDatesSorted('ASC');
		if (isset($date[0]) === true) {
			return $date[0]->getDateType();
		}

		throw new \LogicException('Date must exist.');
	}


	public function getToDate(): \DateTimeInterface
	{
		$date = $this->getDatesSorted('DESC');
		if (isset($date[0]) === true) {
			return $date[0]->getDateType();
		}

		throw new \LogicException('Date must exist.');
	}


	/**
	 * @return Date[]|Collection
	 */
	public function getDates()
	{
		return $this->dates;
	}


	/**
	 * @return Date[]
	 */
	public function getDatesSorted(string $direction = 'ASC'): array
	{
		if ($direction === 'ASC') {
			[$x, $y] = [-1, 1];
		} elseif ($direction === 'DESC') {
			[$x, $y] = [1, -1];
		} else {
			throw new \InvalidArgumentException(
				'Direction "' . $direction . '" does not exist. Did you mean "ASC" or "DESC"?'
			);
		}

		$return = [];
		foreach ($this->getDates() as $date) {
			$return[] = $date;
		}
		usort(
			$return,
			static function (Date $a, Date $b) use ($x, $y): int {
				$left = $a->getDateType()
					->getTimestamp();

				$right = $b->getDateType()
					->getTimestamp();

				return $left < $right ? $x : $y;
			}
		);

		return $return;
	}


	/**
	 * @return string[]
	 */
	public function getDatesFormatted(): array
	{
		$return = [];
		foreach ($this->getDates() as $date) {
			$return[] = $date->getDate();
		}

		return $return;
	}


	public function addDate(Date $date): void
	{
		$this->dates[] = $date;
	}


	public function getProduct(): ?Product
	{
		return $this->product;
	}


	public function setProduct(?Product $product): void
	{
		$this->product = $product;
	}
}
