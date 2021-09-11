<?php declare(strict_types = 1);

namespace Tests\OriNette\Console\Unit\DI;

use Generator;
use OriNette\Console\Command\DIParametersCommand;
use OriNette\Console\DI\LazyCommandLoader;
use OriNette\DI\Boot\ManualConfigurator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as ComponentEventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as ContractsEventDispatcher;
use Tests\OriNette\Console\Doubles\AnotherDefaultNameCommand;
use Tests\OriNette\Console\Doubles\DefaultBothCommand;
use Tests\OriNette\Console\Doubles\DefaultNameCommand;
use Tests\OriNette\Console\Doubles\SimpleCommand;
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

	public function testConfigured(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.commands.neon');

		$container = $configurator->createContainer();

		$application = $container->getByType(Application::class);
		self::assertInstanceOf(Application::class, $application);

		self::assertSame('Name', $application->getName());
		self::assertSame('Version', $application->getVersion());
		self::assertFalse($application->areExceptionsCaught());

		self::assertSame([
			'help',
			'list',
			'di:parameters',
			'default',
			'both-default',
			'tagged',
			'another-default',
			'tagged-name:a',
			'tagged-name:b',
			'tagged-name:c',
			'unicorn',
			'AgathaChristie',
			'pizza',
		], array_keys($application->all()));
	}

	/**
	 * @param class-string<Command> $class
	 * @param array<string> $aliases
	 *
	 * @dataProvider provideCommandConfig
	 */
	public function testCommandConfig(
		string $service,
		string $class,
		string $name,
		string $description,
		array $aliases,
		bool $hidden
	): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/extension.commands.neon');

		$container = $configurator->createContainer();

		$application = $container->getByType(Application::class);
		self::assertInstanceOf(Application::class, $application);

		$command = $application->get($name);
		self::assertInstanceOf($class, $command);
		self::assertSame($name, $command->getName());
		self::assertSame($command, $container->getService($service));
		self::assertSame($description, $command->getDescription());
		self::assertSame($aliases, $command->getAliases());
		self::assertSame($hidden, $command->isHidden());
	}

	/**
	 * @return Generator<array<mixed>>
	 */
	public function provideCommandConfig(): Generator
	{
		yield [
			'command.defaultName',
			DefaultNameCommand::class,
			'default',
			'',
			[],
			false,
		];

		yield [
			'command.bothDefault',
			DefaultBothCommand::class,
			'both-default',
			'Default description',
			[],
			false,
		];

		yield [
			'command.tagged',
			DefaultNameCommand::class,
			'tagged',
			'Fully tagged',
			[],
			false,
		];

		yield [
			'command.tagged.description',
			AnotherDefaultNameCommand::class,
			'another-default',
			'Custom description',
			[],
			false,
		];

		yield [
			'command.tagged.name.a',
			SimpleCommand::class,
			'tagged-name:a',
			'',
			[],
			false,
		];

		yield [
			'command.tagged.name.b',
			SimpleCommand::class,
			'tagged-name:b',
			'',
			[],
			false,
		];

		yield [
			'command.tagged.name.c',
			SimpleCommand::class,
			'tagged-name:c',
			'',
			[],
			false,
		];

		yield [
			'command.hidden',
			SimpleCommand::class,
			'unicorn',
			'I am hiding so people can\'t hang me on their wall.',
			[],
			true,
		];

		yield [
			'command.aliased',
			SimpleCommand::class,
			'AgathaChristie',
			'Woman of many names.',
			['MaryWestmacott', 'AgathaMaryClarissaMiller'],
			false,
		];

		yield [
			'command.hiddenAndAliased',
			SimpleCommand::class,
			'pizza',
			'Hidden and aliased. Also hungry.',
			['kebab', 'quesadilla'],
			true,
		];
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
			new ArrayInput(['command' => DefaultNameCommand::getDefaultName()]),
			new NullOutput(),
		);

		self::assertInstanceOf(DefaultNameCommand::class, $command);
	}

}
