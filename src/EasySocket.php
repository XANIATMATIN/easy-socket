<?php

namespace MatinUtils\EasySocket;

class EasySocket
{
    public function serve()
    {
        $server = new Server();
        return $server->handle();
    }
}
