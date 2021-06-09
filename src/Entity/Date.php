<?php

declare(strict_types=1);

namespace Baraja\Reservation\Entity;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Doctrine\ORM\Mapping as ORM;
use Nette\Utils\DateTime;

/**
 * @ORM\Entity()
 * @ORM\Table(name="reservation__date")
 */
class Date
{
	use IdentifierUnsigned;

	/**
	 * Date in format "YYYY-MM-DD".
	 *
	 * @ORM\Column(type="string", length=10, unique=true)
	 */
	private string $date;

	/** @ORM\ManyToOne(targetEntity="Reservation", inversedBy="dates") */
	private ?Reservation $reservation = null;

	/** @ORM\ManyToOne(targetEntity="Season", inversedBy="dates") */
	private ?Season $season = null;


	public function __construct(string|\DateTimeInterface $date)
	{
		$this->date = DateTime::from($date)
			->format('Y-m-d');
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


	public function getDateType(): \DateTimeInterface
	{
		return DateTime::from($this->date);
	}


	public function isEnable(): bool
	{
		$season = $this->season;

		return
			$this->getDateType()->getTimestamp() >= \time()
			&& $season !== null
			&& $season->isActive() === true
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
