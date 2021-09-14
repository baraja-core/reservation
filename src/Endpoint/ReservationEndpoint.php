<?php

declare(strict_types=1);

namespace Baraja\Reservation\Endpoint;


use Baraja\Doctrine\EntityManager;
use Baraja\DynamicConfiguration\Configuration;
use Baraja\Reservation\Calendar;
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

		$products = [];
		foreach ($reservation->getProductItems() as $productItem) {
			$products[] = [
				'id' => $productItem->getId(),
				'productId' => $productItem->getProduct()->getId(),
				'name' => $productItem->getProduct()->getName(),
				'quantity' => $productItem->getQuantity(),
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
				'fromDate' => $reservation->getFrom(),
				'to' => $reservation->getTo()
					->format('d. m. Y'),
				'toDate' => $reservation->getTo(),
				'createDate' => $reservation->getCreateDate(),
				'dates' => $dates,
				'note' => $reservation->getNote(),
				'otherReservationsByCustomer' => $otherReservationsByCustomer,
				'products' => $products,
			]
		);
	}


	public function postUpdateInterval(int $id, \DateTime $from, \DateTime $to, int $productId): void
	{
		$reservation = $this->getReservation($id);
		foreach ($reservation->getDates() as $date) {
			$date->setReservation(null);
		}
		foreach ($this->calendar->getByInterval($from, $to, $this->getProduct($productId)) as $day) {
			$dayReservation = $day->getReservation();
			if ($dayReservation !== null && $dayReservation->getId() !== $id) {
				$this->sendError(
					'Day "' . $day->getDate() . '" can not be used, '
					. 'because contain reservation "' . $dayReservation->getNumber() . '".',
				);
			}
			$day->setReservation($reservation);
		}
		$reservation->setFrom($from);
		$reservation->setTo($to);

		$this->entityManager->flush();
		$this->flashMessage('Reservation interval has been updated.', self::FLASH_MESSAGE_SUCCESS);
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

		$this->flashMessage('Reservation has been removed.', self::FLASH_MESSAGE_SUCCESS);
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
		$this->flashMessage('Product item has been removed.', self::FLASH_MESSAGE_SUCCESS);
		$this->sendOk();
	}


	public function actionNotificationConfiguration(): void
	{
		$configuration = $this->configuration->getSection(ReservationManager::CONFIGURATION_NAMESPACE);

		$this->sendJson([
			'to' => $configuration->get(ReservationManager::NOTIFICATION_TO) ?? '',
			'copy' => $configuration->get(ReservationManager::NOTIFICATION_COPY) ?? '',
			'subject' => $configuration->get(ReservationManager::NOTIFICATION_SUBJECT) ?? '',
		]);
	}


	public function postNotificationConfiguration(
		string $to,
		string $copy,
		string $subject,
	): void {
		$configuration = $this->configuration->getSection(ReservationManager::CONFIGURATION_NAMESPACE);
		$configuration->save(ReservationManager::NOTIFICATION_TO, trim($to ?? '') ?: null);
		$configuration->save(ReservationManager::NOTIFICATION_COPY, trim($copy ?? '') ?: null);
		$configuration->save(ReservationManager::NOTIFICATION_SUBJECT, trim($subject ?? '') ?: null);
		$this->flashMessage('Configuration has been saved.', self::FLASH_MESSAGE_SUCCESS);
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
			$this->sendError('Product "' . $id . '" does not exist.');
		}
	}
}
