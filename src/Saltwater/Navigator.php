<?php

namespace Saltwater;

use Saltwater\Server as S;
use Saltwater\Utils as U;

/**
 * Class Navigator
 *
 * @package Saltwater
 *
 * List of known providers:
 *
 * @property \Saltwater\Root\Provider\Context $context
 * @property \Saltwater\Root\Provider\Entity  $entity
 * @property \Saltwater\Root\Provider\Service $service
 *
 * @property \RedBean_Instance $db
 *
 * @property \Saltwater\Root\Provider\Log      $log
 * @property \Saltwater\Root\Provider\Response $response
 * @property \Saltwater\Root\Provider\Route    $route
 *
 * @property \Saltwater\Common\Config $config
 */
class Navigator
{
	/**
	 * @var Thing\Module[]
	 */
	private $modules = array();

	/**
	 * @var string[] array of Saltwater\Thing(s)
	 */
	private $things = array();

	/**
	 * @var string
	 */
	private $root = 'root';

	/**
	 * @var string
	 */
	private $master = '';

	/**
	 * @var string[] array of modules stacked in the order they were called in
	 */
	private $stack = array();

	/**
	 * @var array classes that can be skipped during search for caller module
	 */
	private $skip = array(
		'Saltwater\Navigator',
		'Saltwater\Server'
	);

	/**
	 * Add module to Navigator and register its things
	 *
	 * @param string $class  Full Classname
	 * @param bool   $master true if this is also the master module
	 *
	 * @return bool|null
	 */
	public function addModule( $class, $master=false )
	{
		if ( !class_exists($class) ) return false;

		$name = U::namespacedClassToDashed($class);

		if ( isset($this->modules[$name]) ) return null;

		$module = new $class();

		$module->register($name);

		// Push to ->modules late to preserve dependency order
		$this->modules[$name] = $module;

		if ( $master ) $this->setMaster($name);

		return true;
	}

	/**
	 * @param string $path
	 */
	public function cache( $path )
	{
		$cache = new \stdClass();

		$this->copyCache( $this, $cache );

		$info = pathinfo($path);

		if ( !is_dir($info['dirname']) ) mkdir($info['dirname'], 0744, true);

		file_put_contents( $path, serialize($cache)	);
	}

	/**
	 * @param string $path
	 *
	 * @return bool
	 */
	public function loadCache( $path )
	{
		$cache = unserialize( file_get_contents($path) );

		return $this->copyCache( $cache, $this );
	}

	/**
	 * @param Navigator|object $from
	 * @param Navigator|object $to
	 *
	 * @return bool
	 */
	private function copyCache( $from, &$to )
	{
		foreach ( array('things', 'root', 'master', 'stack') as $k ) {
			$to->$k = $from->$k;
		}

		$nav = $to instanceof Navigator;

		if ( !$nav ) $to->modules = array();

		foreach ( $from->modules as $name => $module ) {
			if ( $nav ) {
				$to->modules[$name] = new $module();

				$to->modules[$name]->things = $from->bits[$name];
			} else {
				$to->modules[$name] = get_class($module);

				$to->bits[$name] = $module->things;
			}
		}

		return true;
	}

	/**
	 * Return true if the input is a registered thing
	 *
	 * @param string $name in the form "type.name"
	 *
	 * @return bool
	 */
	public function isThing( $name )
	{
		return in_array($name, $this->things) !== false;
	}

	/**
	 * Return the bitmask integer of a thing
	 *
	 * @param string $name in the form "type.name"
	 *
	 * @return bool|int
	 */
	public function bitThing( $name )
	{
		return array_search($name, $this->things);
	}

	/**
	 * Register a thing and return its bitmask integer
	 * @param $name
	 *
	 * @return number
	 */
	public function addThing( $name )
	{
		if ( $id = $this->bitThing($name) ) return $id;

		$id = pow( 2, count($this->things) );

		$this->things[$id] = $name;

		return $id;
	}

	/**
	 * Return a module class by its name
	 *
	 * @param bool $reverse
	 *
	 * @return Thing\Module[]
	 */
	public function getModules( $reverse=false )
	{
		if ( $reverse ) {
			return array_reverse($this->modules);
		} else {
			return $this->modules;
		}
	}

	/**
	 * Return a module class by its name
	 *
	 * @param string $name
	 *
	 * @return Thing\Module
	 */
	public function getModule( $name )
	{
		return $this->modules[$name];
	}

	/**
	 * Return the master context for the current master module
	 *
	 * @param \Saltwater\Thing\Context|null $parent inject a parent context
	 *
	 * @return \Saltwater\Thing\Context
	 */
	public function masterContext( $parent=null )
	{
		foreach ( $this->modules as $name => $module ) {
			$context = $module->masterContext();

			if ( !empty($context) ) {
				$parent = $this->context->get($context, $parent);
			}

			if ( $name == $this->master ) break;
		}

		return $parent;
	}

	/**
	 * Get the Module that provides a context
	 *
	 * @param string $name plain name of the context
	 *
	 * @return Thing\Module|null
	 */
	public function getContextModule( $name )
	{
		$bit = $this->bitThing('context.' . $name);

		foreach ( $this->modules as $module ) {
			if ( $module->hasThing($bit) ) return $module;
		}

		return null;
	}

	/**
	 * Set the root module by name
	 *
	 * @param string $name
	 */
	public function setRoot( $name )
	{
		if ( empty($name) || ($name == $this->root) ) return;

		$this->root = $name;
	}

	/**
	 * Set the master module by name
	 *
	 * @param string $name
	 */
	public function setMaster( $name )
	{
		if ( empty($name) || ($name == $this->master) ) return;

		$this->master = $name;

		$this->pushStack($name);
	}

	/**
	 * Push a module name onto the stack, establishing later hierarchy for calls
	 *
	 * @param string $name
	 */
	private function pushStack( $name )
	{
		if ( empty($this->stack) ) $this->stack[] = $this->root;

		if ( in_array($name, $this->stack) ) return;

		$this->stack[] = $name;
	}

	/**
	 * Generic call for a type of provider
	 *
	 * @param string $type
	 * @param string $caller Caller module name
	 *
	 * @return Thing\Provider
	 */
	public function provider( $type, $caller=null )
	{
		$thing = 'provider.' . $type;

		if ( !$bit = $this->bitThing($thing) ) {
			S::halt(500, 'provider does not exist: ' . $type);
		};

		if ( empty($caller) ) {
			$caller = $this->findModule($this->lastCaller(), $thing);
		}

		return $this->providerFromModule($bit, $caller, $type);
	}

	/**
	 * @param int    $bit
	 * @param string $caller
	 * @param string $type
	 *
	 * @return bool|Thing\Provider
	 */
	private function providerFromModule( $bit, $caller, $type)
	{
		// Depending on the caller, reset the module stack
		$this->setMaster($caller);

		foreach ( $this->modulePrecedence() as $k ) {
			if ( !$this->modules[$k]->hasThing($bit) ) continue;

			$return = $this->modules[$k]->provider($k, $caller, $type);

			if ( $return !== false ) return $return;
		}

		$master = array_search($this->master, $this->stack);

		if ( $master == count($this->stack)-1 ) return false;

		// As a last resort, step one module up within stack and try again
		$caller = $this->stack[$master+1];

		return $this->providerFromModule($bit, $caller, $type);
	}

	/**
	 * Find the module of a caller class
	 *
	 * @param array|null $caller
	 * @param string     $provider
	 *
	 * @return string module name
	 */
	private function findModule( $caller, $provider )
	{
		if ( empty($caller) ) return null;

		$caller = $this->explodeCaller($caller, $provider);

		$bit = $caller->provider ? $this->bitThing($caller->thing) : 0;

		foreach ( $this->getModules(true) as $k => $module ) {
			if ( $this->compareCallerModule( $caller, $module, $bit ) ) {
				return $k;
			}
		}

		return null;
	}

	/**
	 * @param object       $caller
	 * @param Thing\Module $module
	 * @param string       $bit
	 *
	 * @return bool
	 */
	private function compareCallerModule( $caller, $module, $bit )
	{
		if ( $caller->provider ) {
			if ( !$module->hasThing($bit) ) return false;

			// A provider calling itself always gets a lower level provider
			if ( $module->namespace == $caller->namespace ) return false;
		}

		if ( $module->namespace !== $caller->namespace ) return false;

		if ( !$module->hasThing($bit) ) return false;

		return true;
	}

	/**
	 * @param array  $caller
	 * @param string $provider
	 *
	 * @return object
	 */
	private function explodeCaller( $caller, $provider )
	{
		// Extract a thing from the last two particles
		$class = array_pop($caller);

		$thing = strtolower( array_pop($caller) . '.' . $class );

		// The rest is the namespace
		return (object) array(
			'thing'     => $thing,
			'namespace' => implode('\\', $caller),
			'provider'  => $thing == $provider
		);
	}

	/**
	 * Extracts the last calling class from a debug_backtrace, skipping the
	 * Navigator and Server, of course.
	 *
	 * And - Yup, debug_backtrace().
	 *
	 * @return array|null
	 */
	public function lastCaller()
	{
		// Let me tell you about my boat
		$trace = debug_backtrace(2, 8);

		$depth = count($trace);

		// Iterate through backtrace, find the last caller class
		for ( $i=2; $i<$depth; ++$i ) {
			if ( !isset($trace[$i]['class']) ) continue;

			if (
				in_array($trace[$i]['class'], $this->skip)
				|| (strpos($trace[$i]['class'], 'Saltwater\Root') !== false)
			) continue;

			return explode('\\', $trace[$i]['class']);
		}

		return null;
	}

	/**
	 * Return top candidate Module for providing a Thing
	 *
	 * @param string $thing
	 * @param bool   $precedence Use the current module precedence rules
	 *
	 * @return bool|mixed
	 */
	public function moduleByThing( $thing, $precedence=true )
	{
		return $this->modulesByThing($thing, $precedence, true);
	}

	/**
	 * Return a list of Modules providing a Thing
	 * @param      string $thing
	 * @param bool $precedence
	 * @param bool $first      only return the first item on the list
	 *
	 * @return array|bool
	 */
	public function modulesByThing( $thing, $precedence=true, $first=false )
	{
		if ( !$this->isThing($thing) ) return false;

		$b = $this->bitThing($thing);

		if ( $precedence ) {
			$modules = $this->modulePrecedence();
		} else {
			$modules = array_keys( $this->getModules(true) );
		}

		$return = array();
		foreach ( $modules as $module ) {
			if ( !$this->modules[$module]->hasThing($b) ) continue;

			if ( $first ) return $module;

			$return[] = $module;
		}

		return $return;
	}

	private function modulePrecedence()
	{
		$return = array();
		foreach ( $this->stack as $module ) {
			array_unshift($return, $module);

			if ( $module == $this->master ) break;
		}

		return $return;
	}

	public function __get( $type )
	{
		return $this->provider($type);
	}

	public function __call( $type, $args )
	{
		$caller = empty($args) ? null : array_shift($args);

		return $this->provider($type, $caller);
	}
}
