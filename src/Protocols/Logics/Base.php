<?php

namespace MatinUtils\EasySocket\Protocols\Logics;


abstract class Base
{
    protected $status = true, $socket, $routing, $continuous = false, $buffer = '';

    public function __construct($socket)
    {
        $this->socket = $socket;
        try {
            socket_write($this->socket, "connected"); ///> handshake happens both in here and in the Consumer class
        } catch (\Throwable $th) {
            app('log')->error('Base writeOnSocket Has Error. ' . $th->getMessage());
        }
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
        try {
            $input = socket_read($this->socket, 2048);
            if (empty($input)) {
                $this->close();
                return true;
            }
            $this->buffer .= $input;
            if (!app('easy-socket')->messageIsCompelete($this->buffer)) {
                return false;
            }
            return $this->compeleteSection();
        } catch (\Throwable $th) {
            app('log')->error('Socket Read Has Error. ' . $th->getMessage());
            app('log')->error($th->getTraceAsString());
            return true;
        }
    }

    protected function compeleteSection()
    {
        try {
            $request = $this->getRequest($this->buffer);
            $this->buffer = '';

            $response = $this->getResponse();
            $this->routing->handle($request, $response);
            if ($response->returnable()) {
                $this->writeOnSocket($this->socket, $response->getOutput());
            }

            if ($response->closeConnection() || !$this->continuous) {
                $this->close();
            }

            return true;
        } catch (\Throwable $th) {
            app('log')->error('Base Socket Read Has Error. ' . $th->getMessage());
            app('log')->error($th->getTraceAsString());
            return true;
        }
    }

    protected function writeOnSocket($client, $message)
    {
        try {
            $message = app('easy-socket')->prepareMessage($message);
            socket_write($client, $message);
        } catch (\Throwable $th) {
            app('log')->error('Base writeOnSocket Has Error. ' . $th->getMessage());
        }
    }

    public function close()
    {
        socket_close($this->socket);
        $this->status = false;
    }

    public function status()
    {
        return $this->status;
    }
}
