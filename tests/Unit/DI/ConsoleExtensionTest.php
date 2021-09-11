<?php declare(strict_types = 1);

namespace Tests\OriNette\Console\Unit\DI;

use OriNette\Console\Command\DIParametersCommand;
use OriNette\Console\DI\LazyCommandLoader;
use OriNette\DI\Boot\ManualConfigurator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as ComponentEventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as ContractsEventDispatcher;
use Tests\OriNette\Console\Doubles\LazyCommand;
use Tests\OriNette\Console\Doubles\NotLazyCommand;
use function array_keys;
use function assert;
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

		self::assertInstanceOf(DIParametersCommand::class, $application->get('di:parameters'));
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

		$notLazyTaggedCommandA = $application->get('tests:notLazy-tagged:a');
		self::assertInstanceOf(NotLazyCommand::class, $notLazyTaggedCommandA);
		self::assertSame('tests:notLazy-tagged:a', $notLazyTaggedCommandA->getName());
		self::assertSame($notLazyTaggedCommandA, $container->getService('command.notLazy.tagged.a'));

		$notLazyTaggedCommandB = $application->get('tests:notLazy-tagged:b');
		self::assertInstanceOf(NotLazyCommand::class, $notLazyTaggedCommandB);
		self::assertSame('tests:notLazy-tagged:b', $notLazyTaggedCommandB->getName());
		self::assertSame($notLazyTaggedCommandB, $container->getService('command.notLazy.tagged.b'));

		$notLazyTaggedCommandC = $application->get('tests:notLazy-tagged:c');
		self::assertInstanceOf(NotLazyCommand::class, $notLazyTaggedCommandC);
		self::assertSame('tests:notLazy-tagged:c', $notLazyTaggedCommandC->getName());
		self::assertSame($notLazyTaggedCommandC, $container->getService('command.notLazy.tagged.c'));

		self::assertSame([
			'tests:lazy',
			'tests:lazy-tagged',
			'tests:notLazy-tagged:a',
			'tests:notLazy-tagged:b',
			'tests:notLazy-tagged:c',
		], array_keys($application->all('tests')));
	}

	public function testEventDispatcher(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.eventDispatcher.neon');

		$container = $configurator->createContainer();

		$application = $container->getByType(Application::class);
		self::assertInstanceOf(Application::class, $application);

		$dispatcher = $container->getByType(ContractsEventDispatcher::class);
		assert($dispatcher instanceof ComponentEventDispatcher);

		$command = null;
		$dispatcher->addListener(
			ConsoleEvents::COMMAND,
			static function (ConsoleCommandEvent $event) use (&$command): void {
				$command = $event->getCommand();
			},
		);

		$application->run(
			new ArrayInput(['command' => LazyCommand::getDefaultName()]),
			new NullOutput(),
		);

		self::assertInstanceOf(LazyCommand::class, $command);
	}

}
