<?php

namespace pemapmodder\ircbridge;

use pocketmine\Thread;

class IRCServer extends Thread{
	private $sk;
	public function __construct($ip, $port){
		$this->sk = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_bind($this->sk, $ip, $port);
		socket_listen($this->sk, 5);
	}
	public function shutdown(){
		socket_close($this->sk);
	}
}
