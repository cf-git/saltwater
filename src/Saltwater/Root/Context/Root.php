<?php

namespace Saltwater\Root\Context;

use Saltwater\Thing\Context;

class Root extends Context
{
	public $namespace = 'Saltwater\Root';

	public $services = array('rest', 'info');
}
