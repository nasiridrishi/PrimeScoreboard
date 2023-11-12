<?php

namespace nasiridrishi\primescoreboard;

use InvalidArgumentException;
use nasiridrishi\primescoreboard\PrimeScoreboard;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;
use RangeException;

class Scoreboard {

    private static int $scoreboardUID = 0;

    private static function getScoreboardUID(): int {
        return self::$scoreboardUID += 16;
    }

    public const MAX_LINES = 15;
    public const SORT_ASCENDING = 0;
    public const SORT_DESCENDING = 1;
    public const SLOT_LIST = "list";
    public const SLOT_SIDEBAR = "sidebar";

    /** @var bool[] */
    private array $buffers;

    /** @var string */
    private string $title = "";

    /** @var string */
    private string $displaySlot = self::SLOT_SIDEBAR;

    /** @var int */
    private int $identifier = 0;

    /** @var ScorePacketEntry[] */
    private array $entries = [];

    /** @var string */
    private string $objectiveName = "";
    private Player $owner;

    /** @var int */
    private int $sortOrder;

    /** @var bool */
    private bool $viewable = true;

    /**
     * Scoreboard constructor.
     *
     * @param Player $owner
     * @param string $objectiveName
     * @param string $title
     * @param ScoreboardConfig $config
     * @param int $sortOrder
     */
    public function __construct(Player $owner, string $objectiveName, string $title, private ScoreboardConfig $config, int $sortOrder = self::SORT_ASCENDING) {
        $this->owner = $owner;
        $this->objectiveName = $objectiveName;
        $this->sortOrder = $sortOrder;
        $this->identifier = self::getScoreboardUID();

        // Initialize Buffer Flags
        $this->buffers = [
            "§0" => false,
            "§1" => false,
            "§2" => false,
            "§3" => false,
            "§4" => false,
            "§5" => false,
            "§6" => false,
            "§7" => false,
            "§8" => false,
            "§9" => false,
            "§a" => false,
            "§b" => false,
            "§c" => false,
            "§d" => false,
            "§e" => false];
    }

    /**
     * @return string
     */
    public function getObjectiveName(): string {
        return $this->objectiveName;
    }

    /**
     * @param string $objectiveName
     */
    public function setObjectiveName(string $objectiveName): void {
        $this->objectiveName = $objectiveName;
    }


    public function getOwner(): Player {
        return $this->owner;
    }

    /**
     * @return string
     */
    public function getTitle(): string {
        return $this->title;
    }

    /**
     * @param string $displayName
     */
    public function setTitle(string $displayName): void {
        $this->title = $this->parse($displayName);
    }

    /**
     * @return int
     */
    public function getSortOrder(): int {
        return $this->sortOrder;
    }

    /**
     * @param int $sortOrder
     */
    public function setSortOrder(int $sortOrder): void {
        if($sortOrder !== self::SORT_ASCENDING and $sortOrder !== self::SORT_DESCENDING) {
            throw new InvalidArgumentException("Sort Order selection $sortOrder is invalid.  Acceptable options are 0 or 1");
        }
        $this->sortOrder = $sortOrder;
    }

    /**
     * @return ScorePacketEntry[]
     */
    public function getEntries(): array {
        return $this->entries;
    }

    /**
     * Return the ScorePacketEntry of the line provided.
     * Will return null if nothing is on that line.
     *
     * Should this return null if the line has an empty buffer?
     *
     * @param int $line
     *
     * @return null|ScorePacketEntry
     */
    public function getEntry(int $line): ?ScorePacketEntry {
        $this->verifyLineNumber($line);
        if(isset($this->entries[$line])) {
            return $this->entries[$line];
        }

        return null;
    }

    /**
     * @param int $id
     */
    public function verifyLineNumber(int $id): void {
        if($id > 14 or $id < 0) {
            throw new RangeException("Scoreboard Entry id must be an integer from 0 to 14: $id given");
        }
    }

    /**
     * @param int $line
     * @param string $message
     * @param bool $update
     * @param int $type
     */
    public function setEntry(int $line, string $message, bool $update = false, int $type = ScorePacketEntry::TYPE_FAKE_PLAYER): void {
        $this->verifyLineNumber($line);
        if(isset($this->entries[$line])) {
            $this->removeEntry($line);
        }
        $entry = $this->createEntry($line, $message, $type);
        $this->entries[$line] = $entry;
        if($update) {
            $this->update();
        }
    }

    /**
     * Removes an entry from the scoreboard.  If the entry is an "empty" line
     * the corresponding buffer will set as available.
     *
     * @param int $line
     */
    public function removeEntry(int $line): void {
        if(!isset($this->entries[$line]) || !$this->owner->isConnected()) {
            return;
        }
        if(isset($this->buffers[$this->entries[$line]->customName])) {
            $this->buffers[$this->entries[$line]->customName] = false;
        }
        $pk = new SetScorePacket();
        $pk->type = SetScorePacket::TYPE_REMOVE;
        $pk->entries[] = $this->entries[$line];
        $this->owner->getNetworkSession()->sendDataPacket($pk);
        unset($this->entries[$line]);
    }

    /**
     * @param int $line
     * @param string $message
     * @param int $type
     *
     * @return ScorePacketEntry
     */
    private function createEntry(int $line, string $message, int $type = ScorePacketEntry::TYPE_FAKE_PLAYER): ScorePacketEntry {
        $entry = new ScorePacketEntry();
        $entry->objectiveName = $this->objectiveName;
        $entry->scoreboardId = $line + $this->identifier;
        $entry->score = $line;
        $entry->customName = $message;
        $entry->type = $type;
        return $entry;
    }

    /**
     * Sends updates to the scoreboard the owner.
     */
    public function update(): void {
        $this->hide();
        if($this->viewable and $this->owner->isConnected()) {
            $this->owner->getNetworkSession()->sendDataPacket(SetDisplayObjectivePacket::create(SetDisplayObjectivePacket::DISPLAY_SLOT_SIDEBAR,
                $this->objectiveName,
                $this->parse($this->title),
                "dummy",
                $this->sortOrder
            ));
            $pk = new SetScorePacket();
            $pk->type = SetScorePacket::TYPE_CHANGE;
            $pk->entries = $this->parseEntries();
            $this->owner->getNetworkSession()->sendDataPacket($pk);
        }
    }

    public function removeAllEntry(): void {
        foreach($this->entries as $entry) {
            $this->removeEntry($entry->score);
        }
    }

    /**
     * @return bool
     */
    public function isViewable(): bool {
        return $this->viewable;
    }

    /**
     * @param bool $viewable
     */
    public function setViewable(bool $viewable = true): void {
        if(!$this->owner->isConnected())
            return;
        $this->viewable = $viewable;
        if($viewable) {
            $this->show();
        } else {
            $this->hide();
        }
    }

    /**
     * @return int
     */
    public function getIdentifier(): int {
        return $this->identifier;
    }

    /**
     * Used to show the scoreboard to the player when it is hidden.
     */
    public function show(): void {
        if(!$this->owner->isConnected())
            return;
        // Properly respect the choice of the software and the player before sending the scoreboard packet.
        if(!($this->viewable)){
            $msg = "Tried to show the player a scoreboard while scoreboard was ";
            $msg .= "not viewable";
            $msg .= " and owner was ";
            $msg .= " scoreboards to be viewed.";
            PrimeScoreboard::getInstance()->getLogger()->debug($msg);
            return;
        }
        $this->owner->getNetworkSession()->sendDataPacket(SetDisplayObjectivePacket::create(SetDisplayObjectivePacket::DISPLAY_SLOT_SIDEBAR,
            $this->objectiveName,
            $this->parse($this->title),
            "dummy",
            $this->sortOrder
        ));
        if(!empty($this->entries)) {
            $pk2 = new SetScorePacket();
            $pk2->type = SetScorePacket::TYPE_CHANGE;
            $pk2->entries = $this->parseEntries();
            $this->owner->getNetworkSession()->sendDataPacket($pk2);
        }
    }

    /**
     * Used to hide the scoreboard from the owning player's view.
     */
    public function hide(): void {
        if(!$this->owner->isConnected())
            return;
        $pk = new RemoveObjectivePacket();
        $pk->objectiveName = $this->objectiveName;
        $this->owner->getNetworkSession()->sendDataPacket($pk);
    }

    private function parse(string $message): string{
        //check for string {animationName}
        if(preg_match_all("/\{([^}]+)\}/", $message, $matches)){
            foreach($matches[1] as $match){
                $animation = PrimeScoreboard::getInstance()->getAnimationManager()->getAnimation($match);
                if($animation !== null){
                    $message = str_replace("{" . $match . "}", $animation->getNext(), $message);
                }else{
                    PrimeScoreboard::getInstance()->getLogger()->warning("Could not find animation with name " . $match);
                }
            }
        }
        $message = str_replace("%player%", $this->owner->getName(), $message);
        if(PrimeScoreboard::getInstance() !== null){
            $message = PrimeScoreboard::getInstance()->getPlaceHolderHook()->setPlaceHolders($message, $this->owner);
        }
        return $message;
    }

    /**
     * @return ScorePacketEntry[]
     */
    private function parseEntries(): array{
        $entries = [];
        foreach($this->entries as $key => $entry){
            $pEntry = clone $entry;
            $pEntry->customName = $this->parse($pEntry->customName);
            $entries[$key] = $pEntry;
        }
        return $entries;
    }

    /**
     * @return ScoreboardConfig
     */
    public function getConfig(): ScoreboardConfig {
        return $this->config;
    }
}
