<?php

declare(strict_types=1);

namespace Baraja\Reservation\Endpoint;


use Baraja\Doctrine\EntityManager;
use Baraja\Reservation\Calendar;
use Baraja\Reservation\Entity\Season;
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


	public function actionDetail(int $id): void
	{
		$season = $this->getSeason($id);
		$this->sendJson([
			'id' => $id,
			'name' => $season->getName(),
			'description' => $season->getDescription(),
			'from' => $season->getFromDate(),
			'to' => $season->getToDate(),
			'price' => $season->getPrice(),
			'minimalDays' => $season->getMinimalDays(),
			'active' => $season->isActive(),
		]);
	}


	public function postSave(
		int $id,
		string $name,
		?string $description,
		\DateTime $from,
		\DateTime $to,
		int $price,
		int $minimalDays,
		bool $active,
		bool $flush = false,
	): void {
		$season = $this->getSeason($id);
		$season->setName($name);
		$season->setDescription($description);
		$season->setPrice($price);
		$season->setMinimalDays($minimalDays);
		$season->setActive($active);

		$days = $this->calendar->getByInterval($from, $to);
		if ($days === []) {
			$this->sendError('Date interval can not be empty.');
		}
		foreach ($days as $date) {
			if ($flush === false) { // overwrite selected days by given season
				$dateSeason = $date->getSeason();
				if ($dateSeason !== null && $dateSeason->getId() !== $id) {
					$this->sendError(
						'Season "' . $dateSeason->getName() . '" for date "' . $date->getDate() . '" already exist.',
					);
				}
			}
			$date->setSeason($season);
		}

		$this->entityManager->flush();
		$this->flashMessage('Season has been updated.', self::FLASH_MESSAGE_SUCCESS);
		$this->sendOk();
	}


	public function postCreateSeason(
		string $name,
		?string $description,
		\DateTime $from,
		\DateTime $to,
		int $price,
		int $minimalDays,
	): void {
		$days = $this->calendar->getByInterval($from, $to);
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

		$season = new Season($price);
		$season->setName($name);
		$season->setDescription($description);
		$season->setMinimalDays($minimalDays);
		$this->entityManager->persist($season);

		foreach ($days as $date) {
			$date->setSeason($season);
		}

		$this->entityManager->flush();
		$this->flashMessage('Season has been created.', self::FLASH_MESSAGE_SUCCESS);
		$this->sendOk();
	}


	public function postRemove(int $id): void
	{
		$season = $this->getSeason($id);
		foreach ($season->getDates() as $date) {
			if ($date->isReservation()) {
				$reservation = $date->getReservation();
				$this->sendError(
					'Season "' .  $season->getName() . '" (' . $id. ') can not be removed, '
					. 'because contain a reservation "' . $reservation->getNumber() . '".'
				);
			}
		}
		$this->entityManager->remove($season);
		$this->entityManager->flush();
		$this->flashMessage('Season has been removed.', self::FLASH_MESSAGE_SUCCESS);
		$this->sendOk();
	}


	public function actionSeasonSetActive(int $id): void
	{
		$season = $this->getSeason($id);
		$season->setActive(!$season->isActive());
		$this->entityManager->flush();
		$this->flashMessage(
			'Season has been marked as ' . ($season->isActive() ? 'active' : 'hidden') . '.',
			self::FLASH_MESSAGE_SUCCESS,
		);
		$this->sendOk();
	}


	private function getSeason(int $id): Season
	{
		try {
			return $this->entityManager->getRepository(Season::class)
				->createQueryBuilder('s')
				->where('s.id = :id')
				->setParameter('id', $id)
				->getQuery()
				->getSingleResult();
		} catch (NoResultException | NonUniqueResultException) {
			$this->sendError('Season "' . $id . '" does not exist.');
		}
	}
}
