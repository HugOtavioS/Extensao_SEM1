<?php
namespace Controllers;

use Controllers\ViewController;
use Controllers\Interfaces\ControllerInterface;

class HomeController implements ControllerInterface{
    private $view;

    public function __construct () {
        $this->view = new ViewController();
    }

    public function index (...$args) {
        $this->view->load("home", ["title" => "Home"]);
    }
}