<?php

namespace pemapmodder\ircbridge\ircserver;

class IRCClient{
	private $sk;
	private $ip;
	private $port;
	/** @var IRCServer */
	private $server;
	public function __construct(IRCServer $server, $socket, $ip, $port){
		$this->sk = $socket;
		$this->init();
		$this->ip = $ip;
		$this->port = $port;
		$this->server = $server;
	}
	private function init(){
		
	}
	public function send($line){
		socket_write($this->sk, $line . "\r\n");
	}
	public function read(){
		return @trim(fgets($this->sk));
	}
}
