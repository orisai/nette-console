<?php declare(strict_types = 1);

namespace OriNette\Console\DI;

use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use stdClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use function assert;
use function is_a;
use function is_string;

/**
 * @property-read stdClass $config
 */
final class ConsoleExtension extends CompilerExtension
{

	public const COMMAND_TAG = 'console.command';

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'catchExceptions' => Expect::bool(true),
			'name' => Expect::anyOf(
				Expect::string(),
				Expect::null(),
			)->default(null),
			'version' => Expect::anyOf(
				Expect::string(),
				Expect::int(),
				Expect::float(),
				Expect::null(),
			)->default(null),
		]);
	}

	public function loadConfiguration(): void
	{
		parent::loadConfiguration();

		$builder = $this->getContainerBuilder();
		$config = $this->config;

		$this->registerApplication($config, $builder, $this->registerCommandLoader($builder));
	}

	private function registerCommandLoader(ContainerBuilder $builder): ServiceDefinition
	{
		return $builder->addDefinition($this->prefix('commandLoader'))
			->setFactory(LazyCommandLoader::class)
			->setType(CommandLoaderInterface::class)
			->setAutowired(false);
	}

	private function registerApplication(
		stdClass $config,
		ContainerBuilder $builder,
		ServiceDefinition $commandLoaderDefinition
	): void
	{
		$applicationDefinition = $builder->addDefinition($this->prefix('application'))
			->setFactory(Application::class)
			->setType(Application::class)
			->addSetup('setAutoExit', [false])
			->addSetup('setCatchExceptions', [$config->catchExceptions])
			->addSetup('setCommandLoader', [$commandLoaderDefinition]);

		if ($config->name !== null) {
			$applicationDefinition->addSetup('setName', [$config->name]);
		}

		if ($config->version !== null) {
			$applicationDefinition->addSetup('setVersion', [$config->version]);
		}
	}

	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();

		$applicationDefinition = $builder->getDefinition($this->prefix('application'));
		assert($applicationDefinition instanceof ServiceDefinition);

		$this->addCommandsToApplication($applicationDefinition, $builder);
	}

	private function addCommandsToApplication(ServiceDefinition $applicationDefinition, ContainerBuilder $builder): void
	{
		$commandDefinitions = $builder->findByType(Command::class);
		$commandsMap = [];
		foreach ($commandDefinitions as $key => $commandDefinition) {
			assert($commandDefinition instanceof ServiceDefinition);

			$commandName = $this->getCommandName($commandDefinition);

			if ($commandName !== null) {
				$commandsMap[$commandName] = $key;
			} else {
				$applicationDefinition->addSetup('add', [$commandDefinition]);
			}
		}

		$commandLoaderDefinition = $builder->getDefinition($this->prefix('commandLoader'));
		assert($commandLoaderDefinition instanceof ServiceDefinition);
		$commandLoaderDefinition->getFactory()->arguments = [$commandsMap];
	}

	private function getCommandName(ServiceDefinition $definition): ?string
	{
		// From tag
		$tag = $definition->getTag(self::COMMAND_TAG);
		if (is_string($tag)) {
			$definition->addSetup('setName', [$tag]);

			return $tag;
		}

		// From type
		$type = $definition->getType();
		if (is_string($type) && is_a($type, Command::class, true)) {
			$commandName = $type::getDefaultName();

			if ($commandName !== null) {
				return $commandName;
			}
		}

		// From definition factory
		$factory = $definition->getFactory()->entity;
		if (is_string($factory) && is_a($factory, Command::class, true)) {
			$commandName = $factory::getDefaultName();

			if ($commandName !== null) {
				return $commandName;
			}
		}

		return null;
	}

}
