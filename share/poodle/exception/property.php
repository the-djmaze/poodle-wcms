<?php

namespace Poodle\Exception;

class Property extends \Exception
{
	const
		IS_INVALID    = 0,
		IS_EMPTY      = 1,
		IS_READONLY   = 2,
		IS_NOT_UNIQUE = 3;

	protected
		$class,
		$property;

	function __construct($property, $msg='', $code=0, Exception $previous = NULL)
	{
		$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
		$class = $bt[0]['class'];

		if (!$msg) {
			$L10N = \Poodle::getKernel()->L10N;
			if ($L10N) {
				$L10N->load('poodle_exception');
				switch ($code)
				{
				case 1:  $msg = $L10N->get('_PROP_S_EMPTY'); break;
				case 2:  $msg = $L10N->get('_PROP_S_READONLY'); break;
				case 3:  $msg = $L10N->get('_PROP_S_NOT_UNIQUE'); break;
				default: $msg = $L10N->get('_PROP_S_INVALID'); break;
				}
				$msg = sprintf($msg, $class.'.'.$property);
			} else {
				$msg = "Property '{$class}.{$property}' ";
				if (1 == $code) {
					$msg .= "may not be empty";
				} else if (2 == $code) {
					$msg .= "is readonly";
				} else if (3 == $code) {
					$msg .= "is not unique";
				} else {
					$msg .= "is invalid";
				}
			}
		}

		parent::__construct($msg, $code, $previous);
		$this->class = $class;
		$this->property = $property;
	}

	final function getClass() { return $this->class; }

	final function getProperty() { return $this->property; }
}
