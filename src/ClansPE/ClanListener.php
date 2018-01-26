<?php

namespace ClansPE;

use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerDeathEvent;

class ClanListener implements Listener {
	
	public $plugin;
	
	public function __construct(ClanMain $pg) {
		$this->plugin = $pg;
	}
	
	public function clanChat(PlayerChatEvent $PCE) {
		
		$player = $PCE->getPlayer()->getName();
		//MOTD Check
		if($this->plugin->motdWaiting($player)) {
			if(time() - $this->plugin->getMOTDTime($player) > 30) {
				$PCE->getPlayer()->sendMessage($this->plugin->formatMessage("Timed out. Please use /clans desc again."));
				$this->plugin->db->query("DELETE FROM motdrcv WHERE player='$player';");
				$PCE->setCancelled(true);
				return true;
			} else {
				$motd = $PCE->getMessage();
				$clan = $this->plugin->getPlayerClan($player);
				$this->plugin->setMOTD($clan, $player, $motd);
				$PCE->setCancelled(true);
				$PCE->getPlayer()->sendMessage($this->plugin->formatMessage("Successfully updated the clan description. Type /clans info.", true));
			}
			return true;
		}
		if(isset($this->plugin->clanChatActive[$player])){
			if($this->plugin->clanChatActive[$player]){
				$msg = $PCE->getMessage();
				$clan = $this->plugin->getPlayerClan($player);
				foreach($this->plugin->getServer()->getOnlinePlayers() as $fP){
					if($this->plugin->getPlayerClan($fP->getName()) == $clan){
						if($this->plugin->getServer()->getPlayer($fP->getName())){
							$PCE->setCancelled(true);
							$this->plugin->getServer()->getPlayer($fP->getName())->sendMessage(TextFormat::DARK_GREEN."[$clan]".TextFormat::BLUE." $player: ".TextFormat::AQUA. $msg);
						}
					}
				}
			}
		}
		if(isset($this->plugin->allyChatActive[$player])){
			if($this->plugin->allyChatActive[$player]){
				$msg = $PCE->getMessage();
				$clan = $this->plugin->getPlayerClan($player);
				foreach($this->plugin->getServer()->getOnlinePlayers() as $cP){
					if($this->plugin->areAllies($this->plugin->getPlayerClan($cP->getName()), $clan)){
						if($this->plugin->getServer()->getPlayer($cP->getName())){
							$PCE->setCancelled(true);
							$this->plugin->getServer()->getPlayer($cP->getName())->sendMessage(TextFormat::DARK_GREEN."[$clan]".TextFormat::BLUE." $player: ".TextFormat::AQUA. $msg);
							$PCE->getPlayer()->sendMessage(TextFormat::DARK_GREEN."[$clan]".TextFormat::BLUE." $player: ".TextFormat::AQUA. $msg);
						}
					}
				}
			}
		}
	}
	
	public function clanPVP(EntityDamageEvent $clanDamage) {
		if($clanDamage instanceof EntityDamageByEntityEvent) {
			if(!($clanDamage->getEntity() instanceof Player) or !($clanDamage->getDamager() instanceof Player)) {
				return true;
			}
			if(($this->plugin->isInClan($clanDamage->getEntity()->getPlayer()->getName()) == false) or ($this->plugin->isInClan($clanDamage->getDamager()->getPlayer()->getName()) == false)) {
				return true;
			}
			if(($clanDamage->getEntity() instanceof Player) and ($clanDamage->getDamager() instanceof Player)) {
				$player1 = $clanDamage->getEntity()->getPlayer()->getName();
				$player2 = $clanDamage->getDamager()->getPlayer()->getName();
                		$f1 = $this->plugin->getPlayerClan($player1);
				$f2 = $this->plugin->getPlayerClan($player2);
				if((!$this->plugin->prefs->get("AllowClanPvp") && $this->plugin->sameClan($player1, $player2) == true) or (!$this->plugin->prefs->get("AllowAlliedPvp") && $this->plugin->areAllies($c1,$c2))) {
					$clanDamage->setCancelled(true);
				}
			}
		}
	}/*Disabled.
	public function clanBlockBreakProtect(BlockBreakEvent $event) {
		$x = $event->getBlock()->getX();
		$z = $event->getBlock()->getZ();
		$level = $event->getBlock()->getLevel()->getName();
		if($this->plugin->pointIsInPlot($x, $z, $level)){
			if($this->plugin->clanFromPoint($x, $z, $level) === $this->plugin->getClan($event->getPlayer()->getName())){
				return;
			}else{
				$event->setCancelled(true);
				$event->getPlayer()->sendMessage($this->plugin->formatMessage("You cannot break blocks here. This is already a property of a faction. Type /clans plotinfo for details."));
				return;
			}
		}
	}
	
	public function factionBlockPlaceProtect(BlockPlaceEvent $event) {
      		$x = $event->getBlock()->getX();
     		$z = $event->getBlock()->getZ();
     		$level = $event->getBlock()->getLevel()->getName();
		if($this->plugin->pointIsInPlot($x, $z, $level)) {
			if($this->plugin->factionFromPoint($x, $z, $level) == $this->plugin->getFaction($event->getPlayer()->getName())) {
				return;
			} else {
				$event->setCancelled(true);
				$event->getPlayer()->sendMessage($this->plugin->formatMessage("You cannot place blocks here. This is already a property of a faction. Type /f plotinfo for details."));
				return;
			}
		}
	}*/
	public function onKill(PlayerDeathEvent $event){
        $ent = $event->getEntity();
        $cause = $event->getEntity()->getLastDamageCause();
        if($cause instanceof EntityDamageByEntityEvent){
            $killer = $cause->getDamager();
            if($killer instanceof Player){
                $p = $killer->getPlayer()->getName();
                if($this->plugin->isInClan($p)){
                    $f = $this->plugin->getPlayerClan($p);
                    $e = $this->plugin->prefs->get("PowerGainedPerKillingAnEnemy");
                    if($ent instanceof Player){
                        if($this->plugin->isInClan($ent->getPlayer()->getName())){
                           $this->plugin->addClanPower($f,$e);
                        } else {
                           $this->plugin->addClanPower($f,$e/2);
                        }
                    }
                }
            }
        }
        if($ent instanceof Player){
            $e = $ent->getPlayer()->getName();
            if($this->plugin->isInClan($e)){
                $f = $this->plugin->getPlayerClan($e);
                $e = $this->plugin->prefs->get("PowerGainedPerKillingAnEnemy");
                if($ent->getLastDamageCause() instanceof EntityDamageByEntityEvent && $ent->getLastDamageCause()->getDamager() instanceof Player){
                    if($this->plugin->isInClan($ent->getLastDamageCause()->getDamager()->getPlayer()->getName())){      
                        $this->plugin->subtractClanPower($c,$e*2);
                    } else {
                        $this->plugin->subtractClanPower($c,$e);
                    }
                }
            }
        }
    }
    
	public function onPlayerJoin(PlayerJoinEvent $event) {
		$this->plugin->updateTag($event->getPlayer()->getName());
	}
}
