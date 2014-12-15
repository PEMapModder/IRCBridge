<?php

namespace pemapmodder\ircbridge\ircserver;

use pemapmodder\ircbridge\IRCBridge;
use pocketmine\Thread;

class IRCServer extends Thread{
	private $plugin;
	/** @var resource */
	private $sk;
	private $created;
	/** @var bool */
	private $running = false;
	/** @var IRCClient[] */
	private $clients = [];
	/** @var string */
	private $serverName;
	/** @var array|string */
	public $config;
	/** @var SynchronizedArray */
	private $logs;
	public function __construct(IRCBridge $plugin, $ip, $port, $serverName, array $config){
		$this->plugin = $plugin;
		$this->sk = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		$this->created = time();
		socket_bind($this->sk, $ip, $port);
		socket_listen($this->sk, 5);
		socket_set_nonblock($this->sk);
		$this->serverName = $serverName;
		$this->config = serialize($config);
		$this->logs = new SynchronizedArray([]);
	}
	public function run(){
		$config = $this->config = unserialize($this->config);
		$this->running = true;
		while($this->running){
			$con = socket_accept($this->sk);
			if(!is_resource($con)){
				continue;
			}
			socket_getpeername($con, $ip, $port);
			$client = new IRCClient($this, $con, $ip, $port, $config);
			$this->clients[$client->getIdentifier()] = $client;
			foreach($this->clients as $client){
				while($client->stackLine());
			}
		}
	}
	public function disconnect(IRCClient $client){
		$id = $client->getIdentifier();
		unset($this->clients[$id]);
		$client->closeConnection();
	}
	public function shutdown(){
		socket_close($this->sk);
		$this->running = false;
		$this->join();
	}
	/**
	 * @return string
	 */
	public function getServerName(){
		return $this->serverName;
	}
	public function getClient($identifier){
		return isset($this->clients[$identifier]) ? $this->clients[$identifier]:false;
	}
	public function getClients(){
		return $this->clients;
	}
	public function getClientByNick($nick){
		foreach($this->clients as $client){
			if($client->getNick() === $nick){
				return $client;
			}
		}
		return null;
	}
	/**
	 * @return int
	 */
	public function getCreated(){
		return $this->created;
	}
	public function getPlugin(){
		return $this->plugin;
	}
}
