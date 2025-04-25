<?php
namespace Models\Router;

use Models\Router\Interfaces\RouteExecuteInterface;

class RouteExecute implements RouteExecuteInterface {

    private VerifyRoutes $verifyRoutes;
    private RouteExecute $routeExecute;
    
    public function __construct (VerifyRoutes $verifyRoutes) {
        $this->verifyRoutes = $verifyRoutes;
    }

    public function executeActionController ($routes): void {

        $this->verifyRoutes->setProperties($routes);

        $route = $this->verifyRoutes->verifyRouteWithUri();

        $this->verifyRoutes->verifyProtectedRoute($route);

        $this->verifyRoutes->verifyExistsController($route["controller"]);
        $controller = new $route["controller"];

        $action = $route["action"];
        $this->verifyRoutes->verifyExistsAction($controller, $action);

        $controller->$action();
        
    }
}