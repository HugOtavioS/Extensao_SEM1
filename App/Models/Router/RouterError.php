<?php
namespace Models\Router;

use Controllers\ViewController;
// use Src\Route\Interfaces\InterfaceRouteError;

class RouterError  {

    private ViewController $view;

    public function __construct () {
        $this->view = new ViewController;
    }

    public function error ($mensage = ["msg" => "Service Not Found"], $code = 404):void {
        $this->view->load($code, $mensage);
        exit;
    }
    
}