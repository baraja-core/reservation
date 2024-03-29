<?php

declare(strict_types=1);

namespace Baraja\Reservation\Endpoint;


use Baraja\Doctrine\EntityManager;
use Baraja\DynamicConfiguration\Configuration;
use Baraja\Reservation\Calendar;
use Baraja\Reservation\Endpoint\DTO\ReservationDetailResponse;
use Baraja\Reservation\Endpoint\DTO\ReservationOverviewItem;
use Baraja\Reservation\Endpoint\DTO\ReservationOverviewItemCustomer;
use Baraja\Reservation\Endpoint\DTO\ReservationOverviewResponse;
use Baraja\Reservation\Entity\Date;
use Baraja\Reservation\Entity\Reservation;
use Baraja\Reservation\ReservationManager;
use Baraja\Shop\Product\Entity\Product;
use Baraja\StructuredApi\BaseEndpoint;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class ReservationEndpoint extends BaseEndpoint
{
	public function __construct(
		private EntityManager $entityManager,
		private Calendar $calendar,
		private Configuration $configuration,
	) {
	}


	public function actionDefault(int $page = 1, int $limit = 64): ReservationOverviewResponse
	{
		/** @var Reservation[] $reservations */
		$reservations = $this->entityManager->getRepository(Reservation::class)
			->createQueryBuilder('reservation')
			->orderBy('reservation.createDate', 'DESC')
			->setMaxResults($limit)
			->setFirstResult(($page - 1) * $limit)
			->getQuery()
			->getResult();

		$reservationIds = array_map(static fn(Reservation $reservation): int => $reservation->getId(), $reservations);
		if ($reservationIds !== []) {
			$this->entityManager->getRepository(Date::class)
				->createQueryBuilder('date')
				->where('date.reservation IN (:reservationIds)')
				->setParameter('reservationIds', $reservationIds)
				->getQuery()
				->getResult();
		}

		$items = [];
		foreach ($reservations as $reservation) {
			$items[] = new ReservationOverviewItem(
				id: $reservation->getId(),
				number: $reservation->getIdentifier(),
				customer: new ReservationOverviewItemCustomer(
					firstName: $reservation->getFirstName(),
					lastName: $reservation->getLastName(),
					email: $reservation->getEmail(),
					phone: $reservation->getPhone(),
				),
				price: $reservation->getPrice(),
				status: $reservation->getStatus(),
				from: $reservation->getFrom()->format('d. m. Y'),
				to: $reservation->getTo()->format('d. m. Y'),
				createDate: $reservation->getCreateDate(),
			);
		}

		$count = (int) $this->entityManager->getRepository(Reservation::class)
			->createQueryBuilder('reservation')
			->select('COUNT(reservation.id)')
			->getQuery()
			->getSingleScalarResult();

		return new ReservationOverviewResponse(
			count: $count,
			items: $items,
			paginator: [],
		);
	}


	public function actionOverview(int $id): ReservationDetailResponse
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

		$products = [];
		foreach ($reservation->getProductItems() as $productItem) {
			$products[] = [
				'id' => $productItem->getId(),
				'productId' => $productItem->getProduct()->getId(),
				'name' => $productItem->getProduct()->getLabel(),
				'quantity' => $productItem->getQuantity(),
			];
		}

		return new ReservationDetailResponse(
			id: $reservation->getId(),
			number: $reservation->getIdentifier(),
			customer: new ReservationOverviewItemCustomer(
				firstName: $reservation->getFirstName(),
				lastName: $reservation->getLastName(),
				email: $reservation->getEmail(),
				phone: $reservation->getPhone(),
				avatarUrl: sprintf('https://cdn.baraja.cz/avatar/%s.png', md5($reservation->getEmail())),
			),
			price: $reservation->getPrice(),
			status: $reservation->getStatus(),
			from: $reservation->getFrom()->format('d. m. Y'),
			fromDate: $reservation->getFrom(),
			to: $reservation->getTo()->format('d. m. Y'),
			toDate: $reservation->getTo(),
			createDate: $reservation->getCreateDate(),
			dates: $dates,
			note: $reservation->getNote(),
			otherReservationsByCustomer: $otherReservationsByCustomer,
			products: $products,
		);
	}


	public function postUpdateInterval(int $id, \DateTime $from, \DateTime $to, int $productId): void
	{
		$reservation = $this->getReservation($id);
		foreach ($reservation->getDates() as $date) {
			$date->setReservation(null);
		}
		$reservation->getDates()->clear();
		foreach ($this->calendar->getByInterval($from, $to, $this->getProduct($productId)) as $day) {
			$dayReservation = $day->getReservation();
			if ($dayReservation !== null && $dayReservation->getId() !== $id) {
				$this->sendError(sprintf(
					'Day "%s" can not be used, because contain reservation "%s".',
					$day->getDate(),
					$dayReservation->getNumber(),
				));
			}
			$day->setReservation($reservation);
			$reservation->addDate($day);
		}
		$reservation->setNote(
			trim(sprintf(
				"%s\nInterval changed: [from: %s, to: %s, current date: %s]",
				(string) $reservation->getNote(),
				$reservation->getFrom()->format('d. m. Y'),
				$reservation->getTo()->format('d. m. Y'),
				date('d. m. Y, H:i:s'),
			)),
		);

		$this->entityManager->flush();
		$this->flashMessage('Reservation interval has been updated.', self::FlashMessageSuccess);
		$this->sendOk();
	}


	public function actionMinimalDays(\DateTime $from, \DateTime $to, int $productId): void
	{
		$return = null;
		foreach ($this->calendar->getByInterval($from, $to, $this->getProduct($productId)) as $date) {
			$season = $date->getSeason();
			$days = $season !== null ? $season->getMinimalDays() : 0;
			if ($return === null || $days > $return) {
				$return = $days;
			}
		}

		$this->sendJson([
			'from' => $from,
			'to' => $to,
			'minimalDays' => $return ?? 1,
		]);
	}


	public function postRemove(int $id): void
	{
		$reservation = $this->getReservation($id);
		foreach ($reservation->getDates() as $date) {
			$date->setReservation(null);
		}
		foreach ($reservation->getProductItems() as $productItem) {
			$this->entityManager->remove($productItem);
		}
		$this->entityManager->remove($reservation);
		$this->entityManager->flush();

		$this->flashMessage('Reservation has been removed.', self::FlashMessageSuccess);
		$this->sendOk();
	}


	public function postRemoveProductItem(int $id, int $productItemId): void
	{
		$reservation = $this->getReservation($id);
		foreach ($reservation->getProductItems() as $productItem) {
			if ($productItem->getId() === $productItemId) {
				$this->entityManager->remove($productItem);
			}
		}
		$this->entityManager->flush();
		$this->flashMessage('Product item has been removed.', self::FlashMessageSuccess);
		$this->sendOk();
	}


	public function actionNotificationConfiguration(): void
	{
		$configuration = $this->configuration->getSection(ReservationManager::ConfigurationNamespace);

		$this->sendJson([
			'to' => $configuration->get(ReservationManager::NotificationTo) ?? '',
			'copy' => $configuration->get(ReservationManager::NotificationCopy) ?? '',
			'subject' => $configuration->get(ReservationManager::NotificationSubject) ?? '',
		]);
	}


	public function postNotificationConfiguration(
		string $to,
		string $copy,
		string $subject,
	): void {
		$to = trim($to);
		$copy = trim($copy);
		$subject = trim($subject);

		$configuration = $this->configuration->getSection(ReservationManager::ConfigurationNamespace);
		$configuration->save(ReservationManager::NotificationTo, $to !== '' ? $to : null);
		$configuration->save(ReservationManager::NotificationCopy, $copy !== '' ? $copy : null);
		$configuration->save(ReservationManager::NotificationSubject, $subject !== '' ? $subject : null);
		$this->flashMessage('Configuration has been saved.', self::FlashMessageSuccess);
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
			->setParameter('email', $email)
			->orderBy('r.createDate', 'DESC');

		if ($ignoreReservationId !== null) {
			$selection->andWhere('r.id != :ignoredId')
				->setParameter('ignoredId', $ignoreReservationId);
		}

		return $selection
			->getQuery()
			->getResult();
	}


	private function getProduct(int $id): Product
	{
		try {
			return $this->entityManager->getRepository(Product::class)
				->createQueryBuilder('p')
				->where('p.id = :id')
				->setParameter('id', $id)
				->getQuery()
				->getSingleResult();
		} catch (NoResultException | NonUniqueResultException) {
			$this->sendError(sprintf('Product "%s" does not exist.', $id));
		}
	}
}
