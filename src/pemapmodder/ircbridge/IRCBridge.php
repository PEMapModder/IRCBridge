<?php

namespace pemapmodder\ircbridge;

use pocketmine\plugin\PluginBase;

class IRCBridge extends PluginBase{
	/** @var IRCServer */
	private $ircServer;
	public function onEnable(){
		$this->saveDefaultConfig();
		$config = $this->getConfig()->getAll();
		$ip = $config["ip"];
		$port = $config["port"];
		$this->ircServer = new IRCServer($ip, $port);
	}
	public function onDisable(){
		$this->ircServer->shutdown();
	}
}
