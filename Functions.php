<?php

function serveAndListen(string $portName)
{
    $socketFolder = base_path('bootstrap/easySocket');
    try {
        $newPort = socket_create(AF_UNIX, SOCK_STREAM, 0);
        socket_bind($newPort, "$socketFolder/$portName.sock");
    } catch (\Throwable $th) {
        unlink("$socketFolder/$portName.sock");
        socket_bind($newPort, "$socketFolder/$portName.sock");
    }
    chmod("$socketFolder/$portName.sock", 0777);

    socket_listen($newPort, 10);
    return $newPort;
}