<?php

namespace MatinUtils\EasySocket;

class Routing
{
    protected $routes;

    public function __construct()
    {
        $routesPath = base_path('/routes/socket.php');
        if (file_exists($routesPath)) {
            $this->routes = require $routesPath;
        }
    }

    public function handle(string $input, $client)
    {
        foreach ($this->routes ?? [] as $pattern => $action) {
            if (!preg_match("/$pattern/", $input, $match)) {
                continue;
            }

            if (is_callable($action)) {
                return $action($input);
            }

            if (is_string($action)) {
                list($controller, $function) = explode('@', $action);
                $class = "App\EasySocket\Controllers\\$controller";
                $controller = new $class;
                return $controller->$function($input);
            }
        }
    }
}
