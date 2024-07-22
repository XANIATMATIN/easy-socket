<?php

namespace MatinUtils\EasySocket;

use Exception;

class Client
{
    protected $host, $port, $interval, $masterSocket, $usingIpProtocol;
    protected $clientSockets = [];
    public $isConnected = false;

    public function __construct(string $host, int|string $port = 0)
    {
        $this->host = $host;
        $this->port = $port;
        $this->usingIpProtocol = !empty($this->port);

        if (!$this->isConnected) {
            $this->connectSocket();
        }
        error_reporting(~E_NOTICE);
    }

    protected function connectSocket()
    {
        if ($this->usingIpProtocol) return $this->connectThroughIPProtocol();
        try {
            $startConnection = $this->startConnection(0);
            $this->isConnected = $startConnection['status'];
            if (!$this->isConnected) {
                app('log')->error("Couldn't connect to socket file ($this->host) - {$startConnection['message']}");
            }
            // app('log')->info("Socket successfully connected to : $this->host ");
        } catch (\Throwable $th) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            app('log')->error("Couldn't connect to socket on file ($this->host) [$errorcode] $errormsg ");
            $this->isConnected = false;
        }
        return $this->isConnected;
    }

    protected function connectThroughIPProtocol()
    {
        $getPrtRange = app('easy-socket')->getPrtRange($this->port); ///> returns the range's starting and ending, or false
        $port = $getPrtRange[0];
        $endPort = $getPrtRange[1] ?? $port; ///> there might be no range (no ':')
        $counter = 1;
        do {
            $isConnected = false;
            try {
                $startConnection = $this->startConnection($port);
                $isConnected = $startConnection['status'];
                if (!$isConnected) {
                    app('log')->error("Try #$counter ,Couldn't connect to socket ($this->host:$port) - {$startConnection['message']}");
                }
                // app('log')->info("Socket successfully connected to: $this->host on port $port. handshake: $handshake");
            } catch (\Throwable $th) {
                $errorcode = socket_last_error();
                $errormsg = socket_strerror($errorcode);
                app('log')->error($th->getMessage());
                app('log')->error("Try #$counter ,Couldn't connect to socket ($this->host:$port) [$errorcode] $errormsg");
            }
            $counter++;
            $port++;
        } while (!$isConnected && $port <= $endPort);
        return $this->isConnected = $isConnected;
    }

    protected function startConnection($port)
    {
        if ($this->createSocket()) {
            $isConnected = socket_connect($this->masterSocket, $this->host, $port);
            if (!$isConnected) {
                $errorcode = socket_last_error();
                $errormsg = socket_strerror($errorcode);
                return ['status' => false, 'message' => "socket_connect failed. [$errorcode] $errormsg"];
            }
            app('easy-socket')->setReadTimeOut($this->masterSocket, 1);
            $handshake = socket_read($this->masterSocket, 1024);
            app('easy-socket')->setReadTimeOut($this->masterSocket, 0);
            if ($handshake != "connected") {
                socket_close($this->masterSocket);
                return ['status' => false, 'message' => "handshake failed. received $handshake"];
            }
            return ['status' => true];
        }
        return ['status' => false, 'message' => "Could not create socket"];
    }

    protected function createSocket()
    {
        $this->masterSocket = null;
        try {
            $domain = $this->usingIpProtocol ? AF_INET : AF_UNIX;
            $this->masterSocket = socket_create($domain, SOCK_STREAM, 0);
            // app('log')->info('Created Socket');
            return true;
        } catch (\Throwable $th) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            app('log')->error("Couldn't create socket: [$errorcode] $errormsg ");
            return $this->isConnected  = false;
        }
    }

    public function closeSocket()
    {
        socket_close($this->masterSocket);
        $this->isConnected = false;
    }

    protected function writeOnSocket($data)
    {
        try {
            if (!socket_write($this->masterSocket, $data, strlen($data))) {
                $errorcode = socket_last_error();
                $errormsg = socket_strerror($errorcode);
                app('log')->error("Can not write on socket ($this->host:$this->port): [$errorcode] $errormsg");
                app('log')->error($data);
                Hooks::trigger('writeFailed', "Can not write on socket ($this->host:$this->port): [$errorcode] $errormsg", $data);
                $this->isConnected = false;
            }
        } catch (\Throwable $th) {
            $this->isConnected = false;
            app('log')->error("socket_write exception ($this->host:$this->port): " . $th->getMessage());
            app('log')->error($data);
            Hooks::trigger('socket_write exception,($this->host:$this->port)', "ocket_write exception ($this->host:$this->port): " . $th->getMessage(), $data);
        }
        return $this->isConnected;
    }

    protected function readSocket()
    {
        $stack = '';
        try {
            do {
                $input = socket_read($this->masterSocket, 1024);
                $stack .= $input;
            } while (!app('easy-socket')->messageIsCompelete($input));
        } catch (\Throwable $th) {
            app('log')->error("can not read socket ($this->host:$this->port). " . $th->getMessage());
        }

        $stack = app('easy-socket')->cleanData($stack);
        return $stack ?? '';
    }
}
