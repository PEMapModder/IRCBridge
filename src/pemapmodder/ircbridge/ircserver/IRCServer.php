<?php

namespace pemapmodder\ircbridge\ircserver;

use pocketmine\Thread;

class IRCServer extends Thread{
	/** @var resource */
	private $sk;
	/** @var bool */
	private $running = false;
	private $clients = [];
	/** @var string */
	private $serverName;
	/** @var array|string */
	private $config;
	public function __construct($ip, $port, $serverName, array $config){
		$this->sk = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_bind($this->sk, $ip, $port);
		socket_listen($this->sk, 5);
		socket_set_nonblock($this->sk);
		$this->serverName = $serverName;
		$this->config = serialize($config);
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
			$this->clients["$ip:$port"] = new IRCClient($this, $con, $ip, $port, $config);
		}
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
}
