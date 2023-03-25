<?php

function serveAndListen(string $port)
{
    if (is_numeric($port)) {
        return app('easy-socket')->serveOnPort($port);
    } else {
        return app('easy-socket')->serveOnFile($port);
    }
}

function connectToSocket(string $port)
{
    if (is_numeric($port)) {
        return app('easy-socket')->connectToPort($port);
    } else {
        return app('easy-socket')->connectToFile($port);
    }
}
