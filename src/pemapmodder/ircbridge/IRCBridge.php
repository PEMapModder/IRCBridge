<?php

namespace pemapmodder\ircbridge;

use pemapmodder\ircbridge\ircserver\IRCServer;
use pocketmine\plugin\PluginBase;

class IRCBridge extends PluginBase{
	/** @var IRCServer */
	private $ircServer;
	public function onEnable(){
		$this->saveDefaultConfig();
		$config = $this->getConfig()->getAll();
		$ip = $config["ip"];
		$port = $config["port"];
		$this->ircServer = new IRCServer($ip, $port, "");
		$this->getLogger()->info("Starting Internet Relay Chat server on $ip:$port");
		$this->ircServer->start();
	}
	public function onDisable(){
		$this->ircServer->shutdown();
	}
}
