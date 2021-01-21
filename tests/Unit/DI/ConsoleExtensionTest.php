<?php declare(strict_types = 1);

namespace Tests\OriNette\Console\Unit\DI;

use OriNette\Console\DI\LazyCommandLoader;
use OriNette\DI\Boot\ManualConfigurator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Tests\OriNette\Console\Doubles\LazyCommand;
use Tests\OriNette\Console\Doubles\NotLazyCommand;
use function array_keys;
use function dirname;

final class ConsoleExtensionTest extends TestCase
{

	public function testMinimal(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.minimal.neon');

		$container = $configurator->createContainer();

		self::assertFalse($container->isCreated('console.commandLoader'));

		$application = $container->getByType(Application::class);
		self::assertInstanceOf(Application::class, $application);
		self::assertSame($application, $container->getService('console.application'));

		self::assertTrue($container->isCreated('console.commandLoader'));

		$commandLoader = $container->getService('console.commandLoader');
		self::assertInstanceOf(LazyCommandLoader::class, $commandLoader);

		self::assertSame('UNKNOWN', $application->getName());
		self::assertSame('UNKNOWN', $application->getVersion());
		self::assertFalse($application->isAutoExitEnabled());
		self::assertTrue($application->areExceptionsCaught());

		self::assertSame([], array_keys($application->all('tests')));
	}

	public function testFull(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.full.neon');

		$container = $configurator->createContainer();

		$application = $container->getByType(Application::class);
		self::assertInstanceOf(Application::class, $application);

		self::assertSame('Name', $application->getName());
		self::assertSame('Version', $application->getVersion());
		self::assertFalse($application->areExceptionsCaught());

		$lazyCommand = $application->get('tests:lazy');
		self::assertInstanceOf(LazyCommand::class, $lazyCommand);
		self::assertSame('tests:lazy', $lazyCommand->getName());
		self::assertSame($lazyCommand, $container->getService('command.lazy'));

		$lazyTaggedCommand = $application->get('tests:lazy-tagged');
		self::assertInstanceOf(LazyCommand::class, $lazyTaggedCommand);
		self::assertSame('tests:lazy-tagged', $lazyTaggedCommand->getName());
		self::assertSame($lazyTaggedCommand, $container->getService('command.lazy.tagged'));

		$notLazyTaggedCommand = $application->get('tests:notLazy-tagged');
		self::assertInstanceOf(NotLazyCommand::class, $notLazyTaggedCommand);
		self::assertSame('tests:notLazy-tagged', $notLazyTaggedCommand->getName());
		self::assertSame($notLazyTaggedCommand, $container->getService('command.notLazy.tagged'));

		self::assertSame([
			'tests:lazy',
			'tests:lazy-tagged',
			'tests:notLazy-tagged',
		], array_keys($application->all('tests')));
	}

}
