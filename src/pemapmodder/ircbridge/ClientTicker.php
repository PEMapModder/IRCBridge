<?php

namespace pemapmodder\ircbridge;

use pocketmine\scheduler\PluginTask;

class ClientTicker extends PluginTask{
	public function onRun($currentTick){
		/** @var IRCBridge $plugin */
		$plugin = $this->owner;
		foreach($plugin->getIrcServer()->getClients() as $client){
			$client->mainThreadTick($plugin);
		}
	}
}
