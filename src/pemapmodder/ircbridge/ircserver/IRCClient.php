<?php

namespace pemapmodder\ircbridge\ircserver;

class IRCClient{
	private $sk;
	private $ip;
	private $port;
	/** @var IRCServer */
	private $server;
	public function __construct(IRCServer $server, $socket, $ip, $port, $config){
		$this->sk = $socket;
		$this->init();
		$this->ip = $ip;
		$this->port = $port;
		$this->server = $server;
		$this->config = $config;
	}
	private function init(){
		$line = $this->read();
	}
	public function send($line){
		socket_write($this->sk, $line . "\r\n");
	}
	public function read($loop = true){
		start:
		$line = fgets($this->sk);
		if(!is_string($line) or strlen($line) === 0){
			if($loop){
				goto start;
			}
			return false;
		}
		return new CommandLine($line);
	}
}
