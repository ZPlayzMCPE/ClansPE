<?php

namespace ClansPE;

use pocketmine\scheduler\PluginTask;

class ClanWar extends PluginTask {
	
	public $plugin;
	public $requester;
	
	public function __construct(ClanMain $pl, $requester) {
        parent::__construct($pl);
        $this->plugin = $pl;
		$this->requester = $requester;
    }
	
	public function onRun(int $currentTick) {
		unset($this->plugin->wars[$this->requester]);
		$this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
	}
	
}