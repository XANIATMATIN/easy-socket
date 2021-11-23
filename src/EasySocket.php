<?php

namespace MatinUtils\EasySocket;

class EasySocket
{
    public function serve()
    {
        $server = new Server();
        return $server->handle();
    }

    public function connect()
    {
        $server = new Client();
        return $server->live();
    }

    public function notLiveClient()
    {
        $server = new Client();
        return $server->notLive();
    }
}
