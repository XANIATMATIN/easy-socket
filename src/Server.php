<?php

namespace MatinUtils\EasySocket;

use Exception;

class Server
{
    protected $host, $port, $interval, $masterSocket, $maxClientNumber, $usingIpProtocol;
    protected $clients = [];

    public function __construct()
    {
        $port = config('easySocket.port', 0);
        $this->usingIpProtocol = !empty($port) && is_numeric($port);
        if ($this->usingIpProtocol) {
            $this->host = config('easySocket.host', '127.0.0.1');
            $this->port = $port;
        } else {
            $this->host = base_path(config('easySocket.filePath', "bootstrap/easySocket")) . "/$port.sock";
            $this->port = 0;
        }
        $this->interval = config('easySocket.interval', null);
        $this->maxClientNumber = config('easySocket.maxClientNumber', SOMAXCONN);

        $this->registerStaticHooks();

        error_reporting(~E_NOTICE);
        set_time_limit(0);
    }

    public function handle()
    {
        if ($this->createSocket()) {
            if ($this->bindSocket()) {
                if ($this->socketListen()) {
                    ///> socket is now ready, waiting for connections


                    while (true) {
                        ///> re-filling the read array with the existing sockets
                        $read = [];
                        $read[0] = $this->masterSocket;
                        foreach ($this->clients as $client) {
                            $read[] = $client->getSocket();
                        }

                        ///> waiting for any action on the socket, whether it's new client connecting or an existing client sending sth
                        try {
                            socket_select($read, $write, $except, $this->interval);
                        } catch (\Throwable $th) {
                            $errorcode = socket_last_error();
                            $errormsg = socket_strerror($errorcode);
                            app('log')->error("Socket select failed : [$errorcode] $errormsg. " . $th->getMessage());
                        }


                        //if read contains the master socket, then a new connection has come in
                        if (in_array($this->masterSocket, $read)) {
                            if (count($this->clients) >= $this->maxClientNumber) {
                                app('log')->error("New connection blocked. Max client capacity reached");
                                ///> i accept then close the coonection, there are probably better ways
                                $newClient = socket_accept($this->masterSocket);
                                socket_close($newClient);
                                continue;
                            } else {
                                $protocol = $this->protocolFactoty(config('easySocket.defaultProtocol', 'http'), socket_accept($this->masterSocket));
                                $this->clients[] = $protocol;
                                // Hooks::trigger('newClient', $protocol, count($this->clients));
                            }
                        }

                        ///> receive clients' messages
                        $queue = [];
                        foreach ($this->clients as $index => $client) {
                            if (in_array($client->getSocket(), $read)) {
                                $queue[$index] = $client;
                            }
                        }

                        do {
                            foreach ($queue as $index => $client) {
                                if ($client->read()) {
                                    if (!$client->status()) {
                                        unset($this->clients[$index]);
                                    }
                                    unset($queue[$index]);
                                }
                            }
                        } while (!empty($queue));
                    }
                    $this->closeSocket();
                }
            }
        }
    }

    protected function createSocket()
    {
        try {
            $domain = $this->usingIpProtocol ? AF_INET : AF_UNIX;
            $this->masterSocket = socket_create($domain, SOCK_STREAM, 0);
            return true;
        } catch (\Throwable $th) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            app('log')->error("Couldn't create socket: [$errorcode] $errormsg ");
            return false;
        }
    }

    protected function bindSocket()
    {
        try {
            socket_bind($this->masterSocket, $this->host, $this->port);
            if (!$this->usingIpProtocol) {
                chmod($this->host, 0777);
            }
            app('log')->info('Socket successfully binded to: ' . $this->host . ($this->usingIpProtocol ? " on port $this->port" : ''));
        } catch (\Throwable $th) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            if ($this->usingIpProtocol || (strpos($errormsg, 'Address already in use') === false)) {
                app('log')->error("Couldn't bind socket ($this->host): [$errorcode] $errormsg. " . $th->getMessage());
                return false;
            } else {
                unlink($this->host);
                $this->bindSocket();
            }
        }
        return true;
    }

    protected function socketListen()
    {
        try {
            socket_listen($this->masterSocket, 10);
            return true;
        } catch (\Throwable $th) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            app('log')->error("Couldn't listen on socket: [$errorcode] $errormsg. " . $th->getMessage());
            return false;
        }
    }

    public function registerStaticHooks()
    {
        try {
            $hookHandlerClass = 'App\\' . ucfirst(config('easySocket.defaultProtocol', 'http')) . '\Hooks\Handler';
            $handler = new $hookHandlerClass;
            $userHooks = $handler->userHooks();
            foreach ($userHooks as $command => $functions) {
                foreach ($functions as $function) {
                    Hooks::register($command, $function);
                }
            }
        } catch (\Throwable $th) {
            app('log')->error('Hook handler unavailable');
        }
    }

    public function closeSocket()
    {
        socket_close($this->masterSocket);
    }

    public function protocolFactoty($protocolAlias, $clientSocket)
    {
        $class = config("easySocket.protocols.$protocolAlias");

        if (empty($class)) {
            throw new Exception("No Protocol", 1);
        }

        return new $class($clientSocket);
    }
}
