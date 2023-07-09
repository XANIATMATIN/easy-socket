<?php

namespace MatinUtils\EasySocket;

use Exception;

class Server
{
    protected $host, $port, $interval, $masterSocket, $maxClientNumber, $usingIpProtocol, $read;
    protected $clients = [];

    public function __construct()
    {
        $this->interval = config('easySocket.interval', null);
        $this->maxClientNumber = config('easySocket.maxClientNumber', SOMAXCONN);
        $this->masterSocket = serveAndListen(config('easySocket.port', 'client'));
        $this->registerStaticHooks();
        error_reporting(~E_NOTICE);
        set_time_limit(0);
    }

    public function handle()
    {
        ///> socket is now ready, waiting for connections
        while (true) {
            $this->makeReadArray();

            ///> waiting for any action on the socket, whether it's new client connecting or an existing client sending sth
            socket_select($this->read, $write, $except, $this->interval);

            $this->checkForNewClients();

            $this->receiveAndProcessClientMessage();
        }
        $this->closeSocket();
    }

    protected function makeReadArray()
    {
        ///> re-filling the read array with the existing sockets
        $this->read = [$this->masterSocket];
        foreach ($this->clients as $clientConnection) {
            $this->read[] = $clientConnection->getSocket();
        }
        return $this->read;
    }

    protected function checkForNewClients()
    {
        //if read contains the master socket, then a new connection has come in
        if (in_array($this->masterSocket, $this->read)) {
            $protocol = $this->protocolFactoty(config('easySocket.defaultProtocol', 'http'), socket_accept($this->masterSocket));
            $this->clients[] = $protocol;
            // Hooks::trigger('newClient', $protocol, count($this->clients));
        }
    }

    protected function receiveAndProcessClientMessage()
    {
        $queue = [];
        foreach ($this->clients as $index => $client) {
            if (in_array($client->getSocket(), $this->read)) {
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
