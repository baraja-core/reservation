<?php

declare(strict_types=1);

namespace Baraja\Reservation\Endpoint\DTO;


final class ReservationOverviewResponse
{
	/**
	 * @param array<int, ReservationOverviewItem> $items
	 * @param array<int, int> $paginator
	 */
	public function __construct(
		public int $count,
		public array $items,
		public array $paginator,
	) {
	}
}
