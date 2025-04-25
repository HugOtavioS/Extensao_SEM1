<?php
namespace Models\Router\Interfaces;

interface RouteExecuteInterface {
    public function executeActionController($routes): void;
}