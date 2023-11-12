<?php

namespace nasiridrishi\primescoreboard\animation;

class Animation {

    private int $currentLine = 0;

    public function __construct(private array $lines) {
    }

    public function getNext(): string{
        $line = $this->lines[$this->currentLine];
        $this->currentLine++;
        if($this->currentLine >= count($this->lines)){
            $this->currentLine = 0;
        }
        return $line;
    }

}