<?php

namespace MatinUtils\EasySocket\Protocols\Logics;

interface Response
{
    public function getOutput() : string;
    public function closeConnection() : bool;
    public function returnable() : bool;
}