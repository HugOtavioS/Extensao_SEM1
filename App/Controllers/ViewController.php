<?php
namespace Controllers;

use Controllers\Interfaces\ControllerInterface;

@["AllowDynamicProperties"];
class ViewController implements ControllerInterface {

    public function index(...$args) {

    }

    public function load (string $view, array $params = []):void {

        $this->createParams($params);

        require_once __DIR__ . "/../../app/Views/{$view}.php";

    }

    private function createParams (array $params):void {

        foreach ($params as $key => $value) {
            @$this->$key = $value;
        }

    }
}