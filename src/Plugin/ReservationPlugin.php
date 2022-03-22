<?php

declare(strict_types=1);

namespace Baraja\Reservation\Plugin;


use Baraja\Doctrine\EntityManager;
use Baraja\Plugin\BasePlugin;
use Baraja\Reservation\Entity\Reservation;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class ReservationPlugin extends BasePlugin
{
	public function __construct(
		private EntityManager $entityManager,
	) {
	}


	public function getName(): string
	{
		return 'Reservations';
	}


	public function actionDetail(int $id): void
	{
		try {
			/** @var Reservation $reservation */
			$reservation = $this->entityManager->getRepository(Reservation::class)
				->createQueryBuilder('r')
				->where('r.id = :id')
				->setParameter('id', $id)
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();
		} catch (NoResultException | NonUniqueResultException) {
			$this->error(sprintf('Reservation "%d" does not exist.', $id));
		}

		$this->setTitle(sprintf(
			'(%s) Reservation [%s]',
			$reservation->getIdentifier(),
			$reservation->getName(),
		));
		$this->setSubtitle($reservation->getPhone());
	}
}
