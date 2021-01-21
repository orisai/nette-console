<?php declare(strict_types = 1);

namespace OriNette\Console\DI;

use Nette\DI\Container;
use Orisai\Exceptions\Logic\InvalidArgument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use function array_key_exists;
use function array_keys;
use function get_class;

final class LazyCommandLoader implements CommandLoaderInterface
{

	/** @var array<string, string> */
	private array $servicesMap;

	private Container $container;

	/**
	 * @param array<string, string> $servicesMap
	 */
	public function __construct(array $servicesMap, Container $container)
	{
		$this->servicesMap = $servicesMap;
		$this->container = $container;
	}

	public function get(string $name): Command
	{
		if (!array_key_exists($name, $this->servicesMap)) {
			throw new CommandNotFoundException("Command {$name} not found.");
		}

		$service = $this->container->getService($this->servicesMap[$name]);

		if (!$service instanceof Command) {
			$serviceClass = get_class($service);
			$commandClass = Command::class;

			throw InvalidArgument::create()
				->withMessage("Class {$serviceClass} is not a subclass of {$commandClass}.");
		}

		return $service;
	}

	public function has(string $name): bool
	{
		return array_key_exists($name, $this->servicesMap);
	}

	/**
	 * @return array<string>
	 */
	public function getNames(): array
	{
		return array_keys($this->servicesMap);
	}

}
