<?php

namespace pemapmodder\ircbridge\ircserver;

class CommandLine{
	/** @var string */
	private $prefix, $cmd;
	/** @var string[] */
	private $args;
	public function __construct($line){
		if(preg_match_all("#(:([^ ]+) )?([A-Za-z0-9]+) (.*)#", trim($line), $matches, PREG_SET_ORDER) === 0){
			throw new \RuntimeException("Cannot handle command line '$line': preg_match_all() returns no matches");
		}
		list(, , $this->prefix, $cmd, $args) = $matches[0];
		$pos = strpos($args, ": ");
		if($pos !== false){
			$last = substr($args, $pos + 2);
			$args = array_merge(explode(" ", substr($args, 0, $pos)), [$last]);
		}
		else{
			$args = explode(" ", $args);
		}
		$this->cmd = strtoupper($cmd);
		$this->args = $args;
	}
}
