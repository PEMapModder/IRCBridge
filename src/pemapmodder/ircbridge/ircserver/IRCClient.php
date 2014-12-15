<?php

namespace pemapmodder\ircbridge\ircserver;

use pemapmodder\ircbridge\IRCBridge;
use pocketmine\Player;

class IRCClient{
	/** @var resource */
	private $sk;
	/** @var string */
	private $ip;
	/** @var int */
	private $port;
	/** @var IRCServer */
	private $server;
	/** @var array */
	private $config;
	/** @var SynchronizedArray<CommandLine> */
	private $lines;
	/** @var string */
	private $channel;
	/** @var string */
	private $nick, $ident, $realname, $hostname;
	/** @var bool */
	private $ready = false;
	/** @var \pocketmine\OfflinePlayer */
	private $offlinePlayer;
	/** @var bool */
	private $authed, $banChecked = false;
	/** @var \SimpleAuth\SimpleAuth */
	private $simpleauth;
	public function __construct(IRCServer $server, $socket, $ip, $port, $config){
		$this->sk = $socket;
		$this->ip = $ip;
		$this->port = $port;
		$this->server = $server;
		$this->config = $config;
		$this->lines = new SynchronizedArray([]);
		$this->channel = "#" . $this->config["channel name"];
		$this->authed = !$this->config["NickServ password with SimpleAuth"];
		$this->simpleauth = $this->server->getPlugin()->getServer()->getPluginManager()->getPlugin("SimpleAuth");
		$this->init();
	}
	private function init(){
		start:
		$line = $this->read();
		if(($pass = $this->config["server password"]) !== false){
			if(!($line->getCommand() === "PASS" and isset($line->getArgs()[0]) and $line->getArgs()[0] === $pass)){
				$this->sendNumeric(464, ":Password incorrect");
				goto start;
			}
		}
	}
	public function send($line){
		socket_write($this->sk, $line . "\r\n");
	}
	public function sendNumeric($num, $msg){
		$num = "$num";
		$num = str_repeat("0", max(0, 3 - strlen($num))). $num;
		$this->send(":ircbridge $num $msg");
	}
	public function sendNeedMoreParams($cmd){
		$this->sendNeedMoreParams(461, "$cmd :Not enough parameters");
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
		$line = new CommandLine($line);
		if($line->getCommand() === "PONG"){
			$this->send("PING :" . $line->getArgs()[0]);
			goto start;
		}
		elseif($line->getCommand() === "PING"){
			$this->send("PONG :" . $line->getArgs()[0]);
			goto start;
		}
		return $line;
	}
	public function getIdentifier(){
		return "$this->ip:$this->port";
	}
	public function closeConnection(){
		socket_shutdown($this->sk);
		socket_close($this->sk);
	}
	public function stackLine(){
		$line = $this->read(false);
		if($line instanceof CommandLine){
			$this->lines->push($line);
			return true;
		}
		return false;
	}
	public function mainThreadTick(IRCBridge $plugin){
		/** @var CommandLine $line */
		while(($line = $this->lines->shift()) !== null){
			switch($line->getCommand()){
				case "NICK":
					if(!isset($line->getArgs()[0])){
						$this->sendNumeric(431, ":No nickname given");
						break;
					}
					$nick = $line->getArgs()[0];
					if($plugin->getServer()->getPlayerExact($nick) instanceof Player or $this->server->getClientByNick($nick) instanceof IRCClient){
						$this->sendNumeric(433, "$nick :Nickname is already in use");
						break;
					}
					if(!preg_match('/^[a-zA-Z\\[\\]`_\\^\\{\\|\\}][a-zA-Z0-9\\[\\]`_\\^\\{\\|\\}-]{2,15}$/', $nick) or strtolower($nick) === "ircbridge"){
						$this->sendNumeric(432, "$nick :Erroneous nickname");
						break;
					}
					$this->nick = $nick;
					$this->ready($plugin);
					break;
				case "USER":
					if(!isset($line->getArgs()[3])){
						$this->sendNeedMoreParams("USER");
						break;
					}
					list($this->ident, , , $this->realname) = $line->getArgs();
					$this->hostname = "ircbridge/$this->nick";
					$this->ready($plugin);
					break;
				case "JOIN":
					if(!isset($line->getArgs()[0])){
						$this->sendNeedMoreParams("JOIN");
						break;
					}
					$this->sendNumeric(403, "{$line->getArgs()[0]} :No such channel");
					break;
				case "PART":
					if(!isset($line->getArgs()[0])){
						$this->sendNeedMoreParams("PART");
						break;
					}
					$this->sendNumeric(403, "{$line->getArgs()[0]} :No such channel");
					break;
				case "QUIT":
					$this->server->disconnect($this);
					break;
				case "PRIVMSG":
					if(!isset($line->getArgs()[1])){
						$this->sendNeedMoreParams("PRIVMSG");
						break;
					}
					list($target, $msg) = $line->getArgs();
					if(!$this->authed){
						if(strtolower($target) === "nickserv" and strtolower(substr($msg, 0, 9)) === "identify "){
							$this->authenticate(substr($msg, 9));
							break;
						}
						$this->send(":NickServ!NickServ@services. NOTICE :You need to authenticate with /msg NickServ IDENTIFY <password> first.");
						break;
					}
					// TODO send message
					break;
			}
		}
	}
	public function ready(IRCBridge $plugin){
		if(!isset($this->nick, $this->ident, $this->realname, $this->hostname)){
			return;
		}
		$this->offlinePlayer = $plugin->getServer()->getOfflinePlayer($this->nick);
		if(!$this->banChecked){
			$server = $plugin->getServer();
			if($this->offlinePlayer->isBanned() or $server->getIPBans()->isBanned($this->ip)){
				$this->sendNumeric(465, ":You are banned from this server"); // YOUREBANNEDCREEP
				return;
			}
			$this->banChecked = true;
		}
		if(!$this->authed){
			if($this->simpleauth->isPlayerRegistered($this->offlinePlayer)){
				$this->authed = true;
			}
			else{
				$this->send(":NickServ!NickServ@services. NOTICE Please authenticate with your server password with command '/msg NickServ identify <password>");
			}
		}
		$this->ready = true;
		$this->sendNumeric(1, "$this->nick :Welcome to the Internet Relay Network {$this->getFullName()}");
		$this->sendNumeric(2, "$this->nick :Your host is {$this->server->getServerName()}, running version " . str_replace(" ", "-", $plugin->getDescription()->getFullName()));
		$this->sendNumeric(3, "$this->nick :This server was created " . date("D M j Y \\a\\t H:i:s T", $this->server->getCreated()));
		$this->sendMotd($plugin);
		$this->send(":$this->nick MODE $this->nick :+i");
		$this->send(":{$this->getFullName()} JOIN $this->channel");
		$this->sendNumeric(332, "$this->channel :" . $this->config["channel topic"]);
		$this->sendNumeric(333, "$this->nick $this->channel IRCBridge {$this->server->getCreated()}");
		$this->sendNames($plugin);
	}
	public function authenticate($password){
		$data = $this->simpleauth->getDataProvider()->getPlayer($this->offlinePlayer);
		if($this->SimpleAuth__hash(strtolower($this->nick), $password) === $data["hash"]){
			$this->authed = true;
			$this->send(":NickServ!NickServ@services. NOTICE :You have been authenticated.");
		}
	}
	public function sendMotd(IRCBridge $plugin){
		$this->sendNumeric(375, "$this->nick :- ircbridge Message of the Day -");
		$this->sendNumeric(372, "$this->nick :- " . $plugin->getServer()->getMotd());
		$this->sendNumeric(376, "$this->nick :End of /MOTD command.");
	}
	public function sendNames(IRCBridge $plugin){
		$names = [];
		foreach($plugin->getServer()->getOnlinePlayers() as $player){
			if($player->isOp()){
				$names[] = "@" . $player->getName();
			}
			else{
				$names[] = $player->getName();
			}
		}
		foreach($this->server->getClients() as $client){
			if($client->isReady()){
				$names[] = ($client->isOp() ? "@":"") . $client->getNick();
			}
		}
		$this->sendNumeric(353, "$this->nick = $this->channel :" . implode(" ", $names));
	}
	public function getNick(){
		return $this->nick;
	}
	public function getIdent(){
		return $this->ident;
	}
	public function getRealname(){
		return $this->realname;
	}
	public function getHostname(){
		return $this->hostname;
	}
	public function getFullName(){
		return "$this->nick!$this->ident@$this->hostname";
	}
	public function isOp(){
		return $this->offlinePlayer->isOp();
	}
	public function isReady(){
		return $this->ready;
	}
	public function SimpleAuth__hash($salt, $password){
		return bin2hex(hash("sha512", $password . $salt, true) ^ hash("whirlpool", $salt . $password, true));
	}
}
