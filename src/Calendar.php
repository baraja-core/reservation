<?php

declare(strict_types=1);

namespace Baraja\Reservation;


use Baraja\Doctrine\EntityManager;
use Baraja\Reservation\Entity\Date;
use Baraja\Shop\Product\Entity\Product;
use Nette\Utils\DateTime;

final class Calendar
{
	public function __construct(
		private EntityManager $entityManager,
	) {
	}


	/**
	 * @return array<string, Date>
	 */
	public function getCalendarMonth(int $year, int $month, Product $product): array
	{
		$daysCount = cal_days_in_month(0, $month, $year);

		$dates = [];
		for ($day = 1; $day <= $daysCount; $day++) {
			$dates[] = Date::formatDate($year, $month, $day);
		}

		return $this->getByDates($dates, $product);
	}


	public function getCountDays(\DateTime|string $from, \DateTime|string $to): int
	{
		return iterator_count($this->getDatePeriod($from, $to));
	}


	/**
	 * @return array<string, Date>
	 */
	public function getByInterval(\DateTime|string $from, \DateTime|string $to, Product $product): array
	{
		return $this->getByDates(
			dates: array_map(
				static fn(\DateTimeInterface $date): string => $date->format('Y-m-d'),
				(array) $this->getDatePeriod($from, $to),
			),
			product: $product,
		);
	}


	/**
	 * @return array<string, array<int, Date>>
	 */
	public function getByIntervalGroupByMonths(\DateTime|string $from, \DateTime|string $to, Product $product): array
	{
		$return = [];
		foreach ($this->getByInterval($from, $to, $product) as $date) {
			$return[$date->getDate('Y-m')][] = $date;
		}

		return $return;
	}


	/**
	 * @param array<int, string> $dates
	 * @return array<string, Date>
	 */
	public function getByDates(array $dates, Product $product): array
	{
		static $cache = [];
		$hash = implode(',', $dates);

		if (isset($cache[$hash]) === false) {
			$cache[$hash] = $this->entityManager->getRepository(Date::class)
				->createQueryBuilder('d')
				->where('d.date IN (:dates)')
				->andWhere('d.product = :productId')
				->setParameter('dates', $dates)
				->setParameter('productId', $product->getId())
				->orderBy('d.date', 'ASC')
				->getQuery()
				->getResult();
		}

		/** @var Date[] $dateEntities */
		$dateEntities = $cache[$hash] ?? [];

		$return = [];
		foreach ($dateEntities as $dateEntity) { // hydrate entities
			$return[$dateEntity->getDate()] = $dateEntity;
		}

		$needFlush = false;
		foreach ($dates as $date) { // fast check
			if (isset($return[$date]) === false) { // create date entity
				$dateEntity = new Date($date, $product);
				$this->entityManager->persist($dateEntity);
				$return[$date] = $dateEntity;
				$needFlush = true;
			}
		}
		if ($needFlush === true) {
			$this->entityManager->flush();
		}
		ksort($return);

		return $return;
	}


	private function getDatePeriod(\DateTime|string $from, \DateTime|string $to): \DatePeriod
	{
		$fromType = DateTime::from($from);
		$toType = DateTime::from($to);
		if ($fromType->getTimestamp() > $toType->getTimestamp()) {
			$helper = $fromType;
			$fromType = $toType;
			$toType = $helper;
		}

		/** @phpstan-ignore-next-line */
		return new \DatePeriod(
			start: DateTime::from($fromType->format('Y-m-d 00:00:00')), // @phpstan-ignore-line
			interval: new \DateInterval('P1D'), // @phpstan-ignore-line
			end: DateTime::from($toType->format('Y-m-d 23:00:00')), // @phpstan-ignore-line
		);
	}
}
