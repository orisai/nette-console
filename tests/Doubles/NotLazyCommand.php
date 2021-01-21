<?php declare(strict_types = 1);

namespace Tests\OriNette\Console\Doubles;

use Symfony\Component\Console\Command\Command;

final class NotLazyCommand extends Command
{

	public function __construct()
	{
		parent::__construct();
	}

}
