<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

// On destruct Fatal error:  Class 'Poodle\Events\Event' not found
class_exists('Poodle\\Events\\Event');

trait Events
{
	protected
		$_poodle_events_recursive = false;

	private
		$_poodle_events = array(),
		$_poodle_events_loaded = false;

	private static
		$_poodle_events_classes = array();

	private function loadEventListeners()
	{
		$class = strtolower(static::class);
		if (!isset(self::$_poodle_events_classes[$class])) {
			self::$_poodle_events_classes[$class] = array();
			$SQL = \Poodle::getKernel()->SQL;
			if ($SQL && isset($SQL->TBL->classes_events)) {
				if ($this->_poodle_events_recursive) {
					$classes = implode(',', array_map(
						array($SQL, 'quote'),
						array_merge(array($class), class_parents($this))
					));
				} else {
					$classes = $SQL->quote($class);
				}
				$qr = $SQL->query("SELECT class_event, class_callable
					FROM {$SQL->TBL->classes_events}
					WHERE class_name IN (".strtolower($classes).")");
				while ($r = $qr->fetch_row()) {
					if (is_callable($r[1])) {
						self::$_poodle_events_classes[$class][] = $r;
					}
				}
			}
		}
		if (!$this->_poodle_events_loaded) {
			$this->_poodle_events_loaded = true;
			foreach (self::$_poodle_events_classes[$class] as $r) {
				$this->addEventListener($r[0], $r[1]);
			}
		}
	}

	public static function hookEventListener(string $type, $function)
	{
		\Poodle::getKernel()->SQL->TBL->classes_events->insert(array(
			'class_name' => strtolower(static::class),
			'class_event' => strtolower($type),
			'class_callable' => strtolower($function)
		));
	}

	public static function unhookEventListener(string $type, $function)
	{
		\Poodle::getKernel()->SQL->TBL->classes_events->delete(array(
			'class_name' => strtolower(static::class),
			'class_event' => strtolower($type),
			'class_callable' => strtolower($function)
		));
	}

	public function addEventListener(string $type, callable $function)
	{
		$type = strtolower($type);
		if (is_array($function)) {
			$function = is_object($function[0]) ? $function : "{$function[0]}::{$function[1]}";
		}
		if (!is_callable($function)) {
			throw new \Exception("Function for Event '{$type}' is not callable");
		}
		if (!isset($this->_poodle_events[$type])) {
			$this->_poodle_events[$type] = array($function);
		}
		else if (!in_array($function, $this->_poodle_events[$type], true)) {
			$this->_poodle_events[$type][] = $function;
		}
	}

	public function dispatchEvent(\Poodle\Events\Event $event)
	{
		$this->loadEventListeners();
		$type = strtolower($event->type);
		if (!empty($this->_poodle_events[$type])) {
			$event->target = $this;
			// Execute in reverse order?
			foreach ($this->_poodle_events[$type] as $callback) {
				try {
					call_user_func($callback, $event);
				} catch (\Throwable $e) {
					trigger_error("{$event}: {$e->getMessage()}\n{$e->getTraceAsString()}");
//					\Poodle\LOG::error($event, $e->getMessage()."\n".$e->getTraceAsString());
				}
			}
		}
	}

	public function removeEventListener(string $type, callable $function)
	{
		$type = strtolower($type);
		if (!empty($this->_poodle_events[$type])) {
			$key = array_search($function, $this->_poodle_events[$type], true);
			if (false !== $key) { unset($this->_poodle_events[$type][$key]); }
		}
	}

	public function triggerEvent(string $type)
	{
		$type = strtolower($type);
		if (!empty($this->_poodle_events[$type])) {
			$this->dispatchEvent(new \Poodle\Events\Event($type));
		}
	}

	protected function removeAllEventListeners(string $type = null)
	{
		if ($type) unset($this->_poodle_events[strtolower($type)]);
		else $this->_poodle_events = array();
	}
}
