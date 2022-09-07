<?php

declare(strict_types=1);

namespace Baraja\Reservation;


use Baraja\Doctrine\EntityManager;
use Baraja\DynamicConfiguration\Configuration;
use Baraja\Emailer\EmailerAccessor;
use Baraja\Reservation\Entity\Date;
use Baraja\Reservation\Entity\Reservation;
use Baraja\Reservation\Entity\ReservationProductItem;
use Baraja\Shop\Product\Entity\Product;
use Nette\Mail\Message;
use Nette\Utils\Validators;
use Tracy\Debugger;

final class ReservationManager
{
	public const
		ConfigurationNamespace = 'reservation',
		NotificationTo = 'notification-to',
		NotificationCopy = 'notification-copy',
		NotificationSubject = 'notification-subject';


	public function __construct(
		private EntityManager $entityManager,
		private Calendar $calendar,
		private EmailerAccessor $emailerAccessor,
		private Configuration $configuration,
	) {
	}


	/**
	 * @param array<int, Product> $products
	 */
	public function createReservation(
		\DateTime $from,
		\DateTime $to,
		?string $firstName,
		?string $lastName,
		string $email,
		?string $phone = null,
		array $products = [],
	): Reservation {
		if ($products === []) {
			throw new \InvalidArgumentException('Product array can not be empty.');
		}
		$price = 0;
		$reservedDays = [];
		foreach ($products as $product) {
			$days = $this->calendar->getByInterval($from, $to, $product);
			$minDays = $this->countMinimalDays($from, $to, $product);
			if (count($days) < $minDays) {
				throw new \InvalidArgumentException(
					'Minimální možná rezervace je omezena. Vyberte aspoň tento počet dnů: ' . $minDays,
				);
			}
			foreach ($days as $day) {
				if ($day->isEnable() === false) { // check duplicity
					throw new \InvalidArgumentException(
						'Tento interval nelze použít, protože existuje ve vašem intervalu jiná rezervace, '
						. 'která blokuje objednávku. Prosím, vyberte jiný volný interval.',
					);
				}
				$season = $day->getSeason();
				if ($season !== null && $season->isActive() === false) {
					throw new \InvalidArgumentException(
						'Rezervace pro den "' . $day->getDate() . '" není možná, '
						. 'protože patří do vyprodané sezóny. '
						. 'Zkuste vybrat jiný volný termín, nebo nás kontaktujte.',
					);
				}
			}
			$price += $this->countDaysPrice($from, $to, $product);
			$reservedDays[] = $days;
		}

		$reservation = new Reservation(
			from: $from,
			to: $to,
			price: $price,
			firstName: $firstName,
			lastName: $lastName,
			email: $email,
		);
		$reservation->setPhone($phone);
		$this->entityManager->persist($reservation);

		foreach (array_merge([], ...$reservedDays) as $day) {
			$day->setReservation($reservation);
		}
		foreach ($products as $product) {
			$this->entityManager->persist(
				$reservation->addItem(
					new ReservationProductItem($reservation, $product),
				),
			);
		}

		$this->entityManager->flush();

		try {
			// Notification can fail
			$this->sendNotification($reservation);
		} catch (\Throwable $e) {
			// Silence is golden.
			if (class_exists(Debugger::class)) {
				Debugger::log($e, 'critical');
			}
		}

		return $reservation;
	}


	public function countDaysPrice(\DateTime $from, \DateTime $to, Product $product): int
	{
		return array_sum(
			array_map(
				static fn(Date $date): int => $date->getSeason()?->getPrice() ?? 0,
				$this->calendar->getByInterval($from, $to, $product),
			),
		);
	}


	public function countMinimalDays(\DateTime $from, \DateTime $to, Product $product): int
	{
		$return = null;
		foreach ($this->calendar->getByInterval($from, $to, $product) as $regularDate) {
			$season = $regularDate->getSeason();
			$days = $season !== null ? $season->getMinimalDays() : 0;
			if ($return === null || $days > $return) {
				$return = $days;
			}
		}
		if ($return !== null) { // Check if interval is limited
			$interval = date_interval_create_from_date_string(sprintf('%d days', $return));
			if ($interval === false) {
				throw new \LogicException(sprintf('Interval "%s days" is not valid.', $return));
			}
			$maxAvailableArea = 0;
			$currentAvailableArea = 0;
			$dates = $this->calendar->getByInterval(
				from: date_add($from, $interval),
				to: date_add($to, $interval),
				product: $product,
			);
			foreach ($dates as $date) {
				$currentAvailableArea++;
				if ($date->isEnable() === true) { // is date available?
					if ($currentAvailableArea > $maxAvailableArea) {
						$maxAvailableArea = $currentAvailableArea;
					}
				} else {
					$currentAvailableArea = 0;
				}
			}
			if ($maxAvailableArea >= $return) { // User can change requested area (valid)
				return $return;
			}
			return $maxAvailableArea;
		}

		return 1;
	}


	private function sendNotification(Reservation $reservation): void
	{
		$configuration = $this->configuration->getSection(self::ConfigurationNamespace);

		$to = $configuration->get(self::NotificationTo);
		$copy = $configuration->get(self::NotificationCopy);

		$message = new Message;
		$message->setSubject(sprintf(
			'%s | %s',
			$configuration->get(self::NotificationSubject) ?? 'New reservation',
			$reservation->getNumber(),
		));
		$message->setHtmlBody($this->processNotificationMessage($reservation));

		if ($to !== null && Validators::isEmail($to)) {
			$message->addTo($to);
		} else {
			// Silence error: Notification can not be sent.
			return;
		}
		if ($copy !== null && Validators::isEmail($copy)) {
			$message->addCc($copy);
		}

		$this->emailerAccessor->get()->send($message);
	}


	private function processNotificationMessage(Reservation $reservation): string
	{
		$dates = [];
		foreach ($reservation->getDates() as $date) {
			$dates[] = $date->getDate('d. m. Y');
		}

		return sprintf(
			'<h1>New reservation %i</h1><p>'
			. 'From: %s, To: %s<br>'
			. 'Price: %s<br>'
			. 'Name: %s<br>'
			. 'E-mail: %s<br>'
			. 'Phone: %s<br>'
			. 'Note: %s<br>'
			. '</p>'
			.'<p>Real reserved dates: %s</p>'
			.'<p>Created date: %s</p>',
			$reservation->getIdentifier(),
			$reservation->getFrom()->format('d. m. Y'),
			$reservation->getTo()->format('d. m. Y'),
			(string) $reservation->getPrice(),
			$reservation->getName() ?? '???',
			$reservation->getEmail(),
			$reservation->getPhone() ?? '-',
			$reservation->getNote() ?? '-',
			implode(', ', $dates),
			$reservation->getCreateDate()->format('d. m. Y, H:i:s'),
		);
	}
}
