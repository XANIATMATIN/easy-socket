<?php

namespace MatinUtils\EasySocket\Protocols\Logics;

class Request
{
    protected $raw;

    public function __construct($raw) {
        $this->raw = $raw;
    }
}