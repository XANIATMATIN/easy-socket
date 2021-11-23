<?php

namespace MatinUtils\EasySocket;

class Hooks
{
    static protected $hooks = [];

    static public function register($command, $function)
    {
        $command = strtoupper($command);
        if (!isset(self::$hooks[$command])) {
            self::$hooks[$command] = array();
        }

        if (array_search($function, self::$hooks[$command]) === FALSE) {
            self::$hooks[$command][] = $function;
        }
    }

    static public function unhook($command, $function)
    {
        $command = strtoupper($command);
        $k = array_search($function, self::$hooks[$command]);
        if ($k !== FALSE) {
            unset(self::$hooks[$command][$k]);
        }
    }

    static public function trigger($command, ...$data)
    {
        $command = strtoupper($command);
        foreach (self::$hooks[$command] ?? [] as $function) {
            call_user_func($function, ...$data);
        }
    }
}
