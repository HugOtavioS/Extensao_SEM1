<?php
use Models\Router\Router;
use Controllers\HomeController;
use Controllers\LoginController;
use Controllers\CadastroController;
use Controllers\PedidoController;

Router::addProtectedRoute("/", "GET", HomeController::class, "index");
Router::addProtectedRoute("/signin", "GET", CadastroController::class, "index");
Router::addRoute("/pedidos", "GET", PedidoController::class, "index");
Router::addRoute("/pedidos/dashboard", "GET", PedidoController::class, "dashboard");
Router::addRoute("/pedidos/getById", "POST", PedidoController::class, "getById");
Router::addRoute("/pedidos/create", "POST", PedidoController::class, "create");
Router::addRoute("/pedidos/update", "POST", PedidoController::class, "update");
Router::addRoute("/sair", "GET", LoginController::class, "logout");
Router::addRoute("/login", "GET", LoginController::class, "index");
Router::addRoute("/login?error", "GET", LoginController::class, "index");
Router::addRoute("/login/create", "POST", LoginController::class, "create");
Router::addRoute("/finalizar-pedido", "POST", PedidoController::class, "finalizarPedido");
Router::addRoute("/pedido-finalizado", "GET", PedidoController::class, "pedidoFinalizado");