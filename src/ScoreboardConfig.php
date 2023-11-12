<?php

namespace nasiridrishi\primescoreboard;

use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;

class ScoreboardConfig {

    public function __construct(private string $title, private array $lines, private int $priority, private ?World $world = null, private ?string $permission = null) {
        $this->title = TextFormat::colorize($this->title);
        foreach($this->lines as $key => $line) {
            $this->lines[$key] = TextFormat::colorize($line);
        }
    }

    public function canShow(Player $player): bool {
        if($this->world !== null && $player->getWorld() !== $this->world) {
            return false;
        }
        if($this->permission !== null && !$player->hasPermission($this->permission)) {
            return false;
        }
        return true;
    }

    /**
     * @return int
     */
    public function getPriority(): int {
        return $this->priority;
    }

    public function setScoreboard(Player $player): bool {
        if(!$this->canShow($player)) {
            return false;
        }
        $session = PlayerSession::getSession($player);
        $scoreboard = new Scoreboard($player, $this->title, $this->title, $this);
        $entry = 0;
        foreach($this->lines as $line) {
            PrimeScoreboard::getInstance()->getLogger()->info($line);
            $scoreboard->setEntry($entry, $line);
            $entry++;
        }
        $session->setScoreboard($scoreboard);
        return true;
    }

}