<?php

namespace pemapmodder\ircbridge\ircserver;

class SynchronizedArray{
	/** @var string */
	private $array;
	/** @var bool */
	private $lock = false;
	public function __construct(array $array){
		$this->array = serialize($array);
	}
	public function acquire(){
		while($this->lock);
		$this->lock = true;
	}
	public function release(){
		$this->lock = false;
	}
	public function get(){
		$this->acquire();
		$array = unserialize($this->array);
		$this->release();
		return $array;
	}
	public function set(array $array){
		$this->acquire();
		$this->array = serialize($array);
		$this->release();
	}
	public function shift(){
		$this->acquire();
		$array = unserialize($this->array);
		if(count($array) === 0){
			$this->release();
			return null;
		}
		$result = array_shift($array);
		$this->array = serialize($array);
		$this->release();
		return $result;
	}
	public function push($item){
		$this->acquire();
		$array = unserialize($this->array);
		$array[] = $item;
		$this->array = serialize($array);
		$this->release();
	}
	public function getItem($key, $default = null){
		$array = $this->get();
		return isset($array[$key]) ? $array[$key]:$default;
	}
	public function setItem($key, $value){
		$this->acquire();
		$array = unserialize($this->array);
		$array[$key] = $value;
		$this->array = serialize($array);
		$this->release();
	}
	public function count(){
		return count($this->get());
	}
}
