<?php
namespace Models\Request;

class Request {

    public function __construct () {

    }

    public static function getUri (string $separator = "false"):string {

        if (!$separator) {
            return $_SERVER['REQUEST_URI'];
        }

        return explode($separator, $_SERVER["REQUEST_URI"])[0];

    }

    public static function getVerb ():string {

        return $_SERVER['REQUEST_METHOD'];

    }

    public static function redirect (string $url, int $code = 301):void {

        header("Location: {$url}", true, $code);
        exit();
        
    }
}