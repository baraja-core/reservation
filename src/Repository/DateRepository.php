<?php

declare(strict_types=1);

namespace Baraja\Reservation\Repository;


use Baraja\Reservation\Entity\Date;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Nette\Utils\DateTime;

final class DateRepository extends EntityRepository
{
	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getDateRecordByProductAndDate(int $productId, string $date): Date
	{
		$return = $this->createQueryBuilder('date')
			->select('date, reservation, season')
			->leftJoin('date.reservation', 'reservation')
			->leftJoin('date.season', 'season')
			->where('date.date = :date')
			->andWhere('date.product = :productId')
			->setParameter('date', DateTime::from($date)->format('Y-m-d'))
			->setParameter('productId', $productId)
			->getQuery()
			->getSingleResult();
		assert($return instanceof Date);

		return $return;
	}


	/**
	 * @param array<int, string> $dates
	 * @return array<int, Date>
	 */
	public function getByProductAndDates(int $productId, array $dates)
	{
		/** @var array<int, Date> $return */
		$return = $this->createQueryBuilder('d')
			->where('d.date IN (:dates)')
			->andWhere('d.product = :productId')
			->setParameter('dates', $dates)
			->setParameter('productId', $productId)
			->orderBy('d.date', 'ASC')
			->getQuery()
			->getResult();

		return $return;
	}
}
