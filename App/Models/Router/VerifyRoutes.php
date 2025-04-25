<?php
namespace Models\Router;

use Models\Router\Interfaces\VerifyRoutesInterface;
use Models\Request\Request;
use Exception;
use Models\Session\Session;

class VerifyRoutes implements VerifyRoutesInterface {

    private static $routes = [];
    private $routeError;


    public function __construct(RouterError $routerError) {
        $this->routeError = $routerError;
    }

    public function setProperties(array $routes) {
        self::$routes = $routes;
    }

    public function verifyRouteWithUri () {
        try {

            $result = false;
            $routes = $this->treatRoute(self::$routes);
            $uri = $this->treatUri(Request::getUri());
    
            foreach ($routes as $route) {
                // echo $route["uri"] . " - " . Request::getUri() . "<br>";
                if ($route["uri"] === $uri and 
                    $route["method"] === Request::getVerb()) {
                    $result = $route;
                }
            }
    
            if (!$result) {
                throw new Exception("Rota não encontrada");
            }

            return $result;
            
        } catch (Exception $e) {
            $this->routeError->error(["msg" => $e->getMessage(), "rota" => "/", "buttonMsg" => "Voltar"], 404);
        }
    }

    public function treatRoute ($routes) {
        try {
            $newRoutes = [];

            foreach ($routes as $route) {
                $newUri = explode("?", $route["uri"])[0];
                $newMethod = $route["method"];
                $newRoutes[] = [
                    "uri" => $newUri,
                    "method" => $newMethod,
                    "controller" => $route["controller"],
                    "action" => $route["action"],
                    "protected" => $route["protected"],
                ];
            }

            return $newRoutes;
        } catch (Exception $e) {
            
        }
    }

    public function treatUri ($uri) {
        $uri = explode("?", $uri)[0];
        return $uri;
    }

    public function verifyProtectedRoute ($route):void {

        try {
            if ($route["protected"] === true) {

                Session::init();

                if (!Session::get("user")) {
                    throw new Exception("Rota protegida, faça login para acessar");
                }

            }

        } catch (Exception $e) {
            $this->routeError->error(["msg" => $e->getMessage(), "rota" => "/login", "buttonMsg" => "Fazer Login"], 404);
        }

    }

    public function verifyExistsController ($controller):void {
        try {

            if (!class_exists($controller)) {
                throw new Exception("Erro Interno");
            }

        } catch (Exception $e) {
            $this->routeError->error(["msg" => $e->getMessage()], 404);
        }
    }

    public function verifyExistsAction ($controller, $action):void {
        try {

            if (!method_exists($controller, $action)) {
                throw new Exception("Ação não encontrada");
            }

        } catch (Exception $e) {
            $this->routeError->error(["msg" => $e->getMessage()], 404);
        }
    }

}