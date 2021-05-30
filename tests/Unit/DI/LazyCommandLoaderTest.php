<?php declare(strict_types = 1);

namespace Tests\OriNette\Console\Unit\DI;

use OriNette\Console\DI\LazyCommandLoader;
use OriNette\DI\Boot\ManualConfigurator;
use Orisai\Exceptions\Logic\InvalidArgument;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Tests\OriNette\Console\Doubles\LazyCommand;
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

		self::assertTrue($loader->has('tests:one'));
		self::assertTrue($loader->has('tests:two'));

		self::assertSame(
			[
				'tests:one',
				'tests:two',
				'tests:four',
			],
			$loader->getNames(),
		);

		$commandOne = $loader->get('tests:one');
		self::assertInstanceOf(LazyCommand::class, $commandOne);
		self::assertSame($commandOne, $container->getService('command.one'));

		$commandTwo = $loader->get('tests:two');
		self::assertInstanceOf(LazyCommand::class, $commandTwo);
		self::assertSame($commandTwo, $container->getService('command.two'));
	}

	public function testMissing(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/commandLoader.neon');

		$container = $configurator->createContainer();

		$loader = $container->getByType(LazyCommandLoader::class);

		self::assertFalse($loader->has('tests:three'));

		$this->expectException(CommandNotFoundException::class);
		$this->expectExceptionMessage('Command tests:three not found.');

		$loader->get('tests:three');
	}

	public function testNotACommand(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/commandLoader.neon');

		$container = $configurator->createContainer();

		$loader = $container->getByType(LazyCommandLoader::class);

		self::assertTrue($loader->has('tests:four'));

		$this->expectException(InvalidArgument::class);
		$this->expectExceptionMessage(<<<'MSG'
Context: Service command.four returns instance of stdClass.
Problem: OriNette\Console\DI\LazyCommandLoader supports only instances of
         Symfony\Component\Console\Command\Command.
Solution: Remove service from LazyCommandLoader or make the service return
          supported object type.
MSG);

		$loader->get('tests:four');
	}

}
