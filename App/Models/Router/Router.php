<?php
namespace Models\Router;

use Models\Router\RouterError;
use Models\Request\Request;
use Models\Session\Session;
use Exception;

class Router {

    private static $routes = [];
    private RouterError $routeError;
    private RouteExecute $routeExecute;
    private VerifyRoutes $verifyRoutes;

    public function __construct (RouterError $routeError, VerifyRoutes $verifyRoutes, RouteExecute $routeExecute) {
        $this->routeError = $routeError;
        $this->routeExecute = $routeExecute;
        $this->verifyRoutes = $verifyRoutes;
    }

    public function registerRoutes ():void {
        require __DIR__ . "../web.php";
        $this->routeExecute->executeActionController(self::$routes);
        // $this->executeActionController();
    }

    public static function addRoute ($uri, $method, $controller, $action): void {
        self::$routes[] = [
            "uri" => $uri,
            "method" => $method,
            "controller" => $controller,
            "action" => $action,
            "protected" => false,
        ];
    }

    public static function addProtectedRoute ($uri, $method, $controller, $action): void {
        self::$routes[] = [
            "uri" => $uri,
            "method" => $method,
            "controller" => $controller,
            "action" => $action,
            "protected" => true,
        ];
    }

    // public function getRoutes (): array {
    //     return self::$routes;
    // }

    // private function verifyRouteWithUri () {
    //     try {

    //         $result = false;
    //         $routes = $this->treatRoute(self::$routes);
    //         $uri = $this->treatUri(Request::getUri());
    
    //         foreach ($routes as $route) {
    //             // echo $route["uri"] . " - " . Request::getUri() . "<br>";
    //             if ($route["uri"] === $uri and 
    //                 $route["method"] === Request::getVerb()) {
    //                 $result = $route;
    //             }
    //         }
    
    //         if (!$result) {
    //             throw new Exception("Rota não encontrada");
    //         }

    //         return $result;
            
    //     } catch (Exception $e) {
    //         $this->routeError->error(["msg" => $e->getMessage()], 404);
    //     }
    // }

    // private function treatUri ($uri) {
    //     $uri = explode("?", $uri)[0];
    //     return $uri;
    // }

    // private function treatRoute ($routes) {
    //     try {
    //         $newRoutes = [];

    //         foreach ($routes as $route) {
    //             $newUri = explode("?", $route["uri"])[0];
    //             $newMethod = $route["method"];
    //             $newRoutes[] = [
    //                 "uri" => $newUri,
    //                 "method" => $newMethod,
    //                 "controller" => $route["controller"],
    //                 "action" => $route["action"],
    //                 "protected" => $route["protected"],
    //             ];
    //         }

    //         return $newRoutes;
    //     } catch (Exception $e) {
            
    //     }
    // }

    // private function verifyProtectedRoute ($route):void {

    //     try {
    //         if ($route["protected"] === true) {

    //             Session::init();

    //             if (!Session::get("user")) {
    //                 throw new Exception("Rota protegida, faça login para acessar");
    //             }

    //         }

    //     } catch (Exception $e) {
    //         $this->routeError->error(["msg" => $e->getMessage()], 404);
    //     }

    // }

    // private function executeActionController ():void {
    //     $route = $this->verifyRouteWithUri();

    //     $this->verifyProtectedRoute($route);

    //     $controller = new $route["controller"];
    //     $action = $route["action"];

    //     $this->verifyExistsAction($controller, $action);

    //     $controller->$action();
    // }

    // private function verifyExistsAction ($controller, $action):void {
    //     try {

    //         if (!is_object($controller)) {
    //             throw new Exception("Controller não encontrado");
    //         }

    //         if (!method_exists($controller, $action)) {
    //             throw new Exception("Ação não encontrada");
    //         }

    //     } catch (Exception $e) {
    //         $this->routeError->error(["msg" => $e->getMessage()], 404);
    //     }
    // }

}