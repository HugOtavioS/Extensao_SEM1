<?php
namespace Models\Router\Interfaces;

interface VerifyRoutesInterface {
    public function verifyExistsAction ($controller, $action);
    public function verifyExistsController ($controller);
    public function verifyRouteWithUri ();
    public function verifyProtectedRoute (array $route);
    public function treatRoute (array $routes);
    public function treatUri (string $uri);
    // public function getRouteError (): object;
}