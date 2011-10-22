<?php

require_once "phing/Task.php";
include_once 'phing/system/util/Properties.php';

/**
 * Sets the given property name to the current date & time, optionally
 * using the format string provided
 * 
 * NOTE: This class conforms to Phing's class-naming scheme, which is
 * not PSR-0 compatible.
 */
class NowTask extends Task
{
        protected $name = null;
	protected $format = "YmdHi";
	
        public function setName($name)
        {
                $this->name = $name;
        }
        
	public function setFormat($format)
	{
		$this->format = $format;
	}
	
	public function main()
	{
                if (!isset($this->name))
                {
                        throw new BuildException("You must specify the name of the property to set", $this->getLocation());
                }
		$now = date($this->format);
                
                $this->project->setProperty($this->name, $now);
	}
}