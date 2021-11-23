<?php

namespace MatinUtils\EasySocket;

use App\EasySocket\Hooks\Handler;

class Server
{
    protected $host, $port, $interval, $masterSocket, $maxClientNumber, $usingIpProtocol;
    protected $clientSockets = [];
    protected $routing;

    public function __construct()
    {
        $this->host = config('easySocket.host', '/tmp/server.sock');
        $this->port = config('easySocket.port', 0);
        $this->usingIpProtocol = !empty($this->port);
        $this->interval = config('easySocket.interval', 0);
        $this->maxClientNumber = config('easySocket.maxClientNumber', SOMAXCONN);

        $this->routing = new Routing;
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
                        foreach ($this->clientSockets as $client) {
                            $read[] = $client;
                        }

                        ///> waiting for any action on the socket, whether it's new client connecting or an existing client sending sth
                        try {
                            socket_select($read, $write, $except, $this->interval);
                        } catch (\Throwable $th) {
                            $errorcode = socket_last_error();
                            $errormsg = socket_strerror($errorcode);
                            app('log')->error("Socket select failed : [$errorcode] $errormsg");
                        }


                        //if read contains the master socket, then a new connection has come in
                        if (in_array($this->masterSocket, $read)) {
                            if (count($this->clientSockets) >= $this->maxClientNumber) {
                                app('log')->error("New connection blocked. Max client capacity reached");
                                ///> i accept then close the coonection, there are probably better ways
                                $newClient = socket_accept($this->masterSocket);
                                socket_close($newClient);
                                continue;
                            } else {
                                $this->clientSockets[] = $newClient = socket_accept($this->masterSocket);
                                Hooks::trigger('newClient', $newClient, count($this->clientSockets));
                            }
                        }

                        ///> receive clients' messages
                        foreach ($this->clientSockets as $index => $client) {
                            if (in_array($client, $read)) {
                                $input = $this->readSocket($client);
                                if ($input == null) {
                                    socket_close($client);
                                    unset($this->clientSockets[$index]);
                                } else {
                                    Hooks::trigger('messageReceived', $client, trim($input));
                                    $this->routing->handle(trim($input), $client);
                                }
                            }
                        }
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
            dump('Socket successfully binded to: ' . $this->host . (!empty($this->port) ? " on port $this->port" : ''));
            app('log')->info('Socket successfully binded to: ' . $this->host . ($this->usingIpProtocol ? " on port $this->port" : ''));
        } catch (\Throwable $th) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            if ($this->usingIpProtocol || (strpos($errormsg, 'Address already in use') === false)) {
                app('log')->error("Couldn't bind socket: [$errorcode] $errormsg ");
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
            app('log')->error("Couldn't listen on socket: [$errorcode] $errormsg ");
            return false;
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

    protected function writeOnSocket($client, $message)
    {
        try {
            socket_write($client, $message);
        } catch (\Throwable $th) {
            app('log')->error('catch server wrote '.$th->getMessage());
        }
    }

    protected function readSocket($client)
    {
        $stack = '';
        try {
            do {
                $input = socket_read($client, 1024);
                $stack .= $input;
            } while (strlen($input) == 1024);
        } catch (\Throwable $th) {
            app('log')->error('can not read socket. ',$th->getMessage());
        }

        return $stack ?? '';
    }

    public function closeSocket()
    {
        socket_close($this->masterSocket);
    }
}
