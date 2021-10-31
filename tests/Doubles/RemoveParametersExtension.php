<?php declare(strict_types = 1);

namespace Tests\OriNette\Console\Doubles;

use Nette\DI\CompilerExtension;

final class RemoveParametersExtension extends CompilerExtension
{

	public function loadConfiguration(): void
	{
		parent::loadConfiguration();
		$this->getContainerBuilder()->parameters = [];
	}

}
