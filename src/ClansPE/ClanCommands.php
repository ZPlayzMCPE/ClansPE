<?php

namespace ClansPE;

use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\level\Position;

class ClanCommands {

    public $plugin;

    public function __construct(ClanMain $pg) {
        $this->plugin = $pg;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if ($sender instanceof Player) {
            $playerName = $sender->getPlayer()->getName();
            if (strtolower($command->getName()) === "clans") {
                if (empty($args)) {
                    $sender->sendMessage($this->plugin->formatMessage("§bPlease use §3/clans help §6for a list of commands"));
                    return true;
                }

                    ///////////////////////////////// WAR /////////////////////////////////

                    if ($args[0] == "war") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§5Please use: §d/clans war <faction name:tp>"));
                            return true;
                        }
                        if (strtolower($args[1]) == "tp") {
                            foreach ($this->plugin->wars as $r => $c) {
                                $clan = $this->plugin->getPlayerClan($playerName);
                                if ($r == $clan) {
                                    $x = mt_rand(0, $this->plugin->getNumberOfPlayers($clan) - 1);
                                    $tper = $this->plugin->war_players[$c][$x];
                                    $sender->teleport($this->plugin->getServer()->getPlayerByName($tper));
                                    return true;
                                }
                                if ($c == $clan) {
                                    $x = mt_rand(0, $this->plugin->getNumberOfPlayers($clan) - 1);
                                    $tper = $this->plugin->war_players[$r][$x];
                                    $sender->teleport($this->plugin->getServer()->getPlayer($tper));
                                    return true;
                                }
                            }
                            $sender->sendMessage("§cYou must be in a war to do that");
                            return true;
                        }
                        if (!($this->alphanum($args[1]))) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou may only use letters and numbers"));
                            return true;
                        }
                        if (!$this->plugin->clanExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cClan does not exist"));
                            return true;
                        }
                        if (!$this->plugin->isInClan($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cOnly your clan leader may start wars"));
                            return true;
                        }
                        if (!$this->plugin->areEnemies($this->plugin->getPlayerClan($playerName), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan is not an enemy of §2$args[1]"));
                            return true;
                        } else {
                            $clanName = $args[1];
                            $sClan = $this->plugin->getPlayerClan($playerName);
                            foreach ($this->plugin->war_req as $r => $c) {
                                if ($r == $args[1] && $c == $sClan) {
                                    foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                                        $task = new ClanWar($this->plugin, $r);
                                        $handler = $this->plugin->getServer()->getScheduler()->scheduleDelayedTask($task, 20 * 60 * 2);
                                        $task->setHandler($handler);
                                        $p->sendMessage("§aThe war against §2$clanName §aand §2$sClan §ahas started!");
                                        if ($this->plugin->getPlayerClan($p->getName()) == $sClan) {
                                            $this->plugin->war_players[$sClan][] = $p->getName();
                                        }
                                        if ($this->plugin->getPlayerClan($p->getName()) == $clanName) {
                                            $this->plugin->war_players[$clanName][] = $p->getName();
                                        }
                                    }
                                    $this->plugin->wars[$clanName] = $sClan;
                                    unset($this->plugin->war_req[strtolower($args[1])]);
                                    return true;
                                }
                            }
                            $this->plugin->war_req[$sClan] = $clanName;
                            foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                                if ($this->plugin->getPlayerClan($p->getName()) == $clanName) {
                                    if ($this->plugin->getLeader($clanName) == $p->getName()) {
                                        $p->sendMessage("§2$sClan §awants to start a war. §bPlease use: §3'/clans war $sClan' §bto start!");
                                        $sender->sendMessage("§aThe Faction war has been requested. §bPlease wait for their response.");
                                        return true;
                                    }
                                }
                            }
                            $sender->sendMessage("§cClan leader is not online.");
                            return true;
                        }
                    }

                    /////////////////////////////// CREATE ///////////////////////////////

                    if ($args[0] == "create") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bPlease use: §3/clans create <clan name>"));
                            return true;
                        }
                        if (!($this->alphanum($args[1]))) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou may only use letters and numbers"));
                            return true;
                        }
                        if ($this->plugin->isNameBanned($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThis name is not allowed"));
                            return true;
                        }
                        if ($this->plugin->clanExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe Clan already exists"));
                            return true;
                        }
                        if (strlen($args[1]) > $this->plugin->prefs->get("MaxClanNameLength")) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThat name is too long, please try again"));
                            return true;
                        }
                        if ($this->plugin->isInClan($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must leave the clan first"));
                            return true;
                        } else {
                            $clanName = $args[1];
                            $rank = "Leader";
                            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, clan, rank) VALUES (:player, :clan, :rank);");
                            $stmt->bindValue(":player", $playerName);
                            $stmt->bindValue(":clan", $clanName);
                            $stmt->bindValue(":rank", $rank);
                            $result = $stmt->execute();
                            $this->plugin->updateAllies($clanName);
                            $this->plugin->setClanPower($clanName, $this->plugin->prefs->get("TheDefaultPowerEveryClanStartsWith"));
                            $this->plugin->updateTag($sender->getName());
                            $sender->sendMessage($this->plugin->formatMessage("§aThe Clan named §2$clanName §ahas been created", true));
                            return true;
                        }
                    }

                    /////////////////////////////// INVITE ///////////////////////////////

                    if ($args[0] == "invite") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bPlease use: §3/clans invite <player>"));
                            return true;
                        }
                        if ($this->plugin->isClanFull($this->plugin->getPlayerClan($playerName))) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThis clan is full, please kick players to make room"));
                            return true;
                        }
                        $invited = $this->plugin->getServer()->getPlayer($args[1]);
                        if (!($invited instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer not online"));
                            return true;
                        }
                        if ($this->plugin->isInClan($invited->getName()) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer is currently in a clan"));
                            return true;
                        }
                        if ($this->plugin->prefs->get("OnlyLeadersAndOfficersCanInvite")) {
                            if (!($this->plugin->isOfficer($playerName) || $this->plugin->isLeader($playerName))) {
                                $sender->sendMessage($this->plugin->formatMessage("§cOnly your clan leader/officers can invite"));
                                return true;
                            }
                        }
                        if ($invited->getName() == $playerName) {

                            $sender->sendMessage($this->plugin->formatMessage("§cYou can't invite yourself to your own clan"));
                            return true;
                        }

                        $clanName = $this->plugin->getPlayerClan($playerName);
                        $invitedName = $invited->getName();
                        $rank = "Member";

                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO confirm (player, clan, invitedby, timestamp) VALUES (:player, :clan, :invitedby, :timestamp);");
                        $stmt->bindValue(":player", $invitedName);
                        $stmt->bindValue(":clan", $clanName);
                        $stmt->bindValue(":invitedby", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage("§2$invitedName §ahas been invited succesfully", true));
                        $invited->sendMessage($this->plugin->formatMessage("§bYou have been invited to §3$clanName. §bType §3'/clans accept' or '/clans deny' §binto chat to accept or deny!", true));
                    }

                    /////////////////////////////// LEADER ///////////////////////////////

                    if ($args[0] == "leader") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bPlease use: §3/clans leader <player>"));
                            return true;
                        }
                        if (!$this->plugin->isInClan($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this"));
                            return true;
                        }
                        if ($this->plugin->getPlayerClan($playerName) != $this->plugin->getPlayerClan($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cAdd player to Clan first"));
                            return true;
                        }
                        if (!($this->plugin->getServer()->getPlayer($args[1]) instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe player named §4$playerName is not online"));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {

                            $sender->sendMessage($this->plugin->formatMessage("§cYou can't transfer the leadership to yourself"));
                            return true;
                        }
                        $clanName = $this->plugin->getPlayerClan($playerName);

                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, clan, rank) VALUES (:player, :clan, :rank);");
                        $stmt->bindValue(":player", $playerName);
                        $stmt->bindValue(":clan", $clanName);
                        $stmt->bindValue(":rank", "Member");
                        $result = $stmt->execute();

                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, clan, rank) VALUES (:player, :clan, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":clan", $clanName);
                        $stmt->bindValue(":rank", "Leader");
                        $result = $stmt->execute();


                        $sender->sendMessage($this->plugin->formatMessage("§2You are no longer leader", true));
                        $this->plugin->getServer()->getPlayer($args[1])->sendMessage($this->plugin->formatMessage("§aYou are now leader \nof $clanName!", true));
                        $this->plugin->updateTag($sender->getName());
                        $this->plugin->updateTag($this->plugin->getServer()->getPlayer($args[1])->getName());
                    }

                    /////////////////////////////// PROMOTE ///////////////////////////////

                    if ($args[0] == "promote") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bPlease use: §3/clans promote <player>"));
                            return true;
                        }
                        if (!$this->plugin->isInClan($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this"));
                            return true;
                        }
                        if ($this->plugin->getPlayerClan($playerName) != $this->plugin->getPlayerClan($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe player named: §4$playerName §cis not in this clan"));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou can't promote yourself"));
                            return true;
                        }

                        if ($this->plugin->isOfficer($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer is already Officer"));
                            return true;
                        }
                        $clanName = $this->plugin->getPlayerClan($playerName);
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, clan, rank) VALUES (:player, :clan, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":clan", $clanName);
                        $stmt->bindValue(":rank", "Officer");
                        $result = $stmt->execute();
                        $promotee = $this->plugin->getServer()->getPlayer($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("§2$args[1] §ahas been promoted to Officer", true));

                        if ($promotee instanceof Player) {
                            $promotee->sendMessage($this->plugin->formatMessage("§aYou were promoted to officer of §2$clanName!", true));
                            $this->plugin->updateTag($this->plugin->getServer()->getPlayer($args[1])->getName());
                            return true;
                        }
                    }

                    /////////////////////////////// DEMOTE ///////////////////////////////

                    if ($args[0] == "demote") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bPlease use: §3/clans demote <player>"));
                            return true;
                        }
                        if ($this->plugin->isInClan($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this"));
                            return true;
                        }
                        if ($this->plugin->isLeader($playerName) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this"));
                            return true;
                        }
                        if ($this->plugin->getPlayerClan($playerName) != $this->plugin->getPlayerClan($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe player named: §4$playerName §cis not in this clan"));
                            return true;
                        }

                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou can't demote yourself"));
                            return true;
                        }
                        if (!$this->plugin->isOfficer($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cPlayer is already Member"));
                            return true;
                        }
                        $clanName = $this->plugin->getPlayerClan($playerName);
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, clan, rank) VALUES (:player, :clan, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":clan", $clanName);
                        $stmt->bindValue(":rank", "Member");
                        $result = $stmt->execute();
                        $demotee = $this->plugin->getServer()->getPlayer($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("§5$args[1] §2has been demoted to Member", true));
                        if ($demotee instanceof Player) {
                            $demotee->sendMessage($this->plugin->formatMessage("§2You were demoted to member of §5$clanName!", true));
                            $this->plugin->updateTag($this->plugin->getServer()->getPlayer($args[1])->getName());
                            return true;
                        }
                    }

                    /////////////////////////////// KICK ///////////////////////////////

                    if ($args[0] == "kick") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bPlease use: §3/clans kick <player>"));
                            return true;
                        }
                        if ($this->plugin->isInClan($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this"));
                            return true;
                        }
                        if ($this->plugin->isLeader($playerName) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this"));
                            return true;
                        }
                        if ($this->plugin->getPlayerClan($playerName) != $this->plugin->getPlayerClan($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The Player §4$playerName §cis not in this clan"));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou can't kick yourself"));
                            return true;
                        }
                        $kicked = $this->plugin->getServer()->getPlayer($args[1]);
                        $clanName = $this->plugin->getPlayerClan($playerName);
                        $this->plugin->db->query("DELETE FROM master WHERE player='$args[1]';");
                        $sender->sendMessage($this->plugin->formatMessage("§aYou successfully kicked §2$args[1]", true));
                        $this->plugin->subtractClanPower($clanName, $this->plugin->prefs->get("PowerGainedPerPlayerInClan"));

                        if ($kicked instanceof Player) {
                            $kicked->sendMessage($this->plugin->formatMessage("§2You have been kicked from \n §5$clanName", true));
                            $this->plugin->updateTag($this->plugin->getServer()->getPlayer($args[1])->getName());
                            return true;
                        }
                    }



                    /////////////////////////////// CLAIM ///////////////////////////////
                    /*
                    if (strtolower($args[0]) == 'claim') {
                        if (!$this->plugin->isInFaction($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a faction."));
                            return true;
                        }
                        if (!$this->plugin->isLeader($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this."));
                            return true;
                        }
                        if (!in_array($sender->getPlayer()->getLevel()->getName(), $this->plugin->prefs->get("ClaimWorlds"))) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou can only claim in Faction Worlds: " . implode(" ", $this->plugin->prefs->get("ClaimWorlds"))));
                            return true;
                        }

                        if ($this->plugin->inOwnPlot($sender)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour faction has already claimed this area."));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($sender->getPlayer()->getName());
                        if ($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")) {

                            $needed_players = $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") -
                                    $this->plugin->getNumberOfPlayers($faction);
                            $sender->sendMessage($this->plugin->formatMessage("§cYou need §4$needed_players §cmore players in your faction to claim a faction plot"));
                            return true;
                        }
                        if ($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")) {
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
                            $sender->sendMessage($this->plugin->formatMessage("§cYour faction doesn't have enough STR to claim a land."));
                            $sender->sendMessage($this->plugin->formatMessage("§4$needed_power §cSTR is required but your faction has only §4$faction_power §cSTR."));
                            return true;
                        }

                        $x = floor($sender->getX());
                        $y = floor($sender->getY());
                        $z = floor($sender->getZ());
                        if ($this->plugin->drawPlot($sender, $faction, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->plugin->prefs->get("PlotSize")) == false) {

                            return true;
                        }

                        $sender->sendMessage($this->plugin->formatMessage("Getting your coordinates...", true));
                        $plot_size = $this->plugin->prefs->get("PlotSize");
                        $faction_power = $this->plugin->getFactionPower($faction);
                        $sender->sendMessage($this->plugin->formatMessage("Your land has been claimed.", true));
                    }
                    if (strtolower($args[0]) == 'plotinfo') {
                        $x = floor($sender->getX());
                        $y = floor($sender->getY());
                        $z = floor($sender->getZ());
                        if (!$this->plugin->isInPlot($sender)) {
                            $sender->sendMessage($this->plugin->formatMessage("§5This plot is not claimed by anyone. §dYou can claim it by typing §5/f claim", true));
                            return true;
                        }

                        $fac = $this->plugin->factionFromPoint($x, $z, $sender->getPlayer()->getLevel()->getName());
                        $power = $this->plugin->getFactionPower($fac);
                        $sender->sendMessage($this->plugin->formatMessage("§aThis plot is claimed by §2$fac §awith §2$power §aSTR"));
                    }
                    if (strtolower($args[0]) == 'top') {
                        $this->plugin->sendListOfTop10FactionsTo($sender);
                    }
                    if (strtolower($args[0]) == 'forcedelete') {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bPlease use: §3/f forcedelete <faction>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested faction doesn't exist."));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be OP to do this."));
                            return true;
                        }
                        $this->plugin->db->query("DELETE FROM master WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction1='$args[1]';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction2='$args[1]';");
                        $this->plugin->db->query("DELETE FROM strength WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM motd WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM home WHERE faction='$args[1]';");
                        $sender->sendMessage($this->plugin->formatMessage("§aUnwanted faction was successfully deleted and their faction plot was unclaimed! §bUsing /f forcedelete is not allowed. If you do use this command, please tell Zeao right away. It is not acceptable.", true));
                    }
                    if (strtolower($args[0]) == 'addstrto') {
                        if (!isset($args[1]) or ! isset($args[2])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bPlease use: §3/f addstrto <faction> <STR>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested faction doesn't exist."));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be OP to do this."));
                            return true;
                        }
                        $this->plugin->addFactionPower($args[1], $args[2]);
                        $sender->sendMessage($this->plugin->formatMessage("§aSuccessfully added §2$args[2] §aSTR to §2$args[1]", true));
                    }Removing*/
                    if (strtolower($args[0]) == 'pc') {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bPlease use: §3/clans pc <player>"));
                            return true;
                        }
                        if (!$this->plugin->isInClan($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe selected player is not in a clan or doesn't exist."));
                            $sender->sendMessage($this->plugin->formatMessage("§cMake sure the name of the selected player is spelled EXACTLY."));
                            return true;
                        }
                        $clan = $this->plugin->getPlayerClan($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("§3-$args[1] §bis in the clan: §3$clan-", true));
                    }
/*Removing
                    if (strtolower($args[0]) == 'overclaim') {
                        if (!$this->plugin->isInFaction($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a faction."));
                            return true;
                        }
                        if (!$this->plugin->isLeader($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this."));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($playerName);
                        if ($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")) {

                            $needed_players = $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") -
                                    $this->plugin->getNumberOfPlayers($faction);
                            $sender->sendMessage($this->plugin->formatMessage("§cYou need §4$needed_players §cmore players in your faction to overclaim a faction plot"));
                            return true;
                        }
                        if ($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")) {
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
                            $sender->sendMessage($this->plugin->formatMessage("§cYour faction doesn't have enough STR to claim a land."));
                            $sender->sendMessage($this->plugin->formatMessage("§4$needed_power §cSTR is required but your faction has only §4$faction_power §cSTR."));
                            return true;
                        }
                        $sender->sendMessage($this->plugin->formatMessage("§bGetting your coordinates... Please wait..", true));
                        $x = floor($sender->getX());
                        $y = floor($sender->getY());
                        $z = floor($sender->getZ());
                        if ($this->plugin->prefs->get("EnableOverClaim")) {
                            if ($this->plugin->isInPlot($sender)) {
                                $faction_victim = $this->plugin->factionFromPoint($x, $z, $sender->getPlayer()->getLevel()->getName());
                                $faction_victim_power = $this->plugin->getFactionPower($faction_victim);
                                $faction_ours = $this->plugin->getPlayerFaction($playerName);
                                $faction_ours_power = $this->plugin->getFactionPower($faction_ours);
                                if ($this->plugin->inOwnPlot($sender)) {
                                    $sender->sendMessage($this->plugin->formatMessage("§cYou can't overclaim your own plot."));
                                    return true;
                                } else {
                                    if ($faction_ours_power < $faction_victim_power) {
                                        $sender->sendMessage($this->plugin->formatMessage("§cYou can't overclaim the plot of §4$faction_victim §cbecause your STR is lower than theirs."));
                                        return true;
                                    } else {
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_ours';");
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_victim';");
                                        $arm = (($this->plugin->prefs->get("PlotSize")) - 1) / 2;
                                        $this->plugin->newPlot($faction_ours, $x1 + $arm, $z1 + $arm, $x2 - $arm, $z2 - $arm);
                                        $sender->sendMessage($this->plugin->formatMessage("The land of $faction_victim has been claimed. It is now yours.", true));
                                        return true;
                                    }
                                }
                            } else {
                                $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a faction plot."));
                                return true;
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cOverclaiming is disabled."));
                            return true;
                        }
                    }


                    /////////////////////////////// UNCLAIM ///////////////////////////////

                    if (strtolower($args[0]) == "unclaim") {
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a faction"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this"));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($sender->getName());
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
                        $sender->sendMessage($this->plugin->formatMessage("§2Your land has been unclaimed", true));
                    }
*/
                    /////////////////////////////// DESCRIPTION ///////////////////////////////

                    if (strtolower($args[0]) == "desc") {
                        if ($this->plugin->isInClan($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this!"));
                            return true;
                        }
                        if ($this->plugin->isLeader($playerName) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this"));
                            return true;
                        }
                        $sender->sendMessage($this->plugin->formatMessage("Type your message in chat. It will not be visible to other players", true));
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
                        $stmt->bindValue(":player", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                    }

                    /////////////////////////////// ACCEPT ///////////////////////////////

                    if (strtolower($args[0]) == "accept") {
                        $lowercaseName = strtolower($playerName);
                        $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou have not been invited to any clans"));
                            return true;
                        }
                        $invitedTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $invitedTime) <= 60) { //This should be configurable
                            $clan = $array["clan"];
                            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, clan, rank) VALUES (:player, :clan, :rank);");
                            $stmt->bindValue(":player", ($playerName));
                            $stmt->bindValue(":clan", $clan);
                            $stmt->bindValue(":rank", "Member");
                            $result = $stmt->execute();
                            $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage("§aYou successfully joined §2$clan", true));
                            $this->plugin->addClanPower($clan, $this->plugin->prefs->get("PowerGainedPerPlayerInClan"));
                            $this->plugin->getServer()->getPlayer($array["invitedby"])->sendMessage($this->plugin->formatMessage("§2$playerName §ajoined the clan", true));
                            $this->plugin->updateTag($sender->getName());
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cInvite has timed out"));
                            $this->plugin->db->query("DELETE FROM confirm WHERE player='$playerName';");
                        }
                    }

                    /////////////////////////////// DENY ///////////////////////////////

                    if (strtolower($args[0]) == "deny") {
                        $lowercaseName = strtolower($playerName);
                        $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou have not been invited to any clans"));
                            return true;
                        }
                        $invitedTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $invitedTime) <= 60) { //This should be configurable
                            $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage("§cInvite declined", true));
                            $this->plugin->getServer()->getPlayer($array["invitedby"])->sendMessage($this->plugin->formatMessage("§4$playerName §cdeclined the invitation"));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cInvite has timed out"));
                            $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                        }
                    }

                    /////////////////////////////// DELETE ///////////////////////////////

                    if (strtolower($args[0]) == "disband") {
                        if ($this->plugin->isInClan($playerName) == true) {
                            if ($this->plugin->isLeader($playerName)) {
                                $clan = $this->plugin->getPlayerClan($playerName);
                                $this->plugin->db->query("DELETE FROM plots WHERE clan='$clan';");
                                $this->plugin->db->query("DELETE FROM master WHERE clan='$clan';");
                                $this->plugin->db->query("DELETE FROM allies WHERE clan1='$clan';");
                                $this->plugin->db->query("DELETE FROM allies WHERE clan2='$clan';");
                                $this->plugin->db->query("DELETE FROM strength WHERE clan='$clan';");
                                $this->plugin->db->query("DELETE FROM motd WHERE clan='$clan';");
                                $this->plugin->db->query("DELETE FROM home WHERE clan='$clan';");
                                $sender->sendMessage($this->plugin->formatMessage("§2The Clan named: §5$clan §2has been successfully disbanded", true));
                                $this->plugin->updateTag($sender->getName());
                            } else {
                                $sender->sendMessage($this->plugin->formatMessage("§cYou are not leader!"));
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou are not in a clan!"));
                        }
                    }

                    /////////////////////////////// LEAVE ///////////////////////////////

                    if (strtolower($args[0] == "leave")) {
                        if ($this->plugin->isLeader($playerName) == false) {
                            $remove = $sender->getPlayer()->getNameTag();
                            $clan = $this->plugin->getPlayerClan($playerName);
                            $name = $sender->getName();
                            $this->plugin->db->query("DELETE FROM master WHERE player='$name';");
                            $sender->sendMessage($this->plugin->formatMessage("§2You successfully left §5$clan", true));

                            $this->plugin->subtractClanPower($clan, $this->plugin->prefs->get("PowerGainedPerPlayerInClan"));
                            $this->plugin->updateTag($sender->getName());
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must delete the clan or give\nleadership to someone else first"));
                        }
                    }
/*Removing
                    /////////////////////////////// SETHOME ///////////////////////////////

                    if (strtolower($args[0] == "sethome")) {
                        if (!$this->plugin->isInFaction($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a faction to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to set home"));
                            return true;
                        }
                        $factionName = $this->plugin->getPlayerFaction($sender->getName());
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO home (faction, x, y, z, world) VALUES (:faction, :x, :y, :z, :world);");
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":x", $sender->getX());
                        $stmt->bindValue(":y", $sender->getY());
                        $stmt->bindValue(":z", $sender->getZ());
                        $stmt->bindValue(":world", $sender->getLevel()->getName());
                        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage("§aHome set succesfully. §bNow, you can use: §3/f home", true));
                    }

                    /////////////////////////////// UNSETHOME ///////////////////////////////

                    if (strtolower($args[0] == "unsethome")) {
                        if (!$this->plugin->isInFaction($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a faction to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to unset home"));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($sender->getName());
                        $this->plugin->db->query("DELETE FROM home WHERE faction = '$faction';");
                        $sender->sendMessage($this->plugin->formatMessage("§aHome unset succesfully. §3/f home §bwas removed from your faction.", true));
                    }

                    /////////////////////////////// HOME ///////////////////////////////

                    if (strtolower($args[0] == "home")) {
                        if (!$this->plugin->isInFaction($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a faction to do this"));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($sender->getName());
                        $result = $this->plugin->db->query("SELECT * FROM home WHERE faction = '$faction';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (!empty($array)) {
                        	if ($array['world'] === null || $array['world'] === ""){
								$sender->sendMessage($this->plugin->formatMessage("Home is missing world name, please delete and make it again"));
								return true;
							}
							if(Server::getInstance()->loadLevel($array['world']) === false){
								$sender->sendMessage($this->plugin->formatMessage("The world '" . $array['world'] .  "'' could not be found"));
								return true;
							}
							$level = Server::getInstance()->getLevelByName($array['world']);
                            $sender->getPlayer()->teleport(new Position($array['x'], $array['y'], $array['z'], $level));
                            $sender->sendMessage($this->plugin->formatMessage("§bTeleported to your faction home", true));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cHome is currently not set Set it by using /f sethome"));
                        }
                    }
*/
                    /////////////////////////////// MEMBERS/OFFICERS/LEADER AND THEIR STATUSES ///////////////////////////////
                    if (strtolower($args[0] == "ourmembers")) {
                        if (!$this->plugin->isInClan($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a Clan to do this"));
                            return true;
                        }
                        $this->plugin->getPlayersInClanByRank($sender, $this->plugin->getPlayerClan($playerName), "Member");
                    }
                    if (strtolower($args[0] == "listmembers")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bPlease use: §3/clans listmembers <clan>"));
                            return true;
                        }
                        if (!$this->plugin->clanExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested Clan doesn't exist"));
                            return true;
                        }
                        $this->plugin->getPlayersInClanByRank($sender, $args[1], "Member");
                    }
                    if (strtolower($args[0] == "ourofficers")) {
                        if (!$this->plugin->isInClan($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a Clan to do this"));
                            return true;
                        }
                        $this->plugin->getPlayersInClanByRank($sender, $this->plugin->getPlayerClan($playerName), "Officer");
                    }
                    if (strtolower($args[0] == "listofficers")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bPlease use: §3/clans listofficers <clan>"));
                            return true;
                        }
                        if (!$this->plugin->clanExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist"));
                            return true;
                        }
                        $this->plugin->getPlayersInClanByRank($sender, $args[1], "Officer");
                    }
                    if (strtolower($args[0] == "ourleader")) {
                        if (!$this->plugin->isInClan($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                            return true;
                        }
                        $this->plugin->getPlayersInClanByRank($sender, $this->plugin->getPlayerClan($playerName), "Leader");
                    }
                    if (strtolower($args[0] == "listleader")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bPlease use: §3/clans listleader <clans>"));
                            return true;
                        }
                        if (!$this->plugin->clanExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist"));
                            return true;
                        }
                        $this->plugin->getPlayersInClanByRank($sender, $args[1], "Leader");
                    }
                    if (strtolower($args[0] == "say")) {
                        if (!$this->plugin->prefs->get("AllowChat")) {
                            $sender->sendMessage($this->plugin->formatMessage("§c/clans say is disabled"));
                            return true;
                        }
                        if (!($this->plugin->isInClan($playerName))) {

                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to send clan messages"));
                            return true;
                        }
                        $r = count($args);
                        $row = array();
                        $rank = "";
                        $c = $this->plugin->getPlayerClan($playerName);

                        if ($this->plugin->isOfficer($playerName)) {
                            $rank = "*";
                        } else if ($this->plugin->isLeader($playerName)) {
                            $rank = "**";
                        }
                        $message = "-> ";
                        for ($i = 0; $i < $r - 1; $i = $i + 1) {
                            $message = $message . $args[$i + 1] . " ";
                        }
                        $result = $this->plugin->db->query("SELECT * FROM master WHERE clan='$c';");
                        for ($i = 0; $resultArr = $result->fetchArray(SQLITE3_ASSOC); $i = $i + 1) {
                            $row[$i]['player'] = $resultArr['player'];
                            $p = $this->plugin->getServer()->getPlayer($row[$i]['player']);
                            if ($p instanceof Player) {
                                $p->sendMessage(TextFormat::ITALIC . TextFormat::RED . "<CM>" . TextFormat::AQUA . " <$rank$f> " . TextFormat::GREEN . "<$playerName> " . ": " . TextFormat::RESET);
                                $p->sendMessage(TextFormat::ITALIC . TextFormat::DARK_AQUA . $message . TextFormat::RESET);
                            }
                        }
                    }


                    ////////////////////////////// ALLY SYSTEM ////////////////////////////////
                    if (strtolower($args[0] == "enemy")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bPlease use: §3/clans enemy <clan>"));
                            return true;
                        }
                        if (!$this->plugin->isInClan($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be the leader to do this"));
                            return true;
                        }
                        if (!$this->plugin->clanExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist"));
                            return true;
                        }
                        if ($this->plugin->getPlayerClan($playerName) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan can not enemy with itself"));
                            return true;
                        }
                        if ($this->plugin->areAllies($this->plugin->getPlayerClan($playerName), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan is already enemied with §4$args[1]"));
                            return true;
                        }
                        $clan = $this->plugin->getPlayerClan($playerName);
                        $leader = $this->plugin->getServer()->getPlayer($this->plugin->getLeader($args[1]));

                        if (!($leader instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe leader of the requested clan is offline"));
                            return true;
                        }
                        $this->plugin->setEnemies($clan, $args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("§aYou are now enemies with §2$args[1]!", true));
                        $leader->sendMessage($this->plugin->formatMessage("§aThe leader of §2$clan §ahas declared your clan as an enemy", true));
                    }
                    if (strtolower($args[0] == "ally")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bPlease use: §3/clans ally <clan>"));
                            return true;
                        }
                        if (!$this->plugin->isInClan($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be the leader to do this"));
                            return true;
                        }
                        if (!$this->plugin->clanExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist"));
                            return true;
                        }
                        if ($this->plugin->getPlayerClan($playerName) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour faction can not ally with itself"));
                            return true;
                        }
                        if ($this->plugin->areAllies($this->plugin->getPlayerClan($playerName), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan is already allied with §4$args[1]"));
                            return true;
                        }
                        $clan = $this->plugin->getPlayerClan($playerName);
                        $leader = $this->plugin->getServer()->getPlayer($this->plugin->getLeader($args[1]));
                        $this->plugin->updateAllies($clan);
                        $this->plugin->updateAllies($args[1]);

                        if (!($leader instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe leader of the requested clan is offline"));
                            return true;
                        }
                        if ($this->plugin->getAlliesCount($args[1]) >= $this->plugin->getAlliesLimit()) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested faction has the maximum amount of allies", false));
                            return true;
                        }
                        if ($this->plugin->getAlliesCount($clan) >= $this->plugin->getAlliesLimit()) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan has the maximum amount of allies", false));
                            return true;
                        }
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO alliance (player, clan, requestedby, timestamp) VALUES (:player, :clan, :requestedby, :timestamp);");
                        $stmt->bindValue(":player", $leader->getName());
                        $stmt->bindValue(":clan", $args[1]);
                        $stmt->bindValue(":requestedby", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage("§aYou requested to ally with §2$args[1]!\n§aWait for the leader's response...", true));
                        $leader->sendMessage($this->plugin->formatMessage("§bThe leader of §3$clan §brequested an alliance.\nType §3/clans allyok §bto accept or §3/clans allyno §bto deny.", true));
                    }
                    if (strtolower($args[0] == "unally")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bPlease use: §3/clans unally <clan>"));
                            return true;
                        }
                        if (!$this->plugin->isInClan($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be the leader to do this"));
                            return true;
                        }
                        if (!$this->plugin->clanExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist"));
                            return true;
                        }
                        if ($this->plugin->getPlayerClan($playerName) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan can not break alliance with itself"));
                            return true;
                        }
                        if (!$this->plugin->areAllies($this->plugin->getPlayerClan($playerName), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan can not break alliance with itself"));
                            return true;
                        }

                        $clan = $this->plugin->getPlayerClan($playerName);
                        $leader = $this->plugin->getServer()->getPlayer($this->plugin->getLeader($args[1]));
                        $this->plugin->deleteAllies($clan, $args[1]);
                        $this->plugin->deleteAllies($args[1], $clan);
                        $this->plugin->subtractClanPower($clan, $this->plugin->prefs->get("PowerGainedPerAlly"));
                        $this->plugin->subtractClanPower($args[1], $this->plugin->prefs->get("PowerGainedPerAlly"));
                        $this->plugin->updateAllies($clan);
                        $this->plugin->updateAllies($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("§2Your clan §5$clan §2is no longer allied with §5$args[1]", true));
                        if ($leader instanceof Player) {
                            $leader->sendMessage($this->plugin->formatMessage("§2The leader of §5$clan §2broke the alliance with your clan §5$args[1]", false));
                        }
                    }/*Disabled // Removed.
                    if (strtolower($args[0] == "forceunclaim")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bPlease use: §3/f forceunclaim <faction>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested faction doesn't exist"));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be OP to do this."));
                            return true;
                        }
                        $sender->sendMessage($this->plugin->formatMessage("§aSuccessfully unclaimed the unwanted plot of §2$args[1]"));
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                    }*/

                    if (strtolower($args[0] == "allies")) {
                        if (!isset($args[1])) {
                            if (!$this->plugin->isInClan($playerName)) {
                                $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                                return true;
                            }

                            $this->plugin->updateAllies($this->plugin->getPlayerClan($playerName));
                            $this->plugin->getAllAllies($sender, $this->plugin->getPlayerClan($playerName));
                        } else {
                            if (!$this->plugin->clanExists($args[1])) {
                                $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist"));
                                return true;
                            }
                            $this->plugin->updateAllies($args[1]);
                            $this->plugin->getAllAllies($sender, $args[1]);
                        }
                    }
                    if (strtolower($args[0] == "allyok")) {
                        if (!$this->plugin->isInClan($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be a leader to do this"));
                            return true;
                        }
                        $lowercaseName = strtolower($playerName);
                        $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan has not been requested to ally with any clans"));
                            return true;
                        }
                        $allyTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $allyTime) <= 60) { //This should be configurable
                            $requested_clan = $this->plugin->getPlayerClan($array["requestedby"]);
                            $sender_clan = $this->plugin->getPlayerClan($playerName);
                            $this->plugin->setAllies($requested_clan, $sender_clan);
                            $this->plugin->setAllies($sender_clan, $requested_clan);
                            $this->plugin->addClanPower($sender_clan, $this->plugin->prefs->get("PowerGainedPerAlly"));
                            $this->plugin->addClanPower($requested_clan, $this->plugin->prefs->get("PowerGainedPerAlly"));
                            $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                            $this->plugin->updateAllies($requested_clan);
                            $this->plugin->updateAllies($sender_clan);
                            $sender->sendMessage($this->plugin->formatMessage("Your clan has successfully allied with $requested_clan", true));
                            $this->plugin->getServer()->getPlayer($array["requestedby"])->sendMessage($this->plugin->formatMessage("§2$playerName §afrom §2$sender_clan §ahas accepted the alliance!", true));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cRequest has timed out"));
                            $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                        }
                    }
                    if (strtolower($args[0]) == "allyno") {
                        if (!$this->plugin->isInClan($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be a leader to do this"));
                            return true;
                        }
                        $lowercaseName = strtolower($playerName);
                        $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan has not been requested to ally with any clans"));
                            return true;
                        }
                        $allyTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $allyTime) <= 60) { //This should be configurable
                            $requested_clan = $this->plugin->getPlayerClan($array["requestedby"]);
                            $sender_clan = $this->plugin->getPlayerClan($playerName);
                            $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage("§aYour clan has successfully declined the alliance request.", true));
                            $this->plugin->getServer()->getPlayer($array["requestedby"])->sendMessage($this->plugin->formatMessage("§2$playerName §afrom §2$sender_clan §ahas declined the alliance!"));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cRequest has timed out"));
                            $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                        }
                    }


                    /////////////////////////////// ABOUT ///////////////////////////////

                    if (strtolower($args[0] == 'about')) {
                        $sender->sendMessage(TextFormat::GREEN . "[ORIGINAL] FactionsPro Plugin, by " . TextFormat::BOLD . "Tethered_");
                        $sender->sendMessage(TextFormat::AQUA . "[MODDED] Plugin name: ClansPE, made by " . TextFormat::BOLD . "VMPE Development Team");
                    }
                    ////////////////////////////// CHAT ////////////////////////////////
		    
                    if (strtolower($args[0]) == "chat" or strtolower($args[0]) == "c") {
                        if (!$this->plugin->prefs->get("AllowChat")){
                            $sender->sendMessage($this->plugin->formatMessage("All Clan chat is disabled", false));
                            return true;
                        }
                        
                        if ($this->plugin->isInClan($playerName)) {
                            if (isset($this->plugin->clanChatActive[$playerName])) {
                                unset($this->plugin->clanChatActive[$playerName]);
                                $sender->sendMessage($this->plugin->formatMessage("Clan chat disabled", false));
                                return true;
                            } else {
                                $this->plugin->clanChatActive[$playerName] = 1;
                                $sender->sendMessage($this->plugin->formatMessage("§aClan chat enabled", false));
                                return true;
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("You are not in a clan"));
                            return true;
                        }
                    }
                    if (strtolower($args[0]) == "allychat" or strtolower($args[0]) == "ac") {
                        if (!$this->plugin->prefs->get("AllowChat")){
                            $sender->sendMessage($this->plugin->formatMessage("All Clan chat is disabled", false));
                            return true;
                        }
                        
                        if ($this->plugin->isInClan($playerName)) {
                            if (isset($this->plugin->allyChatActive[$playerName])) {
                                unset($this->plugin->allyChatActive[$playerName]);
                                $sender->sendMessage($this->plugin->formatMessage("Ally chat disabled", false));
                                return true;
                            } else {
                                $this->plugin->allyChatActive[$playerName] = 1;
                                $sender->sendMessage($this->plugin->formatMessage("§aAlly chat enabled", false));
                                return true;
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("You are not in a clan"));
                            return true;
                        }
                    }/*Not needed.
		     if (strtolower($args[0] == "say")) {
			if (!$this->plugin->prefs->get("AllowChat")) {
			    $sender->sendMessage($this->plugin->formatMessage("/clans say is disabled"));
			    return true;
			}
			if (!($this->plugin->isInFaction($playerName))) {
			    $sender->sendMessage($this->plugin->formatMessage("You must be in a faction to send faction messages"));
			    return true;
			}
			$r = count($args);
			$row = array();
			$rank = "";
			$f = $this->plugin->getPlayerFaction($playerName);
			if ($this->plugin->isOfficer($playerName)) {
			    $rank = "*";
			} else if ($this->plugin->isLeader($playerName)) {
			    $rank = "**";
			}
			$message = "-> ";
			for ($i = 0; $i < $r - 1; $i = $i + 1) {
			    $message = $message . $args[$i + 1] . " ";
			}
			$result = $this->plugin->db->query("SELECT * FROM master WHERE faction='$f';");
			for ($i = 0; $resultArr = $result->fetchArray(SQLITE3_ASSOC); $i = $i + 1) {
			    $row[$i]['player'] = $resultArr['player'];
			    $p = $this->plugin->getServer()->getPlayerExact($row[$i]['player']);
			    if ($p instanceof Player) {
				$p->sendMessage(TextFormat::ITALIC . TextFormat::RED . "<FM>" . TextFormat::AQUA . " <$rank$f> " . TextFormat::GREEN . "<$playerName> " . ": " . TextFormat::RESET);
				$p->sendMessage(TextFormat::ITALIC . TextFormat::DARK_AQUA . $message . TextFormat::RESET);
			    }
			}
		    }
*/
                /////////////////////////////// INFO ///////////////////////////////

                if (strtolower($args[0]) == 'info') {
                    if (isset($args[1])) {
                        if (!(ctype_alnum($args[1])) or !($this->plugin->clanExists($args[1]))) {
                            $sender->sendMessage($this->plugin->formatMessage("§cClan does not exist"));
                            $sender->sendMessage($this->plugin->formatMessage("§cMake sure the name of the selected clan is ABSOLUTELY EXACT."));
                            return true;
                        }
                        $clan = $args[1];
                        $result = $this->plugin->db->query("SELECT * FROM motd WHERE clan='$clan';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        $power = $this->plugin->getclanPower($clan);
                        $message = $array["message"];
                        $leader = $this->plugin->getLeader($clan);
                        $numPlayers = $this->plugin->getNumberOfPlayers($clan);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§3_____§2[§5§lClan Information§r§2]§3_____" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§bClan Name: " . TextFormat::GREEN . "§5$clan" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§cLeader Name: " . TextFormat::YELLOW . "§5$leader" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§dPlayers: " . TextFormat::LIGHT_PURPLE . "§5$numPlayers/50" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§eStrength " . TextFormat::RED . "§d$power" . " §5STR" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§aDescription: " . TextFormat::AQUA . TextFormat::UNDERLINE . "§5$message" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§3_____§2[§5§lClan Information§2]§3_____§r" . TextFormat::RESET);
                    } else {
                        if (!$this->plugin->isInClan($playerName)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this!"));
                            return true;
                        }
                        $clan = $this->plugin->getPlayerClan(($sender->getName()));
                        $result = $this->plugin->db->query("SELECT * FROM motd WHERE clan='$clan';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        $power = $this->plugin->getClanPower($clan);
                        $message = $array["message"];
                        $leader = $this->plugin->getLeader($clan);
                        $numPlayers = $this->plugin->getNumberOfPlayers($faction);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§3_____§2[§5§lYour Clan Information§r§2]§3_____" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§bClan Name: " . TextFormat::GREEN . "§5$clan" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§cLeader Name: " . TextFormat::YELLOW . "§5$leader" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§dPlayers: " . TextFormat::LIGHT_PURPLE . "§5$numPlayers/50" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§eStrength: " . TextFormat::RED . "§d$power" . " §5STR" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§aDescription: " . TextFormat::AQUA . TextFormat::UNDERLINE . "§b$message" . TextFormat::RESET);
                        $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§3_____§2[§5§lYour Clan Information§r§2]§3_____" . TextFormat::RESET);
                    }
                    return true;
                }
                if (strtolower($args[0]) == "help") {
                        $sender->sendMessage(TextFormat::RED . "§6§lClans§cPE §dCommands Help");
                        $sender->sendMessage(TextFormat::RED . "\n§a/clans §babout:accept:deny:desc:listmembers:listofficers:listleader:ourmembers:ourofficers:ourleader:allychat:allies:top:allyok:allyno:leave:kick:info:enemy:chat:say:invite:leader:promote:demote:war:create:disband");
			return true;
                }
                return true;
            }
        } else {
            $this->plugin->getServer()->getLogger()->info($this->plugin->formatMessage("Please run this command in game"));
        }
        return true;
    }

    public function alphanum($string){
        if(function_exists('ctype_alnum')){
            $return = ctype_alnum($string);
        }else{
            $return = preg_match('/^[a-z0-9]+$/i', $string) > 0;
        }
        return $return;
    }
}
