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
        app('log')->info("Serving through file $socketFolder/$portName.sock");
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
        if (empty($host) || empty($port)) {
            app('log')->error("no host/port specified for socket connection $host:$port");
            return;
        }
        $getPrtRange = app('easy-socket')->getPrtRange($port); ///> returns the range's starting and ending, or false
        $port = $getPrtRange[0];
        $endPort = $getPrtRange[1] ?? $port; ///> there might be no range (no ':')
        do {
            $newSocket = $bindStatus = false;
            try {
                $newSocket = socket_create(AF_INET, SOCK_STREAM, 0);
                $bindStatus = socket_bind($newSocket, $host, $port);
            } catch (\Throwable $th) {
                app('log')->error("Failed to Serve through ip $host:$port " . $th->getMessage());
            }
            $port++;
        } while ((!$newSocket || !$bindStatus) && $port <= $endPort);
        if (!$bindStatus) {
            return false;
        }
        $port--; ///> for log
        app('log')->info("Served through ip $host:$port");
        socket_listen($newSocket, 10);
        return $newSocket;
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

    /**
     * Checks if ip is a range
     * @param  string|int|null,     $port    requested port
     *
     * @return int|false                     if not range, it returns false. if range it returns the range's starting ip
     */
    public function getPrtRange(string $port = '')
    {
        if (empty($port) || !str_contains($port, ':')) return false;
        $range = explode(':', $port);
        return $range;
    }

    /**
     * Removes the "\0" at the end of the message, if exists
     * @param  string|null     $input       Message received from socket
     *
     * @return   string                     Message without "\0" at the end
     */
    public function cleanData(string|null $input = '')
    {
        $length = strlen($input);
        if (($input[$length - 1] ?? "") == "\0") {
            $input = substr($input, 0, -1);
        }
        return $input;
    }

    /**
     * Adds "\0" at the end of the message, if doesn't exists
     * @param    string     $message       Message that is going to be sent to socket
     *
     * @return   string                    Message with "\0" at the end
     */
    public function prepareMessage(string $message)
    {
        if (($message[strlen($message) - 1] ?? '') != "\0") { ///> the message might already have /0
            $message .= "\0";
        }
        return $message;
    }

    /**
     * checks if Message is compeleted
     * @param    string     $message       Message received from socket
     *
     * @return   bool                    
     */
    public function messageIsCompelete(string $message)
    {
        return ($message[strlen($message) - 1] ?? "\0")== "\0";
    }
}
