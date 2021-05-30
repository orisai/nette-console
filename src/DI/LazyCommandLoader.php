<?php declare(strict_types = 1);

namespace OriNette\Console\DI;

use OriNette\DI\Services\ServiceManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;

final class LazyCommandLoader extends ServiceManager implements CommandLoaderInterface
{

	public function get(string $name): Command
	{
		$service = $this->getService($name);

		if ($service === null) {
			throw new CommandNotFoundException("Command {$name} not found.");
		}

		if (!$service instanceof Command) {
			$this->throwInvalidServiceType($name, Command::class, $service);
		}

		return $service;
	}

	public function has(string $name): bool
	{
		return $this->hasService($name);
	}

	/**
	 * @return array<string>
	 */
	public function getNames(): array
	{
		return $this->getKeys();
	}

}
