<?php

namespace MatinUtils\EasySocket\Protocols\Logics;


abstract class Base
{
    protected $status = true, $socket, $routing, $continuous = false, $buffer = '';

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
        try {
            $input = socket_read($this->socket, 2048);
            if (empty($input)) {
                $this->close();
                return true;
            }
            $this->buffer .= $input;

            $length = strlen($input);
            if ($input[$length - 1] != "\0") {
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
            throw $th;
        }
    }

    protected function writeOnSocket($client, $message)
    {
        try {
            socket_write($client, $message);
        } catch (\Throwable $th) {
            throw $th;
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
