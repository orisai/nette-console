<?php declare(strict_types = 1);

namespace OriNette\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CommandsDebugCommand extends Command
{

	/** @var array<array{string, string, bool, bool}> */
	private array $commands;

	/**
	 * @param array<array{string, string, bool, bool}> $commands
	 */
	public function __construct(array $commands)
	{
		parent::__construct();
		$this->commands = $commands;
	}

	public static function getDefaultName(): string
	{
		return 'commands-debug';
	}

	public static function getDefaultDescription(): string
	{
		return 'Check which commands are not lazy loaded';
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		if ($this->commands === []) {
			$output->writeln('All commands are lazy-loaded.');

			return self::SUCCESS;
		}

		$output->writeln('Following commands are missing <fg=red>❌</> either name or description. ' .
			'Check orisai/nette-console documentation about lazy loading to learn how to fix it.'
			. "\n");

		$table = new Table($output);
		$table->setStyle('compact');

		$table->addRow(['Name', 'Description', 'Service name', 'Service type']);
		foreach ($this->commands as [$service, $type, $name, $description]) {
			$table->addRow([
				$name ? '<fg=green>✔️</>' : '<fg=red>❌</>',
				$description ? '<fg=green>✔️</>' : '<fg=red>❌</>',
				$service,
				$type,
			]);
		}

		$table->render();

		return self::FAILURE;
	}

}
