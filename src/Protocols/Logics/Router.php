<?php

namespace MatinUtils\EasySocket\Protocols\Logics;

interface Router
{
    public function handle(Request $request, Response &$response);
}
