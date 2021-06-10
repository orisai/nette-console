<?php declare(strict_types = 1);

namespace OriNette\Console\Command;

use Nette\DI\Container;
use OriNette\Console\Utils\ParametersSorter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function array_keys;
use function count;
use function get_class;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function next;
use function prev;
use function sprintf;

final class DIParametersCommand extends Command
{

	private Container $container;

	public function __construct(Container $container)
	{
		parent::__construct();
		$this->container = $container;
	}

	public static function getDefaultName(): string
	{
		return 'di:parameters';
	}

	protected function configure(): void
	{
		$this->setName(self::getDefaultName());
		$this->setDescription('Show DI container parameters');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$parameters = $this->container->getParameters();

		if ($parameters === []) {
			$output->writeln('No parameters found in DIC');

			return 0;
		}

		$this->printSortedParameters(
			$output,
			ParametersSorter::sortByType($this->container->getParameters()),
		);

		return 0;
	}

	/**
	 * @param array<mixed> $parameters
	 */
	private function printSortedParameters(OutputInterface $output, array $parameters, string $spaces = '  '): void
	{
		$lastKey = array_keys($parameters)[count($parameters) - 1];

		foreach ($parameters as $key => $item) {
			if (is_array($item)) {
				if ($item === []) {
					$output->writeln(sprintf(
						'%s<fg=cyan>%s</>: <fg=white>[]</>',
						$spaces,
						$key,
					));
				} else {
					$output->writeln(sprintf(
						'%s<fg=cyan>%s</>:',
						$spaces,
						$key,
					));

					$this->printSortedParameters($output, $item, $spaces . '  ');
				}
			} else {
				$output->writeln(sprintf(
					'%s<fg=cyan>%s</>: %s',
					$spaces,
					$key,
					$this->valueToString($item),
				));

				if ($key === $lastKey) {
					$output->writeln('');
				} elseif (is_array(next($parameters))) {
					$output->writeln('');
					prev($parameters);
				}
			}
		}
	}

	/**
	 * @param mixed $value
	 */
	private function valueToString($value): string
	{
		if (is_bool($value)) {
			$value = $value ? 'true' : 'false';
			$fg = 'yellow';
		} elseif (is_int($value) || is_float($value)) {
			$fg = 'green';
		} elseif (is_string($value)) {
			$fg = 'white';
		} elseif ($value === null) {
			$value = 'null';
			$fg = 'yellow';
		} else {
			$value = is_object($value) ? get_class($value) : 'Unknown';
			$fg = 'red';
		}

		return "<fg={$fg}>{$value}</>";
	}

}
