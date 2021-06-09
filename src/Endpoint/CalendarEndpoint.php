<?php

declare(strict_types=1);

namespace Baraja\Reservation\Endpoint;


use Baraja\Doctrine\EntityManager;
use Baraja\Reservation\Calendar;
use Baraja\Reservation\Entity\Date;
use Baraja\Reservation\Entity\Reservation;
use Baraja\Reservation\Entity\Season;
use Baraja\StructuredApi\BaseEndpoint;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Nette\Utils\DateTime;

final class CalendarEndpoint extends BaseEndpoint
{
	public function __construct(
		private EntityManager $entityManager,
		private Calendar $calendar,
	) {
	}


	public function actionDefault(?int $year = null): void
	{
		$year ??= (int) date('Y');
		$calendarDays = $this->calendar->getByIntervalGroupByMonths($year . '-01-01', $year . '-12-31');

		/** @var Season[] $seasons */
		$seasons = $this->entityManager->getRepository(Season::class)
			->createQueryBuilder('season')
			->select('season, day')
			->leftJoin('season.dates', 'day')
			->where('day.date LIKE :datePattern')
			->setParameter('datePattern', $year . '-%')
			->orderBy('day.date', 'ASC')
			->getQuery()
			->getResult();

		$this->entityManager->getRepository(Reservation::class)
			->createQueryBuilder('reservation')
			->select('reservation, day')
			->leftJoin('reservation.dates', 'day')
			->where('day.date LIKE :datePattern')
			->setParameter('datePattern', $year . '-%')
			->orderBy('day.date', 'DESC')
			->getQuery()
			->getResult();

		$calendar = [];
		foreach ($calendarDays as $monthKey => $dates) {
			$firstDay = (int) DateTime::from($monthKey . '-01')
				->format('N');
			$firstDate = DateTime::from($dates[0]->getDate());
			$firstWeek = (int) $firstDate->format('W');
			if ($firstWeek > 5 && ((int) $firstDate->format('m')) === 1) {
				$firstWeek = 0;
			}
			for ($i = 1; $i < $firstDay; $i++) {
				$calendar[$monthKey][$firstWeek][] = [
					'day' => null,
					'date' => null,
					'enable' => false,
					'reservation' => false,
					'season ' => false,
				];
			}
			foreach ($dates as $dateEntity) {
				$date = DateTime::from($dateEntity->getDate());
				$week = (int) $date->format('W');
				if ($week > 5 && ((int) $date->format('m')) === 1) {
					$week = 0;
				}
				$calendar[$monthKey][$week][] = [
					'day' => (int) $date->format('d'),
					'date' => $dateEntity->getDate(),
					'enable' => $dateEntity->isEnable(),
					'reservation' => $dateEntity->isReservation(),
					'season' => $dateEntity->isSeason(),
				];
			}
		}

		$seasonList = [];
		foreach ($seasons as $season) {
			$seasonList[] = [
				'id' => $season->getId(),
				'name' => $season->getName(),
				'price' => $season->getPrice(),
				'from' => $season->getFromDate()
					->format('d. m. Y'),
				'to' => $season->getToDate()
					->format('d. m. Y'),
				'minimalDays' => $season->getMinimalDays(),
				'active' => $season->isActive(),
			];
		}

		$this->sendJson(
			[
				'year' => $year,
				'calendar' => $calendar,
				'seasons' => $seasonList,
			]
		);
	}


	public function actionDetail(string $date): void
	{
		/** @var Date $entity */
		$entity = $this->entityManager->getRepository(Date::class)
			->createQueryBuilder('date')
			->select('date, reservation, season')
			->leftJoin('date.reservation', 'reservation')
			->leftJoin('date.season', 'season')
			->where('date.date = :date')
			->setParameter(
				'date',
				DateTime::from($date)
					->format('Y-m-d')
			)
			->getQuery()
			->getSingleResult();

		$reservation = $entity->getReservation();
		$season = $entity->getSeason();

		$this->sendJson(
			[
				'loading' => false,
				'date' => $entity->getDate(),
				'reservation' => $reservation !== null
					? [
						'id' => $reservation->getId(),
						'name' => $reservation->getName(),
						'email' => $reservation->getEmail(),
						'phone' => $reservation->getPhone(),
						'price' => $reservation->getPrice(),
					] : null,
				'season' => $season !== null
					? [
						'id' => $season->getId(),
						'name' => $season->getName(),
						'description' => $season->getDescription(),
						'price' => $season->getPrice(),
						'minimalDays' => $season->getMinimalDays(),
						'dates' => $season->getDatesFormatted(),
					] : null,
			]
		);
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
