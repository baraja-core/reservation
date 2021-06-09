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


	public function actionSeasonSetActive(int $id): void
	{
		try {
			/** @var Season $season */
			$season = $this->entityManager->getRepository(Season::class)
				->createQueryBuilder('s')
				->where('s.id = :id')
				->setParameter('id', $id)
				->getQuery()
				->getSingleResult();
		} catch (NoResultException | NonUniqueResultException) {
			$this->sendError('Season "' . $id . '" does not exist.');
		}

		$season->setActive(!$season->isActive());
		$this->entityManager->flush();
		$this->sendOk();
	}
}
