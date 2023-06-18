<?php declare(strict_types = 1);

namespace Tests\OriNette\Console\Doubles;

use Nette\Http\IRequest;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class UrlPrintingCommand extends Command
{

	private IRequest $request;

	public function __construct(IRequest $request)
	{
		parent::__construct();
		$this->request = $request;
	}

	public static function getDefaultName(): string
	{
		return 'print-url';
	}

	public static function getDefaultDescription(): string
	{
		return 'Print URL to output';
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->write((string) $this->request->getUrl());

		return 0;
	}

}
