<?php

declare(strict_types=1);

namespace Baraja\Reservation\Endpoint\DTO;


final class ReservationDetailResponse
{
	/**
	 * @param array<int, array{id: int, date: string, season: string|null}> $dates
	 * @param array<int, array{id: int, number: string, price: int, status: string}> $otherReservationsByCustomer
	 * @param array<int, array{id: int, productId: int, name: string, quantity: int}> $products
	 */
	public function __construct(
		public int $id,
		public string $number,
		public ReservationOverviewItemCustomer $customer,
		public int $price,
		public string $status,
		public string $from,
		public \DateTimeInterface $fromDate,
		public string $to,
		public \DateTimeInterface $toDate,
		public \DateTimeInterface $createDate,
		public array $dates,
		public ?string $note,
		public array $otherReservationsByCustomer,
		public array $products,
	) {
	}
}
