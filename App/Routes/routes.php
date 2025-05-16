<?php
use Models\Router\Router;
use Controllers\HomeController;
use Controllers\LoginController;
use Controllers\CadastroController;
use Controllers\PedidoController;
use Controllers\MapaController;
use Controllers\CaixaController;
use Controllers\API\APIController;

Router::addProtectedRoute("/", "GET", HomeController::class, "index");
Router::addProtectedRoute("/signin", "GET", CadastroController::class, "index");
Router::addProtectedRoute("/pedidos", "GET", PedidoController::class, "index");
Router::addProtectedRoute("/pedidos/dashboard", "GET", PedidoController::class, "dashboard");
Router::addProtectedRoute("/pedidos/getById", "POST", PedidoController::class, "getById");
Router::addProtectedRoute("/pedidos/create", "POST", PedidoController::class, "create");
Router::addProtectedRoute("/pedidos/update", "POST", PedidoController::class, "update");
Router::addProtectedRoute("/sair", "GET", LoginController::class, "logout");
Router::addRoute("/login", "GET", LoginController::class, "index");
Router::addRoute("/login?error", "GET", LoginController::class, "index");
Router::addRoute("/login/create", "POST", LoginController::class, "create");
Router::addProtectedRoute("/finalizar-pedido", "POST", PedidoController::class, "finalizarPedido");
Router::addProtectedRoute("/pedido-finalizado", "GET", PedidoController::class, "pedidoFinalizado");

// Rotas para o mapa de coleta
Router::addProtectedRoute("/mapa-coleta", "GET", MapaController::class, "index");
Router::addRoute("/api/pontos-coleta", "GET", MapaController::class, "getPontosColeta");
Router::addRoute("/api/pontos-coleta/proximos", "POST", MapaController::class, "getPontosColetaProximos");

// Rotas para o caixa
Router::addProtectedRoute("/caixa/status", "GET", CaixaController::class, "getStatus");
Router::addProtectedRoute("/caixa/dados", "GET", CaixaController::class, "getDados");
Router::addProtectedRoute("/caixa/abrir", "POST", CaixaController::class, "abrirCaixa");

// Rotas para API
Router::addRoute("/api/registrar-venda-material", "POST", APIController::class, "registrarVendaMaterial"); 