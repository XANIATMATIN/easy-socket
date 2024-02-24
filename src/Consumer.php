<?php

namespace MatinUtils\EasySocket;


class Consumer
{
    ///> This class is used when Process Manager receives a new client,
    ///> will move to another package (pm connector)
    protected $status = true, $socket, $continuous = false, $responseReceived = false, $buffer = '', $temp = '';

    public function __construct($socket)
    {
        $this->socket = $socket;
        try {
            socket_write($this->socket, "connected"); ///> handshake
        } catch (\Throwable $th) {
            app('log')->error('Consumer handshake Failed. ' . $th->getMessage());
            $this->status = false;
        }
    }

    public function getSocket()
    {
        return $this->socket;
    }

    public function read()
    {
        try {
            $input = socket_read($this->socket, 2048);
            if (empty($input)) { ///> means the client is disconnected
                $this->close();
                return false;
            }
            $this->buffer .= $input;
            $length = strlen($input);
            if ($input[$length - 1] != "\0") {
                return false;
            }
            $data = $this->buffer;
            $this->buffer = '';
            return $data;
        } catch (\Throwable $th) {
            app('log')->error('Consumer Socket Read Has Error. ' . $th->getMessage());
            app('log')->error($th->getTraceAsString());
            return true;
        }
    }

    public function writeOnSocket($message)
    {
        $message = app('easy-socket')->prepareMessage($message);
        $this->temp = $message;
        try {
            socket_write($this->socket, $message);
        } catch (\Throwable $th) {
            app('log')->error('Consumer writeOnSocket Has Error. ' . $th->getMessage());
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

    public function responseReceived()
    {
        return $this->responseReceived = true;
    }

    public function waitingForResponse()
    {
        return $this->responseReceived = false;
    }

    public function isWaitingForResponse()
    {
        return !$this->responseReceived;
    }
}
