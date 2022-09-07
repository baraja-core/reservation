<?php

declare(strict_types=1);

namespace Baraja\Reservation\Endpoint\DTO;


final class ReservationOverviewItemCustomer
{
	public function __construct(
		public ?string $firstName,
		public ?string $lastName,
		public string $email,
		public ?string $phone,
		public ?string $avatarUrl = null,
	) {
	}
}
