<?php

declare(strict_types=1);

namespace Baraja\Reservation\Plugin;


use Baraja\Plugin\BasePlugin;

final class ReservationCalendarPlugin extends BasePlugin
{
	public function getName(): string
	{
		return 'Reservation calendar';
	}
}
