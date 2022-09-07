<?php

declare(strict_types=1);

namespace Baraja\Reservation\Endpoint\DTO;


final class ReservationOverviewItem
{
	public function __construct(
		public int $id,
		public string $number,
		public ReservationOverviewItemCustomer $customer,
		public int $price,
		public string $status,
		public string $from,
		public string $to,
		public \DateTimeInterface $createDate,
	) {
	}
}
