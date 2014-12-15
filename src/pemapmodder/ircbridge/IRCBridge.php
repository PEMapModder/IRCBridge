<?php

namespace pemapmodder\ircbridge;

use pemapmodder\ircbridge\ircserver\IRCServer;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\plugin\PluginDisableEvent;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;

class IRCBridge extends PluginBase implements Listener{
	/** @var IRCServer */
	private $ircServer;
	public function onEnable(){
		$this->saveDefaultConfig();
		$config = $this->getConfig()->getAll();
		$ip = $config["ip"];
		$port = $config["port"];
		if($config["NickServ password with SimpleAuth"] and !($this->getServer()->getPluginManager()->getPlugin("SimpleAuth") instanceof Plugin)){
			$this->getLogger()->critical("NickServ-SimpleAuth integration is enabled but SimpleAuth is not enabled. Self-disabling.");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		$this->ircServer = new IRCServer($this, $ip, $port, $config["server name"], $config);
		$this->getLogger()->info("Starting Internet Relay Chat server on $ip:$port");
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new ClientTicker($this), 1);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->ircServer->start();
	}
	public function onDisable(){
		$this->ircServer->shutdown();
	}
	/**
	 * @return IRCServer
	 */
	public function getIrcServer(){
		return $this->ircServer;
	}
	public function onPluginDisabled(PluginDisableEvent $event){
		if($event->getPlugin()->getName() === "SimpleAuth" and $this->getConfig()->get("NickServ password with SimpleAuth")){
			$this->getLogger()->critical("NickServ-SimpleAuth integration is enabled but SimpleAuth is being disabled. Self-disabling too.");
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}
	}
	/**
	 * @param PlayerChatEvent $event
	 * @priority MONITOR
	 * @ignoreCancelled true
	 */
	public function onChat(PlayerChatEvent $event){

	}
}
