<?php

declare(strict_types=1);

namespace Baraja\Reservation;


use Baraja\Doctrine\EntityManager;
use Baraja\DynamicConfiguration\Configuration;
use Baraja\Emailer\EmailerAccessor;
use Baraja\Reservation\Entity\Reservation;
use Nette\Mail\Message;
use Nette\Utils\Validators;
use Tracy\Debugger;
use Tracy\ILogger;

final class ReservationManager
{
	public const
		CONFIGURATION_NAMESPACE = 'reservation',
		NOTIFICATION_TO = 'notification-to',
		NOTIFICATION_COPY = 'notification-copy',
		NOTIFICATION_SUBJECT = 'notification-subject';

	public function __construct(
		private EntityManager $entityManager,
		private Calendar $calendar,
		private EmailerAccessor $emailerAccessor,
		private Configuration $configuration,
	) {
	}


	public function createReservation(
		\DateTime $from,
		\DateTime $to,
		?string $firstName,
		?string $lastName,
		string $email,
		?string $phone = null
	): Reservation {
		$days = $this->calendar->getByInterval($from, $to);
		$minDays = $this->countMinimalDays($from, $to);
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

		$reservation = new Reservation(
			$from,
			$to,
			$this->countDaysPrice($from, $to),
			$firstName,
			$lastName,
			$email
		);
		$reservation->setPhone($phone);
		$this->entityManager->persist($reservation);

		foreach ($days as $day) {
			$day->setReservation($reservation);
		}

		$this->entityManager->flush();

		try {
			// Notification can fail
			$this->sendNotification($reservation);
		} catch (\Throwable $e) {
			// Silence is golden.
			if (class_exists(Debugger::class)) {
				Debugger::log($e, ILogger::CRITICAL);
			}
		}

		return $reservation;
	}


	public function countDaysPrice(\DateTime $from, \DateTime $to): int
	{
		$sum = 0;
		foreach ($this->calendar->getByInterval($from, $to) as $date) {
			$season = $date->getSeason();
			if ($season !== null) {
				$sum += $season->getPrice();
			}
		}

		return $sum;
	}


	public function countMinimalDays(\DateTime $from, \DateTime $to): int
	{
		$return = null;
		foreach ($this->calendar->getByInterval($from, $to) as $date) {
			$season = $date->getSeason();
			$days = $season !== null ? $season->getMinimalDays() : 0;
			if ($return === null || $days > $return) {
				$return = $days;
			}
		}

		return $return ?? 1;
	}


	private function sendNotification(Reservation $reservation): void
	{
		$configuration = $this->configuration->getSection(self::CONFIGURATION_NAMESPACE);

		$to = $configuration->get(self::NOTIFICATION_TO);
		$copy = $configuration->get(self::NOTIFICATION_COPY);

		$message = (new Message)
			->setSubject(
				($configuration->get(self::NOTIFICATION_SUBJECT) ?: 'New reservation')
				. ' | ' . $reservation->getNumber()
			)
			->setBody('New reservation.');

		if ($to !== null && Validators::isEmail($to)) {
			$message->addTo($to);
		} else {
			// Silence error: Notification can not be sent.
			return;
		}
		if ($copy !== null && Validators::isEmail($copy)) {
			$message->addCc($copy);
		}

		$this->emailerAccessor->get()
			->send($message);
	}
}
