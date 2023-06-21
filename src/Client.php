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
            $startConnection = $this->startConnection(0);
            $this->isConnected = $startConnection['status'];
            if (!$this->isConnected) {
                app('log')->error("Couldn't connect to socket file ($this->host) - {$startConnection['message']}");
            }
            // app('log')->info("Socket successfully connected to: $this->host . $handshake");
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
        $portIsAnIPRange = app('easy-socket')->portIsAnIPRange($this->port); ///> returns the range's starting ip or false
        $port = $portIsAnIPRange ?? $this->port;
        $counter = 1;
        do {
            $isConnected = $handshake = false;
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
        } while (!$isConnected && $counter <= ($portIsAnIPRange ? config('easySocket.ipRangeMax', 3) : 1));
        return $this->isConnected = $isConnected;
    }

    protected function startConnection($port)
    {
        $isConnected = socket_connect($this->masterSocket, $this->host, $port);
        if (!$isConnected) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            return ['status' => false, 'message' => "socket_connect failed. [$errorcode] $errormsg"];
        }
        $handshake = socket_read($this->masterSocket, 1024);
        if ($handshake != "connected") return ['status' => false, 'message' => "handshake failed. received $handshake"];
        return ['status' => true];
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

        $stack = app('easy-socket')->cleanData($stack);
        return $stack ?? '';
    }
}
