<?php

declare(strict_types=1);

namespace Baraja\Reservation\Entity;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Baraja\PhoneNumber\PhoneNumberFormatter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nette\Utils\Random;
use Nette\Utils\Strings;

/**
 * @ORM\Entity()
 * @ORM\Table(name="reservation__reservation")
 */
class Reservation
{
	use IdentifierUnsigned;

	public const
		STATUS_NEW = 'new',
		STATUS_PAID = 'paid',
		STATUS_STORNO = 'storno';

	/**
	 * @var Date[]|Collection
	 * @ORM\OneToMany(targetEntity="Date", mappedBy="reservation")
	 */
	private $dates;

	/** @ORM\Column(type="date", name="`from`") */
	private \DateTimeInterface $from;

	/** @ORM\Column(type="date", name="`to`") */
	private \DateTimeInterface $to;

	/** @ORM\Column(type="integer") */
	private int $price;

	/** @ORM\Column(type="string", length=24, nullable=true) */
	private ?string $number = null;

	/** @ORM\Column(type="string", length=16) */
	private string $status = self::STATUS_NEW;

	/** @ORM\Column(type="string", length=32, unique=true) */
	private string $hash;

	/** @ORM\Column(type="string", length=32, nullable=true) */
	private ?string $firstName;

	/** @ORM\Column(type="string", length=32, nullable=true) */
	private ?string $lastName;

	/** @ORM\Column(type="string", length=128) */
	private string $email;

	/** @ORM\Column(type="string", length=16, nullable=true) */
	private ?string $phone = null;

	/** @ORM\Column(type="datetime") */
	private \DateTimeInterface $createDate;


	public function __construct(
		\DateTime $from,
		\DateTime $to,
		int $price,
		?string $firstName,
		?string $lastName,
		string $email
	) {
		$this->from = $from;
		$this->to = $to;
		$this->price = $price;
		$this->firstName = Strings::firstUpper($firstName ?? '') ?: null;
		$this->lastName = Strings::firstUpper($lastName ?? '') ?: null;
		$this->email = (string) mb_strtolower($email, 'UTF-8');
		$this->hash = Random::generate(32);
		$this->createDate = new \DateTime;
		$this->dates = new ArrayCollection;
	}


	public function getIdentifier(): string
	{
		return $this->getNumber() ?? (string) $this->getId();
	}


	public function getNumber(): ?string
	{
		return $this->number;
	}


	public function setNumber(?string $number): void
	{
		$this->number = $number;
	}


	/**
	 * @return Date[]|Collection
	 */
	public function getDates()
	{
		return $this->dates;
	}


	public function addDate(Date $date): void
	{
		$this->dates[] = $date;
	}


	public function getFrom(): \DateTimeInterface
	{
		return $this->from;
	}


	public function setFrom(\DateTime $from): void
	{
		$this->from = $from;
	}


	public function getTo(): \DateTimeInterface
	{
		return $this->to;
	}


	public function setTo(\DateTime $to): void
	{
		$this->to = $to;
	}


	public function getPrice(): int
	{
		return $this->price;
	}


	public function setPrice(int $price): void
	{
		$this->price = $price;
	}


	public function getStatus(): string
	{
		return $this->status;
	}


	public function getHash(): string
	{
		return $this->hash;
	}


	public function getName(): ?string
	{
		return trim($this->getFirstName() . ' ' . $this->getLastName()) ?: null;
	}


	public function getFirstName(): ?string
	{
		return $this->firstName;
	}


	public function setFirstName(?string $firstName): void
	{
		$this->firstName = $firstName;
	}


	public function getLastName(): ?string
	{
		return $this->lastName;
	}


	public function setLastName(?string $lastName): void
	{
		$this->lastName = $lastName;
	}


	public function getEmail(): string
	{
		return $this->email;
	}


	public function setEmail(string $email): void
	{
		$this->email = $email;
	}


	public function getPhone(): ?string
	{
		return $this->phone;
	}


	public function setPhone(?string $phone): void
	{
		if ($phone !== null) {
			$phone = PhoneNumberFormatter::fix($phone);
		}
		$this->phone = $phone;
	}


	public function getCreateDate(): \DateTimeInterface
	{
		return $this->createDate;
	}


	public function setCreateDate(\DateTimeInterface $createDate): void
	{
		$this->createDate = $createDate;
	}
}
