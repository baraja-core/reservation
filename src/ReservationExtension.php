<?php

declare(strict_types=1);

namespace Baraja\Reservation;


use Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension;
use Baraja\Plugin\Component\VueComponent;
use Baraja\Plugin\PluginManager;
use Baraja\Reservation\Plugin\ReservationCalendarPlugin;
use Baraja\Reservation\Plugin\ReservationPlugin;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;

final class ReservationExtension extends CompilerExtension
{
	/**
	 * @return string[]
	 */
	public static function mustBeDefinedBefore(): array
	{
		return [OrmAnnotationsExtension::class];
	}


	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		OrmAnnotationsExtension::addAnnotationPathToManager($builder, 'Baraja\Reservation\Entity', __DIR__ . '/Entity');

		$builder->addDefinition($this->prefix('calendar'))
			->setFactory(Calendar::class);

		$builder->addDefinition($this->prefix('reservationManager'))
			->setFactory(ReservationManager::class);

		/** @var ServiceDefinition $pluginManager */
		$pluginManager = $this->getContainerBuilder()->getDefinitionByType(PluginManager::class);
		$pluginManager->addSetup('?->addComponent(?)', ['@self', [
			'key' => 'reservationDefault',
			'name' => 'reservation-default',
			'implements' => ReservationPlugin::class,
			'componentClass' => VueComponent::class,
			'view' => 'default',
			'source' => __DIR__ . '/../template/reservation-default.js',
			'position' => 80,
			'tab' => 'Reservations',
			'params' => [],
		]]);
		$pluginManager->addSetup('?->addComponent(?)', ['@self', [
			'key' => 'reservationOverview',
			'name' => 'reservation-overview',
			'implements' => ReservationPlugin::class,
			'componentClass' => VueComponent::class,
			'view' => 'detail',
			'source' => __DIR__ . '/../template/reservation-overview.js',
			'position' => 100,
			'tab' => 'Overview',
			'params' => ['id'],
		]]);
		$pluginManager->addSetup('?->addComponent(?)', ['@self', [
			'key' => 'calendarDefault',
			'name' => 'calendar-default',
			'implements' => ReservationCalendarPlugin::class,
			'componentClass' => VueComponent::class,
			'view' => 'default',
			'source' => __DIR__ . '/../template/calendar-default.js',
			'position' => 80,
			'tab' => 'Reservation calendar',
			'params' => [],
		]]);
	}
}
