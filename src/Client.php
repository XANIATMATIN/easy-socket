<?php

namespace MatinUtils\EasySocket;


class Client
{
    protected $host, $port, $interval, $masterSocket, $usingIpProtocol;
    protected $clientSockets = [];
    public $isConnected = false;

    public function __construct(string $host, int $port = 0)
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
        try {
            $this->isConnected  = socket_connect($this->masterSocket, $this->host, $this->port);
            // app('log')->info('Socket successfully connected to: ' . $this->host . (!empty($this->port) ? " on port $this->port" : ''));
            return $this->isConnected;
        } catch (\Throwable $th) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            app('log')->error("Couldn't connect socket ($this->host): [$errorcode] $errormsg ");
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
                app('log')->error("Can not write on socket : [$errorcode] $errormsg");
                app('log')->error(__FILE__ . ':' . __LINE__);
                app('log')->error($data);
                Hooks::trigger('writeFailed', "Can not write on socket : [$errorcode] $errormsg", $data);
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
            app('log')->error('can not read socket. ' . $th->getMessage());
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
