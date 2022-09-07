<?php

declare(strict_types=1);

namespace Baraja\Reservation\Endpoint;


use Baraja\Doctrine\EntityManager;
use Baraja\Reservation\Calendar;
use Baraja\Reservation\Entity\Season;
use Baraja\Shop\Product\Entity\Product;
use Baraja\StructuredApi\BaseEndpoint;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class ReservationSeasonEndpoint extends BaseEndpoint
{
	public function __construct(
		private Calendar $calendar,
		private EntityManager $entityManager,
	) {
	}


	public function actionDetail(int $id, int $productId): void
	{
		$season = $this->getSeason($id, $productId);
		$this->sendJson([
			'id' => $id,
			'name' => $season->getName(),
			'description' => $season->getDescription(),
			'from' => $season->getFromDate(),
			'to' => $season->getToDate(),
			'price' => $season->getPrice(),
			'minimalDays' => $season->getMinimalDays(),
			'active' => $season->isActive(),
			'flush' => false,
		]);
	}


	public function postSave(
		int $id,
		int $productId,
		string $name,
		?string $description,
		\DateTime $from,
		\DateTime $to,
		int $price,
		int $minimalDays,
		bool $active,
		bool $flush = false,
	): void {
		$season = $this->getSeason($id, $productId);
		$season->setName($name);
		$season->setDescription($description);
		$season->setPrice($price);
		$season->setMinimalDays($minimalDays);
		$season->setActive($active);
		foreach ($season->getDates() as $date) {
			$date->setSeason(null);
		}

		$product = $season->getProduct();
		assert($product !== null);
		$days = $this->calendar->getByInterval($from, $to, $product);
		if ($days === []) {
			$this->sendError('Date interval can not be empty.');
		}
		foreach ($days as $date) {
			if ($flush === false) { // overwrite selected days by given season
				$dateSeason = $date->getSeason();
				if ($dateSeason !== null && $dateSeason->getId() !== $id) {
					$this->flashMessage(
						'This interval can not be used: '
						. 'Date "' . $date->getDate() . '" is blocked, because season '
						. '"' . $dateSeason->getName() . '" already exist there.',
						self::FlashMessageError,
					);
					$this->sendError('Season date is blocked by another season.');
				}
			}
			$date->setSeason($season);
		}

		$this->entityManager->flush();
		$this->flashMessage('Season has been updated.', self::FlashMessageSuccess);
		$this->sendOk();
	}


	public function postCreateSeason(
		string $name,
		?string $description,
		\DateTime $from,
		\DateTime $to,
		int $price,
		int $minimalDays,
		int $productId,
	): void {
		$product = $this->getProduct($productId);
		$days = $this->calendar->getByInterval($from, $to, $product);
		if ($days === []) {
			$this->sendError('Date interval can not be empty.');
		}
		foreach ($days as $date) {
			$dateSeason = $date->getSeason();
			if ($dateSeason !== null) {
				$this->sendError(
					'Season "' . $dateSeason->getName() . '" for date "' . $date->getDate() . '" already exist.',
				);
			}
		}

		$season = new Season($price, $product);
		$season->setName($name);
		$season->setDescription($description);
		$season->setMinimalDays($minimalDays);
		$this->entityManager->persist($season);

		foreach ($days as $date) {
			$date->setSeason($season);
		}

		$this->entityManager->flush();
		$this->flashMessage('Season has been created.', self::FlashMessageSuccess);
		$this->sendOk();
	}


	public function postRemove(int $id): void
	{
		$season = $this->getSeason($id);
		foreach ($season->getDates() as $date) {
			$reservation = $date->getReservation();
			if ($reservation !== null) { // is reservation?
				$this->sendError(
					'Season "' . $season->getName() . '" (' . $id . ') can not be removed, '
					. 'because contain a reservation "' . $reservation->getNumber() . '".',
				);
			}
		}
		$this->entityManager->remove($season);
		$this->entityManager->flush();
		$this->flashMessage('Season has been removed.', self::FlashMessageSuccess);
		$this->sendOk();
	}


	public function actionSeasonSetActive(int $id): void
	{
		$season = $this->getSeason($id);
		$season->setActive(!$season->isActive());
		$this->entityManager->flush();
		$this->flashMessage(
			sprintf('Season has been marked as %s.', $season->isActive() ? 'active' : 'hidden'),
			self::FlashMessageSuccess,
		);
		$this->sendOk();
	}


	private function getSeason(int $id, ?int $productId = null): Season
	{
		$selection = $this->entityManager->getRepository(Season::class)
			->createQueryBuilder('s')
			->where('s.id = :id')
			->setParameter('id', $id);

		if ($productId !== null) {
			$selection->andWhere('s.product = :productId')
				->setParameter('productId', $productId);
		}

		try {
			return $selection->getQuery()->getSingleResult();
		} catch (NoResultException | NonUniqueResultException) {
			$this->sendError(sprintf('Season "%d" does not exist.', $id));
		}
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
			$this->sendError(sprintf('Season "%d" does not exist.', $id));
		}
	}
}
