<?php
use Models\Router\Router;
use Controllers\HomeController;
use Controllers\LoginController;
use Controllers\CadastroController;

Router::addProtectedRoute("/", "GET", HomeController::class, "index");
Router::addProtectedRoute("/signin", "GET", CadastroController::class, "index");
Router::addRoute("/sair", "GET", LoginController::class, "logout");
Router::addRoute("/login", "GET", LoginController::class, "index");
Router::addRoute("/login?error", "GET", LoginController::class, "index");
Router::addRoute("/login/create", "POST", LoginController::class, "create");