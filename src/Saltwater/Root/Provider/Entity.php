<?php

namespace Saltwater\Root\Provider;

use Saltwater\Server as S;
use Saltwater\Utils as U;
use Saltwater\Thing\Provider;

class Entity extends Provider
{
	public static function getProvider() { return new Entity(); }

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	public function get( $name )
	{
		$model = $this->formatModel($name);

		if ( !empty($model) ) return $model;

		return $name;
	}

	/**
	 * @param string $name
	 */
	private function formatModel( $name )
	{
		$module = S::$n->getModule(self::$caller);

		$bit = S::$n->bitThing('entity.' . $name);

		if ( !$module->hasThing($bit) ) {
			$module = S::$n->getModule(self::$module);
		}

		$name = U::snakeToCamelCase($name);

		$class = $module->namespace . '\Entity\\' . $name;

		if ( class_exists($class) ) return $class;

		$name = U::CamelTodashed($name);

		$bit = S::$n->bitThing('entity.' . $name);

		if ( $module->hasThing($bit) ) {
			return '\Saltwater\Thing\Entity';
		}

		return null;
	}
}
