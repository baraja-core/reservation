<?php

declare(strict_types=1);

namespace Baraja\Reservation\Entity;


use Baraja\PhoneNumber\PhoneNumberFormatter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nette\Utils\Random;
use Nette\Utils\Strings;

#[ORM\Entity]
#[ORM\Table(name: 'reservation__reservation')]
class Reservation
{
	public const
		StatusNew = 'new',
		StatusPaid = 'paid',
		StatusStorno = 'storno';

	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	/** @var Collection<Date> */
	#[ORM\OneToMany(mappedBy: 'reservation', targetEntity: Date::class)]
	private Collection $dates;

	#[ORM\Column(type: 'integer')]
	private int $price;

	#[ORM\Column(type: 'string', length: 24, nullable: true)]
	private ?string $number = null;

	#[ORM\Column(type: 'string', length: 16)]
	private string $status = self::StatusNew;

	#[ORM\Column(type: 'string', length: 32, unique: true)]
	private string $hash;

	#[ORM\Column(type: 'string', length: 32, nullable: true)]
	private ?string $firstName;

	#[ORM\Column(type: 'string', length: 32, nullable: true)]
	private ?string $lastName;

	#[ORM\Column(type: 'string', length: 128)]
	private string $email;

	#[ORM\Column(type: 'string', length: 16, nullable: true)]
	private ?string $phone = null;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $note = null;

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $createDate;

	/** @var Collection<ReservationProductItem> */
	#[ORM\OneToMany(mappedBy: 'reservation', targetEntity: ReservationProductItem::class)]
	private Collection $productItems;


	public function __construct(
		int $price,
		?string $firstName,
		?string $lastName,
		string $email,
	) {
		$this->price = $price;
		$this->firstName = $firstName !== '' && $firstName !== null
			? Strings::firstUpper($firstName)
			: null;
		$this->lastName = $lastName !== '' && $lastName !== null
			? Strings::firstUpper($lastName)
			: null;
		$this->email = mb_strtolower($email, 'UTF-8');
		$this->hash = Random::generate(32);
		$this->createDate = new \DateTime;
		$this->dates = new ArrayCollection;
		$this->productItems = new ArrayCollection;
	}


	public function getId(): int
	{
		return $this->id;
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
	 * @return Collection<Date>
	 */
	public function getDates()
	{
		return $this->dates;
	}


	/**
	 * @return Collection<ReservationProductItem>
	 */
	public function getProductItems()
	{
		return $this->productItems;
	}


	public function addDate(Date $date): void
	{
		$this->dates[] = $date;
	}


	public function addItem(ReservationProductItem $item): ReservationProductItem
	{
		$exist = null;
		foreach ($this->productItems as $lastItem) { // Item already exist
			if ($lastItem->getProduct()->getId() === $item->getProduct()->getId()) {
				$exist = $lastItem;
				break;
			}
		}
		if ($exist !== null) {
			$exist->addQuantity($item->getQuantity());

			return $exist;
		}

		$this->productItems[] = $item;

		return $item;
	}


	public function getFrom(): \DateTimeInterface
	{
		$min = null;
		foreach ($this->dates as $date) {
			if ($min === null || $date->getDateType() < $min) {
				$min = $date->getDateType();
			}
		}
		if ($min === null) {
			throw new \LogicException(sprintf('Reservation "%s" is empty.', $this->getIdentifier()));
		}

		return $min;
	}


	public function getTo(): \DateTimeInterface
	{
		$max = null;
		foreach ($this->dates as $date) {
			if ($max === null || $date->getDateType() > $max) {
				$max = $date->getDateType();
			}
		}
		if ($max === null) {
			throw new \LogicException(sprintf('Reservation "%s" is empty.', $this->getIdentifier()));
		}

		return $max;
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
		$name = trim(sprintf('%s %s', $this->getFirstName(), $this->getLastName()));

		return $name !== '' ? $name : null;
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


	public function getNote(): ?string
	{
		return $this->note;
	}


	public function setNote(?string $note): void
	{
		$note = trim($note ?? '');
		$this->note = $note !== '' ? $note : null;
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
