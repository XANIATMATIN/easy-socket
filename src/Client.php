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
            if ($this->createSocket()) {
                $this->connectSocket();
            }
        }
    }

    protected function createSocket()
    {
        error_reporting(~E_NOTICE);
        set_time_limit(0);
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

    protected function connectSocket()
    {
        if ($this->usingIpProtocol) return $this->connectThroughIPProtocol();
        try {
            $this->isConnected  = socket_connect($this->masterSocket, $this->host, $this->port);
            if (($handshake = socket_read($this->masterSocket, 1024)) != "connected") {
                throw new Exception("Handshake failed. Received $handshake", 1);
            }
            // app('log')->info("Socket successfully connected to: $this->host . $handshake");
            return $this->isConnected;
        } catch (\Throwable $th) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            app('log')->error("Couldn't connect to socket on file ($this->host) [$errorcode] $errormsg ");
            return $this->isConnected = false;
        }
    }

    protected function connectThroughIPProtocol()
    {
        $portIsAnIPRange = app('easy-socket')->portIsAnIPRange($this->port); ///> returns the range's starting ip or false
        $port = $portIsAnIPRange ?? $this->port;
        $counter = 1;
        do {
            $isConnected = $handshake = false;
            try {
                $isConnected = socket_connect($this->masterSocket, $this->host, $port);
                if (($handshake = socket_read($this->masterSocket, 1024)) != "connected") {
                    throw new Exception("Handshake failed. Received $handshake", 1);
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
        } while (!$isConnected && $counter <= ($portIsAnIPRange ? config('easySocket.ipRangeMax', 3) : 1));
        return $this->isConnected = $isConnected;
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
                app('log')->error("Can not write on socket : [$errorcode] $errormsg");
                app('log')->error($data);
                Hooks::trigger('writeFailed', "Can not write on socket ($this->host:$this->port): [$errorcode] $errormsg", $data);
                $this->isConnected = false;
            }
            return $this->isConnected;
        } catch (\Throwable $th) {
            $this->isConnected = false;
            app('log')->error("Can not write on socket : " . $th->getMessage());
            app('log')->error(__FILE__ . ':' . __LINE__);
            app('log')->error($data);
            Hooks::trigger('writeFailed', "Can not write on socket : " . $th->getMessage(), $data);
        }
    }

    protected function readSocket()
    {
        $stack = '';
        try {
            do {
                $input = socket_read($this->masterSocket, 1024);
                $stack .= $input;
            } while (($input[strlen($input) - 1] ?? "\0") != "\0");
        } catch (\Throwable $th) {
            app('log')->error("can not read socket ($this->host:$this->port). " . $th->getMessage());
        }

        $stack = $this->cleanData($stack);
        return $stack ?? '';
    }

    protected function cleanData($input)
    {
        $length = strlen($input);
        if (($input[$length - 1] ?? "") == "\0") {
            $input = substr($input, 0, -1);
        }
        return $input;
    }
}
