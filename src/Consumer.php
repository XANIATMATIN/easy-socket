<?php

namespace MatinUtils\EasySocket;


class Consumer
{
    protected $status = true, $socket, $continuous = false, $buffer = '';

    public function __construct($socket)
    {
        $this->socket = $socket;
    }

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
            app('log')->error('Socket Read Has Error. ' . $th->getMessage());
            app('log')->error($th->getTraceAsString());
            return true;
        }
    }

    public function writeOnSocket($message)
    {
        $message = $this->prepareMessage($message);
        try {
            socket_write($this->socket, $message);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    protected function prepareMessage($message)
    {
        if (($message[strlen($message) - 1] ?? '') != "\0") { ///> the message might already have /0
            $message .= "\0";
        }
        return $message;
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
