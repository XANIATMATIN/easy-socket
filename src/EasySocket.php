<?php

namespace MatinUtils\EasySocket;

class EasySocket
{
    public function serve()
    {
        $server = new Server();
        return $server->handle();
    }

    public function serveOnFile($portName)
    {
        $socketFolder = base_path(config('easySocket.filePath'));
        app('log')->info("connecting through file $socketFolder/$portName.sock");
        try {
            $newPort = socket_create(AF_UNIX, SOCK_STREAM, 0);
            socket_bind($newPort, "$socketFolder/$portName.sock");
        } catch (\Throwable $th) {
            if (!strstr($th->getMessage(), 'already in use')) {
                app('log')->error($th->getMessage());
                return;
            }
            unlink("$socketFolder/$portName.sock");
            socket_bind($newPort, "$socketFolder/$portName.sock");
        }
        chmod("$socketFolder/$portName.sock", 0777);
        socket_listen($newPort, 10);
        return $newPort;
    }

    public function connectToFile($portName)
    {
        $socketFolder = base_path(config('easySocket.filePath'));
        app('log')->info("connecting through file $socketFolder/$portName.sock");
        try {
            $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
            socket_connect($socket, "$socketFolder/$portName.sock");
        } catch (\Throwable $th) {
            app('log')->error($th->getMessage());
        }
        return $socket;
    }

    public function serveOnPort($port)
    {
        $host = config('easySocket.host');
        app('log')->info("connecting through ip $host:$port");
        if (empty($host) || empty($port)) {
            app('log')->error("no host/port specified for socket connection");
            return;
        }
        try {
            $newPort = socket_create(AF_INET, SOCK_STREAM, 0);
            socket_bind($newPort, $host, $port);
        } catch (\Throwable $th) {
            app('log')->error($th->getMessage());
        }
        socket_listen($newPort, 10);
        return $newPort;
    }

    public function connectToPort($port)
    {
        $host = config('easySocket.host');
        app('log')->info("connecting through ip $host:$port");
        if (empty($host) || empty($port)) {
            app('log')->error("no host/port specified for socket connection");
            return;
        }
        try {
            $socket = socket_create(AF_INET, SOCK_STREAM, 0);
            socket_connect($socket, $host, $port);
        } catch (\Throwable $th) {
            app('log')->error($th->getMessage());
        }
        return $socket;
    }
}
