<?php

namespace nasiridrishi\primescoreboard;

use nasiridrishi\primescoreboard\Scoreboard;
use pocketmine\player\Player;
use WeakMap;

class PlayerSession {
    private static WeakMap $sessions;

    public static function getSession(Player $player): PlayerSession{
        if(!isset(self::$sessions)){
            self::$sessions = new WeakMap();
        }
        if(!isset(self::$sessions[$player])){
            self::$sessions[$player] = new PlayerSession($player);
        }
        return self::$sessions[$player];
    }

    private Player $player;

    private ?Scoreboard $scoreboard = null;

    public function __construct(Player $player){
        $this->player = $player;
    }

    public function getPlayer(): Player{
        return $this->player;
    }

    public function getScoreboard(): ?Scoreboard{
        return $this->scoreboard;
    }

    public function setScoreboard(?Scoreboard $scoreboard): void{
        if(isset($this->scoreboard)){
            $this->scoreboard->hide();
        }
        if($scoreboard === null){
            $this->scoreboard = null;
            return;
        }
        $this->scoreboard = $scoreboard;
        $this->scoreboard->show();
    }
}