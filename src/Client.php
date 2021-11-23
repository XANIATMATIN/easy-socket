<?php

namespace MatinUtils\EasySocket;

use App\EasySocket\Hooks\Handler;

class Client
{
    protected $host, $port, $interval, $masterSocket, $usingIpProtocol;
    protected $clientSockets = [];
    public $isConnected = false;

    public function __construct(string $host,int $port = 0)
    {
        $this->host = $host;
        $this->port = $port;
        $this->usingIpProtocol = !empty($this->port);
        $this->registerStaticHooks();

        if (!$this->isConnected) {
            if ($this->createSocket()) {
                $this->connectSocket();
            }
        }
    }

    public function live()
    {
        while (true) {

            try {
                $input = socket_read($this->masterSocket, 1024);

                if ($input === 'close') {
                    break;
                } else {
                    dump($input);

                    $message = readline();
                    if ($message === 'exit') {
                        break;
                    }

                    try {
                        socket_write($this->masterSocket, $message, strlen($message));
                    } catch (\Throwable $th) {
                        $errorcode = socket_last_error();
                        $errormsg = socket_strerror($errorcode);
                        dump("Can not send message : [$errorcode] $errormsg");
                        break;
                    }
                }
            } catch (\Throwable $th) {
                $errorcode = socket_last_error();
                $errormsg = socket_strerror($errorcode);
                dump("Can not read socket : [$errorcode] $errormsg");
            }
        }
    }

    public function notLive($data = '') /// for lack of a better word
    {
        if ($this->isConnected) {
            $data .= "\0";
            return $this->writeOnSocket($data);
        } else {
            return false;
        }
    }

    protected function createSocket()
    {
        error_reporting(~E_NOTICE);
        set_time_limit(0);
        try {
            $domain = $this->usingIpProtocol ? AF_INET : AF_UNIX;
            $this->masterSocket = socket_create($domain, SOCK_STREAM, 0);
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
            app('log')->error("Couldn't connect socket: [$errorcode] $errormsg ");
            return $this->isConnected  = false;
        }
    }

    public function closeSocket()
    {
        socket_close($this->masterSocket);
    }

    protected function writeOnSocket($data)
    {
        try {
            if (!socket_write($this->masterSocket, $data, strlen($data))) {
                $errorcode = socket_last_error();
                $errormsg = socket_strerror($errorcode);
                Hooks::trigger('writeFailed', "Can not write on socket : [$errorcode] $errormsg", $data);
                $this->isConnected = false;
            }
            return $this->isConnected;
        } catch (\Throwable $th) {
            Hooks::trigger('writeFailed', "Can not write on socket : " . $th->getMessage(), $data);
            $this->isConnected = false;
        }
    }

    public function registerStaticHooks()
    {
        if (class_exists(Handler::class)) {
            $handler = new Handler;
            $userHooks = $handler->userHooks();
            foreach ($userHooks as $command => $functions) {
                foreach ($functions as $function) {
                    Hooks::register($command, $function);
                }
            }
        }
    }
}
