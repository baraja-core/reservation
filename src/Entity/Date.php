<?php

declare(strict_types=1);

namespace Baraja\Reservation\Entity;


use Baraja\Reservation\Repository\DateRepository;
use Baraja\Shop\Product\Entity\Product;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Nette\Utils\DateTime;

#[ORM\Entity(repositoryClass: DateRepository::class)]
#[ORM\Table(name: 'reservation__date')]
#[UniqueConstraint(name: 'reservation__date_date', columns: ['date', 'product_id'])]
class Date
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	/** Date in format "YYYY-MM-DD". */
	#[ORM\Column(type: 'string', length: 10)]
	private string $date;

	#[ORM\ManyToOne(targetEntity: Product::class)]
	private Product $product;

	#[ORM\ManyToOne(targetEntity: Reservation::class, inversedBy: 'dates')]
	private ?Reservation $reservation = null;

	#[ORM\ManyToOne(targetEntity: Season::class, inversedBy: 'dates')]
	private ?Season $season = null;


	public function __construct(string|\DateTimeInterface $date, Product $product)
	{
		$this->date = DateTime::from($date)
			->format('Y-m-d');
		$this->product = $product;
	}


	public function getId(): int
	{
		return $this->id;
	}


	public static function formatDate(int $year, int $month, int $day): string
	{
		$format = static fn(int $haystack): string => $haystack <= 9
			? '0' . $haystack
			: (string) $haystack;

		return $year . '-' . $format($month) . '-' . $format($day);
	}


	public function getDate(?string $format = null): string
	{
		return $format === null
			? $this->date
			: $this->getDateType()
				->format($format);
	}


	public function getProduct(): Product
	{
		return $this->product;
	}


	public function getDateType(): \DateTimeInterface
	{
		return DateTime::from($this->date);
	}


	public function isEnable(): bool
	{
		return $this->getDateType()->getTimestamp() >= \time()
			&& $this->season !== null
			&& $this->season->isActive() === true
			&& $this->isReservation() === false;
	}


	public function isReservation(): bool
	{
		return $this->reservation !== null;
	}


	public function isSeason(): bool
	{
		return $this->season !== null;
	}


	public function getReservation(): ?Reservation
	{
		return $this->reservation;
	}


	public function setReservation(?Reservation $reservation): void
	{
		$this->reservation = $reservation;
	}


	public function getSeason(): ?Season
	{
		return $this->season;
	}


	public function setSeason(?Season $season): void
	{
		$this->season = $season;
	}
}
