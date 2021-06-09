<?php

declare(strict_types=1);

namespace Baraja\Reservation\Endpoint;


use Baraja\Doctrine\EntityManager;
use Baraja\Reservation\Calendar;
use Baraja\Reservation\Entity\Reservation;
use Baraja\StructuredApi\BaseEndpoint;

final class ReservationEndpoint extends BaseEndpoint
{
	public function __construct(
		private EntityManager $entityManager,
		private Calendar $calendar,
	) {
	}


	public function actionDefault(int $page = 1, int $limit = 64): void
	{
		/** @var Reservation[] $reservations */
		$reservations = $this->entityManager->getRepository(Reservation::class)
			->createQueryBuilder('reservation')
			->orderBy('reservation.createDate', 'DESC')
			->setMaxResults($limit)
			->setFirstResult(($page - 1) * $limit)
			->getQuery()
			->getResult();

		$items = [];
		foreach ($reservations as $reservation) {
			$items[] = [
				'id' => $reservation->getId(),
				'number' => $reservation->getIdentifier(),
				'customer' => [
					'firstName' => $reservation->getFirstName(),
					'lastName' => $reservation->getLastName(),
					'email' => $reservation->getEmail(),
					'phone' => $reservation->getPhone(),
				],
				'price' => $reservation->getPrice(),
				'status' => $reservation->getStatus(),
				'from' => $reservation->getFrom()
					->format('d. m. Y'),
				'to' => $reservation->getTo()
					->format('d. m. Y'),
				'createDate' => $reservation->getCreateDate(),
			];
		}

		$this->sendJson(
			[
				'count' => 10,
				'items' => $items,
				'paginator' => [],
			]
		);
	}


	public function actionOverview(int $id): void
	{
		$reservation = $this->getReservation($id);

		$dates = [];
		foreach ($reservation->getDates() as $date) {
			$dates[] = [
				'id' => $date->getId(),
				'date' => $date->getDate(),
				'season' => $date->getSeason()?->getName(),
			];
		}

		$otherReservationsByCustomer = [];
		foreach ($this->getReservationsByCustomer($reservation->getEmail(), $id) as $reservationItem) {
			$otherReservationsByCustomer[] = [
				'id' => $reservationItem->getId(),
				'number' => $reservationItem->getIdentifier(),
				'price' => $reservationItem->getPrice(),
				'status' => $reservationItem->getStatus(),
			];
		}

		$this->sendJson(
			[
				'id' => $reservation->getId(),
				'number' => $reservation->getIdentifier(),
				'customer' => [
					'firstName' => $reservation->getFirstName(),
					'lastName' => $reservation->getLastName(),
					'email' => $reservation->getEmail(),
					'phone' => $reservation->getPhone(),
					'avatarUrl' => 'https://cdn.baraja.cz/avatar/' . md5($reservation->getEmail()) . '.png',
				],
				'price' => $reservation->getPrice(),
				'status' => $reservation->getStatus(),
				'from' => $reservation->getFrom()
					->format('d. m. Y'),
				'to' => $reservation->getTo()
					->format('d. m. Y'),
				'createDate' => $reservation->getCreateDate(),
				'dates' => $dates,
				'otherReservationsByCustomer' => $otherReservationsByCustomer,
			]
		);
	}


	public function postUpdateInterval(int $id, \DateTime $from, \DateTime $to): void
	{
		$reservation = $this->getReservation($id);
		$days = $this->calendar->getByInterval($from, $to);
		foreach ($days as $day) {
			$dayReservation = $day->getReservation();
			if ($dayReservation !== null && $dayReservation->getId() !== $id) {
				$this->sendError(
					'Day "' . $day->getDate() . '" can not be used, '
					. 'because contain reservation "' . $dayReservation->getNumber() . '".',
				);
			}
			$day->setReservation($reservation);
		}

		$this->entityManager->flush();
		$this->flashMessage('Reservation interval has been updated.', self::FLASH_MESSAGE_SUCCESS);
		$this->sendOk();
	}


	public function postRemove(int $id): void
	{
		$reservation = $this->getReservation($id);
		foreach ($reservation->getDates() as $date) {
			$date->setReservation(null);
		}
		$this->entityManager->remove($reservation);
		$this->entityManager->flush();

		$this->flashMessage('Reservation has been removed.', self::FLASH_MESSAGE_SUCCESS);
		$this->sendOk();
	}


	private function getReservation(int $id): Reservation
	{
		return $this->entityManager->getRepository(Reservation::class)
			->createQueryBuilder('r')
			->select('r, date')
			->leftJoin('r.dates', 'date')
			->where('r.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->getSingleResult();
	}


	/**
	 * @return Reservation[]
	 */
	private function getReservationsByCustomer(string $email, ?int $ignoreReservationId = null): array
	{
		$selection = $this->entityManager->getRepository(Reservation::class)
			->createQueryBuilder('r')
			->select('r, date')
			->leftJoin('r.dates', 'date')
			->where('r.email = :email')
			->setParameter('email', $email);

		if ($ignoreReservationId !== null) {
			$selection->andWhere('r.id != :ignoredId')
				->setParameter('ignoredId', $ignoreReservationId);
		}

		return $selection
			->getQuery()
			->getResult();
	}
}
