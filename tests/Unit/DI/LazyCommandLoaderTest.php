<?php declare(strict_types = 1);

namespace Tests\OriNette\Console\Unit\DI;

use OriNette\Console\DI\LazyCommandLoader;
use OriNette\DI\Boot\ManualConfigurator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Tests\OriNette\Console\Doubles\DefaultNameCommand;
use function dirname;

final class LazyCommandLoaderTest extends TestCase
{

	public function testExisting(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/commandLoader.neon');

		$container = $configurator->createContainer();

		$loader = $container->getByType(LazyCommandLoader::class);

		self::assertTrue($loader->has('one'));
		self::assertTrue($loader->has('two'));

		self::assertSame(
			[
				'one',
				'two',
			],
			$loader->getNames(),
		);

		$commandOne = $loader->get('one');
		self::assertInstanceOf(DefaultNameCommand::class, $commandOne);
		self::assertSame($commandOne, $container->getService('command.one'));

		$commandTwo = $loader->get('two');
		self::assertInstanceOf(DefaultNameCommand::class, $commandTwo);
		self::assertSame($commandTwo, $container->getService('command.two'));
	}

	public function testMissing(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/commandLoader.neon');

		$container = $configurator->createContainer();

		$loader = $container->getByType(LazyCommandLoader::class);

		self::assertFalse($loader->has('three'));

		$this->expectException(CommandNotFoundException::class);
		$this->expectExceptionMessage('Command three not found.');

		$loader->get('three');
	}

}
