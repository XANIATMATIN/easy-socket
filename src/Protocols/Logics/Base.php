<?php

namespace MatinUtils\EasySocket\Protocols\Logics;


abstract class Base
{
    protected $socket, $routing, $continuous = false;

    public function __construct($socket)
    {
        $this->socket = $socket;
        $this->routing = $this->getRouter();
    }
    public abstract function getRouter(): Router;
    public abstract function getRequest($raw): Request;
    public abstract function getResponse(): Response;

    public function getSocket()
    {
        return $this->socket;
    }

    public function read()
    {
        $input = $this->readSocket();

        if (($input == null && !$this->continuous)) {
            return $this->close();
        }

        if ($input != null) {
            app('log')->info($input);
            $request = $this->getRequest($input);
            $response = $this->getResponse();
            $this->routing->handle($request, $response);
            $this->writeOnSocket($this->socket, $response->getOutput());
            if ($response->closeConnection()) {
                return $this->close();
            }
        }

        if (!$this->continuous) {
            return $this->close();
        }
    }

    protected function readSocket()
    {
        $stack = '';
        try {
            do {
                $input = socket_read($this->socket, 2048);
                $stack .= $input;
            } while (strlen($input) == 2048);
        } catch (\Throwable $th) {
            app('log')->error('can not read socket. ', $th->getMessage());
        }

        return $stack ?? '';
    }

    protected function writeOnSocket($client, $message)
    {
        try {
            socket_write($client, $message);
        } catch (\Throwable $th) {
            app('log')->error('writeOnSocket failed: ' . $th->getMessage());
        }
    }

    public function close()
    {
        socket_close($this->socket);
    }

    public function status()
    {
        return get_resource_type($this->socket) == 'Socket';
    }
}
