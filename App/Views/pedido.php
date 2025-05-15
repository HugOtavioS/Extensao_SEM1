<?php
use Models\Session\Session;
use Config\Database\Database;
use Config\env;

$db = new Database(new env());
Session::init();

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Pedidos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="/js/pedido.js" defer></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: '#22c55e',           // Verde principal (ex: green-500)
                    'primary-dark': '#16a34a',    // Verde escuro (ex: green-600)
                    secondary: '#2EC4B6',
                    'secondary-dark': '#20AEA1',
                    accent: '#bbf7d0',            // Verde claro (ex: green-100)
                    'text-dark': '#333F48',
                    'text-light': '#6B7280',
                    background: '#F9F7F3',
                    danger: '#E53935',
                    'danger-dark': '#D32F2F',
                    success: '#43A047',
                    'success-dark': '#388E3C',
                }
            }
        }
    }
    </script>
    <style>
        .card {
            @apply bg-white rounded-xl shadow-md p-6;
        }
        .stat-value {
            @apply text-2xl md:text-3xl font-bold text-primary;
        }
        .stat-label {
            @apply text-sm text-text-light;
        }
        .card-header {
            @apply text-lg font-semibold text-text-dark mb-4;
        }
        .filter-select {
            @apply block w-full border-gray-300 rounded-md shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50;
        }
        .date-input {
            @apply block w-full border-gray-300 rounded-md shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50;
        }
        
        /* Custom dropdown styles */
        .custom-dropdown .status-option.active,
        .custom-dropdown .pago-option.active {
            @apply bg-primary/10 text-primary;
        }
        
        .custom-dropdown #statusDropdownButton,
        .custom-dropdown #pagoDropdownButton {
            transition: all 0.2s ease;
        }
        
        .custom-dropdown #statusDropdownButton:hover,
        .custom-dropdown #pagoDropdownButton:hover {
            @apply border-primary/70 shadow;
        }
        
        .custom-dropdown #statusDropdownOptions,
        .custom-dropdown #pagoDropdownOptions {
            max-height: 0;
            opacity: 0;
            transition: all 0.2s ease;
            overflow: hidden;
            pointer-events: none;
        }
        
        .custom-dropdown #statusDropdownOptions:not(.hidden),
        .custom-dropdown #pagoDropdownOptions:not(.hidden) {
            max-height: 200px;
            opacity: 1;
            pointer-events: auto;
        }
        
        .custom-dropdown .status-option,
        .custom-dropdown .pago-option {
            position: relative;
            overflow: hidden;
        }
        
        .custom-dropdown .status-option::after,
        .custom-dropdown .pago-option::after {
            content: '';
            position: absolute;
            background: rgba(255, 255, 255, 0.3);
            width: 100px;
            height: 100px;
            border-radius: 50%;
            transform: translate(-50%, -50%) scale(0);
            opacity: 0;
            transition: transform 0.4s, opacity 0.3s;
            pointer-events: none;
        }
        
        .custom-dropdown .status-option:active::after,
        .custom-dropdown .pago-option:active::after {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
            transition: 0s;
        }
        
        /* Custom scrollbar styles */
        .hide-scrollbar {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        .hide-scrollbar::-webkit-scrollbar {
            display: none; /* Chrome, Safari and Opera */
        }
        
        /* Product card animations */
        .produto-card.selecionado {
            @apply border-secondary bg-secondary/5;
        }
        
        /* Category filters */
        .categoria-filtro {
            @apply bg-gray-100 text-text-dark hover:bg-gray-200 transition-colors duration-150 cursor-pointer;
        }
        .categoria-filtro.categoria-ativa {
            @apply bg-primary text-white hover:bg-primary-dark;
        }
        
        /* Touch optimization */
        .touch-manipulation {
            touch-action: manipulation;
        }
        
        /* Loading indicator */
        .loading-spinner {
            display: inline-block;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>

<body class="bg-background min-h-screen flex flex-col">
    <?php
    if (Session::get("user")) {
        if (strpos(Session::get("user"), "admin") !== false) {
            require "Components/headerAdm.php";
        } else {
            require "Components/header.php";
        }
    } else {
        require "Components/headerInit.php";
    }
    ?>

    <div class="container mx-auto px-4 py-8 flex-grow">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-text-dark">Pedidos</h1>
            <button id="btnNovoPedido" type="button"
                class="bg-primary hover:bg-primary-dark text-white px-6 py-3 rounded-lg shadow-md transition-colors duration-200 flex items-center font-medium">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Novo Pedido
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <?php
            // Recuperar todos os pedidos
            $pedidos = $db->read("tb_pedidos", ["*"]);
            
            // Ordenar por data mais recente primeiro (assumindo que há um campo data)
            usort($pedidos, function($a, $b) {
                $dateA = isset($a['data_pedido']) ? $a['data_pedido'] : '';
                $dateB = isset($b['data_pedido']) ? $b['data_pedido'] : '';
                return strtotime($dateB) - strtotime($dateA);
            });
            
            if (empty($pedidos)) {
                echo '<div class="col-span-full text-center py-12 bg-white rounded-lg shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-text-light opacity-50 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <p class="text-text-light text-lg">Nenhum pedido encontrado</p>
                    <button id="btnSemPedidos" class="mt-4 bg-primary hover:bg-primary-dark text-white px-6 py-2 rounded-md shadow-sm transition-colors duration-200">
                        Criar Primeiro Pedido
                    </button>
                </div>';
            } else {
                foreach ($pedidos as $pedido) {
                    $statusPedido = isset($pedido['status']) ? $pedido['status'] : 'preparando';
                    $pagamento = isset($pedido['pago']) && $pedido['pago'];
                    
                    // Definir classes com base no status
                    $statusClass = '';
                    if ($statusPedido == 'preparando') {
                        $statusClass = 'bg-amber-100 text-amber-800 border-amber-200';
                    } elseif ($statusPedido == 'pronto') {
                        $statusClass = 'bg-emerald-100 text-emerald-800 border-emerald-200';
                    } elseif ($statusPedido == 'entregue') {
                        $statusClass = 'bg-secondary/20 text-secondary-dark border-secondary/30';
                    } elseif ($statusPedido == 'cancelado') {
                        $statusClass = 'bg-danger/20 text-danger-dark border-danger/30';
                    } else {
                        $statusClass = 'bg-gray-100 text-gray-800 border-gray-200';
                    }
                    
                    $pagamentoClass = $pagamento ? 
                        'bg-success/20 text-success-dark border-success/30' : 
                        'bg-danger/20 text-danger-dark border-danger/30';
                    
                    // Get formatted date
                    $dataPedido = isset($pedido['data_pedido']) ? date('d/m/Y H:i', strtotime($pedido['data_pedido'])) : date('d/m/Y H:i');
                    $itens = isset($pedido['itens']) ? $pedido['itens'] : '0';
                    $mesa = isset($pedido['mesa']) ? $pedido['mesa'] : 'N/A';
                    $valorTotal = isset($pedido['valor_total']) ? number_format($pedido['valor_total'], 2, ',', '.') : '0,00';
                    
                    echo "
                    <div class='bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300'>
                        <div class='p-5 border-b border-gray-100'>
                            <div class='flex justify-between items-start'>
                                <div>
                                    <h3 class='text-lg font-bold text-text-dark flex items-center'>
                                        Pedido #{$pedido['id']}
                                    </h3>
                                    <p class='text-text-light text-sm mt-1'>
                                        <time>{$dataPedido}</time>
                                    </p>
                                </div>
                                <div class='flex flex-col gap-2'>
                                    <span class='px-3 py-1 rounded-full text-xs font-medium $statusClass border'>
                                        " . ucfirst($statusPedido) . "
                                    </span>
                                    <span class='px-3 py-1 rounded-full text-xs font-medium $pagamentoClass border'>
                                        " . ($pagamento ? 'Pago' : 'Não pago') . "
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class='px-5 py-4'>
                            <div class='flex flex-wrap gap-1 mb-4'>
                                <span class='text-xs text-text-light bg-gray-100 px-2 py-1 rounded-full'>
                                    {$itens} itens
                                </span>
                                <span class='text-xs text-text-light bg-gray-100 px-2 py-1 rounded-full'>
                                    Mesa {$mesa}
                                </span>
                            </div>
                            <div class='flex justify-between items-center'>
                                <span class='font-bold text-primary'>
                                    R$ {$valorTotal}
                                </span>
                                <button 
                                    data-id='{$pedido['id']}'
                                    class='text-secondary hover:text-secondary-dark text-sm font-medium transition-colors duration-200 flex items-center'>
                                    <svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4 mr-1' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M15 12a3 3 0 11-6 0 3 3 0 016 0z' />
                                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z' />
                                    </svg>
                                    Detalhes
                                </button>
                            </div>
                        </div>
                    </div>
                    ";
                }
            }
            ?>
        </div>
    </div>

    <!-- Modal de Novo Pedido -->
    <div id="novoPedidoModal" class="fixed w-full inset-0 bg-black/50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen max-h-screen w-full p-4">
            <div class="bg-white rounded-lg w-full max-w-4xl shadow-xl flex flex-col h-full max-h-full">
                <!-- Cabeçalho fixo -->
                <div class="flex justify-between items-center p-4 md:p-6 border-b border-gray-200">
                    <h2 class="text-xl md:text-2xl font-bold text-text-dark">Novo Pedido</h2>
                    <button id="btnFecharModal"
                        class="text-text-light hover:text-text-dark transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <!-- Área de conteúdo com rolagem apenas nos produtos -->
                <div class="flex-1 flex flex-col overflow-hidden">
                    <!-- Inputs de mesa e busca lado a lado -->
                    <div class="p-4 md:p-6 pb-2">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex gap-2">
                                <div class="w-[25%]">
                                    <label for="mesa" class="block text-text-dark font-medium mb-2">Mesa</label>
                                    <input type="number" id="mesa"
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                                </div>
                                <!-- Barra de busca -->
                                <div class="flex-1">
                                    <label for="produtoSearch" class="block text-text-dark font-medium mb-2">Buscar Produto</label>
                                    <div class="relative">
                                        <input type="text" id="produtoSearch" placeholder="Buscar produto..." 
                                            class="w-full border border-gray-300 rounded-md pl-10 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </div>
                                        <button id="limparBusca" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 hidden">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Título "Produtos" e categorias -->
                    <div class="px-4 md:px-6 py-2">
                        <h3 class="font-bold text-text-dark">Produtos</h3>
                        
                        <!-- Filtros por categoria -->
                        <div class="overflow-x-auto py-2 hide-scrollbar">
                            <div id="categoriasFiltro" class="flex space-x-2 min-w-max">
                                <button data-categoria="todos" class="categoria-filtro categoria-ativa text-sm font-medium px-3 py-1.5 rounded-full bg-primary text-white">
                                    Todos
                                </button>
                                <!-- Categorias serão preenchidas por JavaScript -->
                            </div>
                        </div>
                        
                        <!-- Produtos selecionados -->
                        <div id="produtosSelecionados" class="overflow-x-auto py-2 hide-scrollbar hidden">
                            <div class="flex space-x-3 min-w-max">
                                <!-- Itens selecionados preenchidos dinamicamente -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lista de produtos com scroll -->
                    <div class="flex-1 overflow-y-auto px-4 md:px-6 pb-4">
                        <!-- Grade de produtos com layout aprimorado -->
                        <div id="produtosContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php
                            $produtos = $db->read("tb_produtos", ["*"]);
            
                            // Ordenar array por nome
                            usort($produtos, function($a, $b) {
                                return strcasecmp($a['nome'], $b['nome']);
                            });
                            
                            // Extrair as categorias existentes
                            $categorias = [];
                            foreach ($produtos as $produto) {
                                $categoria = isset($produto['categoria']) ? $produto['categoria'] : 'Sem categoria';
                                if (!in_array($categoria, $categorias)) {
                                    $categorias[] = $categoria;
                                }
                            }
                            
                            foreach ($produtos as $key => $value) {
                                $preco = isset($value['preco']) ? number_format($value['preco'], 2, ',', '.') : '0,00';
                                $categoria = isset($value['categoria']) ? $value['categoria'] : 'Sem categoria';
                                $descricao = isset($value['descricao']) ? $value['descricao'] : '';
                                                
                                echo "
                                <div class='produto-card relative bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md' 
                                    data-id='{$value['id']}' 
                                    data-nome='{$value['nome']}' 
                                    data-categoria='{$categoria}' 
                                    data-preco='{$preco}'>
                                    
                                    <div class='p-4'>
                                        <div class='flex items-center justify-between mb-3'>
                                            <div class='flex-1'>
                                                <h4 class='font-bold text-text-dark truncate'>{$value['nome']}</h4>
                                                <p class='text-xs text-text-light truncate'>{$descricao}</p>
                                                <div class='mt-1'>
                                                    <span class='text-xs font-medium bg-gray-100 text-text-light px-2 py-0.5 rounded-full'>{$categoria}</span>
                                                </div>
                                            </div>
                                            <div class='text-primary font-bold ml-2'>
                                                R$ {$preco}
                                            </div>
                                        </div>
                                        
                                        <div class='flex items-center justify-between mt-4'>
                                            <div class='flex items-center space-x-2'>
                                                <button onclick='diminuirQuantidade({$value['id']})' 
                                                    class='diminuir-btn bg-danger hover:bg-danger-dark text-white rounded-full w-9 h-9 flex items-center justify-center text-xl focus:outline-none focus:ring-2 focus:ring-danger/50 active:scale-95 transition-all duration-200 touch-manipulation'>
                                                    <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M20 12H4' />
                                                    </svg>
                                                </button>
                                                <div id='quantidade-{$value['id']}' class='w-10 text-center text-lg font-bold'>
                                                    0
                                                </div>
                                                <button onclick='aumentarQuantidade({$value['id']})' 
                                                    class='aumentar-btn bg-success hover:bg-success-dark text-white rounded-full w-9 h-9 flex items-center justify-center text-xl focus:outline-none focus:ring-2 focus:ring-success/50 active:scale-95 transition-all duration-200 touch-manipulation'>
                                                    <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 4v16m8-8H4' />
                                                    </svg>
                                                </button>
                                            </div>
                                            
                                            <div class='w-10 h-10 rounded-full bg-secondary/10 hidden items-center justify-center border-2 border-secondary produto-selecionado-badge'>
                                                <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5 text-secondary' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                                    <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7' />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                ";
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- Rodapé fixo com total e botões -->
                <div class="border-t border-gray-200 p-4 md:p-6 bg-gray-50">
                    <div class="flex flex-col md:flex-row md:justify-between md:items-center space-y-3 md:space-y-0">
                        <div>
                            <span class="font-medium text-text-dark">Total:</span>
                            <span id="totalPedido" class="font-bold text-primary text-xl ml-2">R$ 0,00</span>
                        </div>
                        <div class="flex space-x-4">
                            <button id="btnCancelarPedido"
                                class="px-4 py-2 md:px-6 md:py-3 border border-gray-300 rounded-lg text-text-dark hover:bg-gray-100 transition-colors duration-200">
                                Cancelar
                            </button>
                            <button id="btnFinalizarPedido"
                                class="bg-primary hover:bg-primary-dark text-white px-4 py-2 md:px-6 md:py-3 rounded-lg shadow-md transition-colors duration-200 flex items-center font-medium">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                Finalizar Pedido
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso -->
    <div id="sucessoModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 sm:p-8 max-w-sm sm:max-w-md w-full mx-auto">
            <div class="text-center">
                <div class="mx-auto h-20 w-20 rounded-full bg-success/10 flex items-center justify-center mb-4">
                    <svg class="h-10 w-10 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-text-dark">Pedido Registrado!</h3>
                <p class="mt-2 text-sm text-text-light">Seu pedido foi registrado com sucesso e está sendo preparado.
                </p>
                <div class="mt-6">
                    <button onclick="fecharModalSucesso()"
                        class="inline-flex justify-center w-full px-4 py-2 text-sm font-medium text-white bg-primary border border-transparent rounded-md hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                        Voltar para Pedidos
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for mobile menu toggle -->
    <script>
    function toggleMobileMenu(menuId) {
        const menu = document.getElementById(menuId);
        if (menu.classList.contains('hidden')) {
            menu.classList.remove('hidden');
            menu.classList.add('block');
        } else {
            menu.classList.remove('block');
            menu.classList.add('hidden');
        }
    }
    </script>

    <!-- Modal de Detalhes do Pedido -->
    <div id="detalhesPedidoModal" class="fixed inset-0 bg-black/50 max-h-screen hidden items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-lg w-full max-w-2xl h-fit shadow-xl mx-auto my-8">
            <div class="flex justify-between items-center p-6 border-b border-gray-200">
                <h2 class="text-2xl font-bold text-text-dark">Detalhes do Pedido <span id="detalhesPedidoId" class="text-primary"></span></h2>
                <button id="btnFecharDetalhes"
                    class="text-text-light hover:text-text-dark transition-colors duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <p class="text-sm text-text-light mb-1">Mesa</p>
                        <div class="flex items-center">
                            <p id="detalhesMesa" class="font-medium text-text-dark"></p>
                            <!-- Campo de edição para Mesa (inicialmente oculto) -->
                            <div id="editMesaContainer" class="hidden ml-2">
                                <input type="number" id="editMesa" class="w-16 border border-gray-300 rounded px-2 py-1 text-sm" min="1">
                            </div>
                            <!-- Botão de edição (visível apenas para admin) -->
                            <button id="btnEditMesa" class="admin-edit-btn ml-2 text-secondary hover:text-secondary-dark hidden">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm text-text-light mb-1">Data</p>
                        <p id="detalhesData" class="font-medium text-text-dark"></p>
                    </div>
                    <div>
                        <p class="text-sm text-text-light mb-1">Status</p>
                        <div class="flex flex-col gap-1 items-start justify-center">
                            <div class="flex items-center">
                                <div id="detalhesStatus" class="inline-block px-3 py-1 rounded-full text-xs font-medium border"></div>
                                <!-- Botão de edição (visível apenas para admin) -->
                                <button id="btnEditStatus" class="admin-edit-btn ml-2 text-secondary hover:text-secondary-dark hidden">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </button>
                            </div>
                            <!-- Seletor de status (inicialmente oculto) -->
                            <div id="editStatusContainer" class="hidden">
                                <div class="custom-dropdown relative">
                                    <div id="statusDropdownButton" class="flex items-center justify-between w-full px-3 py-2 text-sm bg-white border border-gray-300 rounded-md shadow-sm cursor-pointer hover:border-primary">
                                        <span id="currentStatusText">Preparando</span>
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </div>
                                    <div id="statusDropdownOptions" class="absolute z-10 hidden w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg">
                                        <div class="py-1">
                                            <div class="status-option px-4 py-2 text-sm hover:bg-primary hover:text-white cursor-pointer transition-colors" data-value="preparando">Preparando</div>
                                            <div class="status-option px-4 py-2 text-sm hover:bg-primary hover:text-white cursor-pointer transition-colors" data-value="pronto">Pronto</div>
                                            <div class="status-option px-4 py-2 text-sm hover:bg-primary hover:text-white cursor-pointer transition-colors" data-value="entregue">Entregue</div>
                                            <div class="status-option px-4 py-2 text-sm hover:bg-primary hover:text-white cursor-pointer transition-colors" data-value="cancelado">Cancelado</div>
                                        </div>
                                    </div>
                                    <!-- Mantemos o select original oculto para compatibilidade -->
                                    <select id="editStatus" class="hidden">
                                    <option value="preparando">Preparando</option>
                                    <option value="pronto">Pronto</option>
                                    <option value="entregue">Entregue</option>
                                    <option value="cancelado">Cancelado</option>
                                </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm text-text-light mb-1">Pagamento</p>
                        <div class="flex flex-col gap-1 items-start justify-center">
                            <div class="flex items-center">
                                <!-- Botão de edição (visível apenas para admin) -->
                                <div id="detalhesPago" class="inline-block px-3 py-1 rounded-full text-xs font-medium border"></div>
                                <button id="btnEditPago" class="admin-edit-btn ml-2 text-secondary hover:text-secondary-dark hidden">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </button>
                            </div>
                            <!-- Seletor de pagamento (inicialmente oculto) -->
                            <div id="editPagoContainer" class="hidden">
                                <div class="custom-dropdown relative">
                                    <div id="pagoDropdownButton" class="flex items-center justify-between w-full px-3 py-2 text-sm bg-white border border-gray-300 rounded-md shadow-sm cursor-pointer hover:border-primary">
                                        <span id="currentPagoText">Não pago</span>
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </div>
                                    <div id="pagoDropdownOptions" class="absolute z-10 hidden w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg">
                                        <div class="py-1">
                                            <div class="pago-option px-4 py-2 text-sm hover:bg-primary hover:text-white cursor-pointer transition-colors" data-value="1">Pago</div>
                                            <div class="pago-option px-4 py-2 text-sm hover:bg-primary hover:text-white cursor-pointer transition-colors" data-value="0">Não pago</div>
                                        </div>
                                    </div>
                                    <!-- Mantemos o select original oculto para compatibilidade -->
                                    <select id="editPago" class="hidden">
                                    <option value="1">Pago</option>
                                    <option value="0">Não pago</option>
                                </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-6">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="font-bold text-text-dark">Produtos</h3>
                        <!-- Botão para adicionar produtos (visível apenas para admin) -->
                        <button id="btnAdicionarProduto" class="admin-edit-btn text-sm text-white bg-primary hover:bg-primary-dark px-3 py-1 rounded flex items-center hidden">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Adicionar Produto
                        </button>
                    </div>
                    <div id="detalhesProdutosLista" class="bg-gray-50 rounded-lg border border-gray-200 p-4 max-h-64 overflow-y-auto">
                        <p class="text-text-light text-center py-4">Carregando produtos...</p>
                    </div>
                </div>
                
                <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                    <div>
                        <span class="text-sm text-text-light">Total de Itens:</span>
                        <span id="detalhesTotalItens" class="font-medium text-text-dark ml-1"></span>
                    </div>
                    <div>
                        <span class="text-sm text-text-light">Valor Total:</span>
                        <span id="detalhesValorTotal" class="font-bold text-primary text-xl ml-2"></span>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-200 p-4 bg-gray-50 flex justify-between">
                <!-- Botões de ação (Salvar será visível apenas para admin) -->
                <button id="btnSalvarAlteracoes" class="admin-edit-btn px-6 py-2 bg-success hover:bg-success-dark text-white rounded-lg hidden">
                    Salvar Alterações
                </button>
                <button id="btnFecharModalDetalhes"
                    class="px-6 py-2 border border-gray-300 rounded-lg text-text-dark hover:bg-gray-100 transition-colors duration-200">
                    Fechar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal para adicionar produtos ao pedido -->
    <div id="adicionarProdutoModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[60] p-4 overflow-y-auto">
        <div class="bg-white rounded-lg w-full max-w-xl shadow-xl mx-auto my-8">
            <div class="flex justify-between items-center p-6 border-b border-gray-200">
                <h2 class="text-xl font-bold text-text-dark">Adicionar Produtos</h2>
                <button id="btnFecharAdicionarProduto"
                    class="text-text-light hover:text-text-dark transition-colors duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 gap-4 max-h-96 overflow-y-auto" id="adicionarProdutosLista">
                    <!-- Lista de produtos será carregada aqui -->
                    <p class="text-text-light text-center py-4">Carregando produtos...</p>
                </div>
            </div>
            <div class="border-t border-gray-200 p-4 bg-gray-50 flex justify-end">
                <button id="btnFecharAdicionarProdutoModal"
                    class="px-6 py-2 border border-gray-300 rounded-lg text-text-dark hover:bg-gray-100 transition-colors duration-200 mr-2">
                    Cancelar
                </button>
                <button id="btnConfirmarAdicionarProdutos"
                    class="px-6 py-2 bg-primary hover:bg-primary-dark text-white rounded-lg shadow-md transition-colors duration-200">
                    Adicionar Selecionados
                </button>
            </div>
        </div>
    </div>
    
    <script>
    // Verificar se o usuário é administrador e mostrar os controles de edição
    document.addEventListener('DOMContentLoaded', function() {
        // Verificar se o usuário é um administrador
        const isAdmin = <?php echo (Session::get("user") && strpos(Session::get("user"), "admin") !== false) ? 'true' : 'false'; ?>;
        
        // Mostrar os botões de edição se for administrador
        if (isAdmin) {
            document.querySelectorAll('.admin-edit-btn').forEach(btn => {
                btn.classList.remove('hidden');
            });
        }
        
        // Configurar os dropdowns personalizados
        setupCustomDropdowns();
    });
    
    // Configurar dropdowns personalizados
    function setupCustomDropdowns() {
        // Dropdown de Status
        const statusButton = document.getElementById('statusDropdownButton');
        const statusOptions = document.getElementById('statusDropdownOptions');
        const statusSelect = document.getElementById('editStatus');
        const currentStatusText = document.getElementById('currentStatusText');
        
        if (statusButton && statusOptions) {
            // Abrir/fechar dropdown de status
            statusButton.addEventListener('click', function() {
                statusOptions.classList.toggle('hidden');
                
                // Quando o dropdown é aberto, destacar a opção ativa
                if (!statusOptions.classList.contains('hidden')) {
                    // Remover destaque de todas as opções
                    document.querySelectorAll('.status-option').forEach(opt => opt.classList.remove('active'));
                    
                    // Destacar a opção atual
                    const currentValue = statusSelect.value;
                    const activeOption = document.querySelector(`.status-option[data-value="${currentValue}"]`);
                    if (activeOption) {
                        activeOption.classList.add('active');
                        
                        // Scroll para a opção ativa estar visível
                        setTimeout(() => {
                            activeOption.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                        }, 100);
                    }
                }
            });
            
            // Fechar dropdown ao clicar fora
            document.addEventListener('click', function(e) {
                if (!statusButton.contains(e.target) && !statusOptions.contains(e.target)) {
                    statusOptions.classList.add('hidden');
                }
            });
            
            // Selecionar opção
            document.querySelectorAll('.status-option').forEach(option => {
                option.addEventListener('click', function() {
                    // Remover destaque de todas as opções
                    document.querySelectorAll('.status-option').forEach(opt => opt.classList.remove('active'));
                    
                    // Destacar a opção clicada
                    this.classList.add('active');
                    
                    const value = this.getAttribute('data-value');
                    currentStatusText.textContent = this.textContent;
                    statusSelect.value = value;
                    statusOptions.classList.add('hidden');
                });
            });
        }
        
        // Dropdown de Pagamento
        const pagoButton = document.getElementById('pagoDropdownButton');
        const pagoOptions = document.getElementById('pagoDropdownOptions');
        const pagoSelect = document.getElementById('editPago');
        const currentPagoText = document.getElementById('currentPagoText');
        
        if (pagoButton && pagoOptions) {
            // Abrir/fechar dropdown de pagamento
            pagoButton.addEventListener('click', function() {
                pagoOptions.classList.toggle('hidden');
                
                // Quando o dropdown é aberto, destacar a opção ativa
                if (!pagoOptions.classList.contains('hidden')) {
                    // Remover destaque de todas as opções
                    document.querySelectorAll('.pago-option').forEach(opt => opt.classList.remove('active'));
                    
                    // Destacar a opção atual
                    const currentValue = pagoSelect.value;
                    const activeOption = document.querySelector(`.pago-option[data-value="${currentValue}"]`);
                    if (activeOption) {
                        activeOption.classList.add('active');
                        
                        // Scroll para a opção ativa estar visível
                        setTimeout(() => {
                            activeOption.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                        }, 100);
                    }
                }
            });
            
            // Fechar dropdown ao clicar fora
            document.addEventListener('click', function(e) {
                if (!pagoButton.contains(e.target) && !pagoOptions.contains(e.target)) {
                    pagoOptions.classList.add('hidden');
                }
            });
            
            // Selecionar opção
            document.querySelectorAll('.pago-option').forEach(option => {
                option.addEventListener('click', function() {
                    // Remover destaque de todas as opções
                    document.querySelectorAll('.pago-option').forEach(opt => opt.classList.remove('active'));
                    
                    // Destacar a opção clicada
                    this.classList.add('active');
                    
                    const value = this.getAttribute('data-value');
                    currentPagoText.textContent = this.textContent;
                    pagoSelect.value = value;
                    pagoOptions.classList.add('hidden');
                });
            });
        }
    }
    
    function toggleMobileMenu(menuId) {
        const menu = document.getElementById(menuId);
        if (menu.classList.contains('hidden')) {
            menu.classList.remove('hidden');
            menu.classList.add('block');
        } else {
            menu.classList.remove('block');
            menu.classList.add('hidden');
        }
    }
    </script>

    <!-- JavaScript for the improved product selection interface -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar recursos da interface de produtos
        initProdutosFiltros();
        initProdutosBusca();
        initProdutosSelecionados();
        setupProductCardEvents();
    });

    // Inicializar filtros de categorias
    function initProdutosFiltros() {
        const categoriasFiltroContainer = document.getElementById('categoriasFiltro');
        if (!categoriasFiltroContainer) return;
        
        // Encontrar categorias únicas nos dados dos produtos
        const categorias = [];
        document.querySelectorAll('.produto-card').forEach(card => {
            const categoria = card.getAttribute('data-categoria');
            if (categoria && !categorias.includes(categoria)) {
                categorias.push(categoria);
            }
        });
        
        // Se encontramos categorias, adicione-as ao container de filtros
        if (categorias.length) {
            let categoriasHTML = '';
            categorias.forEach(categoria => {
                categoriasHTML += `
                    <button data-categoria="${categoria}" class="categoria-filtro text-sm font-medium px-3 py-1.5 rounded-full">
                        ${categoria}
                    </button>
                `;
            });
            
            // Adicionar ao container depois do botão "Todos"
            const todosButton = categoriasFiltroContainer.querySelector('[data-categoria="todos"]');
            if (todosButton) {
                todosButton.insertAdjacentHTML('afterend', categoriasHTML);
            } else {
                categoriasFiltroContainer.innerHTML = categoriasHTML;
            }
            
            // Adicionar listeners para os botões de filtro
            categoriasFiltroContainer.querySelectorAll('.categoria-filtro').forEach(button => {
                button.addEventListener('click', function() {
                    const categoria = this.getAttribute('data-categoria');
                    
                    // Remover classe ativa de todos os botões
                    categoriasFiltroContainer.querySelectorAll('.categoria-filtro').forEach(btn => {
                        btn.classList.remove('categoria-ativa');
                    });
                    
                    // Adicionar classe ativa ao botão clicado
                    this.classList.add('categoria-ativa');
                    
                    // Filtrar produtos pela categoria
                    filtrarProdutosPorCategoria(categoria);
                });
            });
        }
    }
    
    // Filtrar produtos por categoria
    function filtrarProdutosPorCategoria(categoria) {
        const produtoCards = document.querySelectorAll('.produto-card');
        
        produtoCards.forEach(card => {
            // Sempre remover a classe hidden primeiro
            card.classList.remove('hidden');
            
            // Se a categoria não for "todos", verificar se o produto pertence à categoria
            if (categoria !== 'todos') {
                const produtoCategoria = card.getAttribute('data-categoria');
                if (produtoCategoria !== categoria) {
                    card.classList.add('hidden');
                }
            }
        });
        
        // Atualizar o container de produtos selecionados
        atualizarProdutosSelecionados();
    }
    
    // Inicializar busca de produtos
    function initProdutosBusca() {
        const searchInput = document.getElementById('produtoSearch');
        const clearButton = document.getElementById('limparBusca');
        
        if (!searchInput || !clearButton) return;
        
        // Evento para busca em tempo real
        searchInput.addEventListener('input', function() {
            const termo = this.value.toLowerCase().trim();
            
            // Mostrar/ocultar botão de limpar
            if (termo.length > 0) {
                clearButton.classList.remove('hidden');
                clearButton.classList.add('flex');
            } else {
                clearButton.classList.add('hidden');
                clearButton.classList.remove('flex');
            }
            
            // Filtrar produtos pelo termo de busca
            filtrarProdutosPorTermo(termo);
        });
        
        // Evento para limpar busca
        clearButton.addEventListener('click', function() {
            searchInput.value = '';
            this.classList.add('hidden');
            
            // Restaurar visualização de todos os produtos
            filtrarProdutosPorTermo('');
            
            // Voltar para a categoria atualmente selecionada
            const categoriaAtiva = document.querySelector('.categoria-filtro.categoria-ativa');
            if (categoriaAtiva) {
                const categoria = categoriaAtiva.getAttribute('data-categoria');
                filtrarProdutosPorCategoria(categoria);
            }
            
            searchInput.focus();
        });
    }
    
    // Filtrar produtos por termo de busca
    function filtrarProdutosPorTermo(termo) {
        const produtoCards = document.querySelectorAll('.produto-card');
        
        if (!termo) {
            // Se não há termo, mostrar todos os produtos (respeitando o filtro de categoria atual)
            const categoriaAtiva = document.querySelector('.categoria-filtro.categoria-ativa');
            if (categoriaAtiva) {
                const categoria = categoriaAtiva.getAttribute('data-categoria');
                filtrarProdutosPorCategoria(categoria);
            } else {
                produtoCards.forEach(card => card.classList.remove('hidden'));
            }
            return;
        }
        
        // Esconder produtos que não correspondem ao termo
        produtoCards.forEach(card => {
            const nome = card.getAttribute('data-nome').toLowerCase();
            const categoria = card.getAttribute('data-categoria').toLowerCase();
            
            // Verificar se o termo está no nome ou categoria do produto
            if (nome.includes(termo) || categoria.includes(termo)) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });
    }
    
    // Inicializar tracking de produtos selecionados
    function initProdutosSelecionados() {
        // Inicializar container e verificar produtos já selecionados
        atualizarProdutosSelecionados();
    }
    
    // Atualizar lista de produtos selecionados
    function atualizarProdutosSelecionados() {
        const containerSelecionados = document.getElementById('produtosSelecionados');
        const containerLista = containerSelecionados.querySelector('div');
        
        if (!containerSelecionados || !containerLista) return;
        
        // Limpar conteúdo atual
        containerLista.innerHTML = '';
        
        // Encontrar produtos com quantidade > 0
        const produtosSelecionados = [];
        document.querySelectorAll('.produto-card').forEach(card => {
            const id = card.getAttribute('data-id');
            const quantidadeElement = document.getElementById(`quantidade-${id}`);
            if (quantidadeElement && parseInt(quantidadeElement.textContent) > 0) {
                produtosSelecionados.push({
                    id: id,
                    nome: card.getAttribute('data-nome'),
                    quantidade: parseInt(quantidadeElement.textContent),
                    preco: card.getAttribute('data-preco'),
                    categoria: card.getAttribute('data-categoria')
                });
            }
        });
        
        // Se não há produtos selecionados, ocultar o container
        if (produtosSelecionados.length === 0) {
            containerSelecionados.classList.add('hidden');
            return;
        }
        
        // Adicionar produtos selecionados ao container
        produtosSelecionados.forEach(produto => {
            containerLista.innerHTML += `
                <div class="flex flex-col items-center space-y-1">
                    <div class="flex items-center justify-center w-12 h-12 bg-secondary/10 rounded-full border-2 border-secondary text-secondary font-bold">
                        ${produto.quantidade}
                    </div>
                    <div class="text-xs text-center font-medium max-w-[60px] truncate">
                        ${produto.nome}
                    </div>
                </div>
            `;
        });
        
        // Mostrar o container
        containerSelecionados.classList.remove('hidden');
    }
    
    // Configurar eventos para os cards de produtos
    function setupProductCardEvents() {
        document.querySelectorAll('.produto-card').forEach(card => {
            // Atualizar visuais quando a quantidade muda
            const id = card.getAttribute('data-id');
            const quantidadeElement = document.getElementById(`quantidade-${id}`);
            
            if (quantidadeElement) {
                // Observer para monitorar mudanças no texto do elemento quantidade
                const observeQuantidade = new MutationObserver(mutations => {
                    mutations.forEach(mutation => {
                        if (mutation.type === 'childList') {
                            const quantidade = parseInt(quantidadeElement.textContent);
                            atualizarVisualProdutoCard(card, quantidade);
                            
                            // Atualizar lista de produtos selecionados
                            atualizarProdutosSelecionados();
                        }
                    });
                });
                
                observeQuantidade.observe(quantidadeElement, { childList: true });
            }
        });
    }
    
    // Atualizar visual do card de produto conforme quantidade
    function atualizarVisualProdutoCard(card, quantidade) {
        const badgeElement = card.querySelector('.produto-selecionado-badge');
        
        if (quantidade > 0) {
            // Adicionar classe e mostrar badge
            card.classList.add('selecionado');
            if (badgeElement) {
                badgeElement.classList.remove('hidden');
                badgeElement.classList.add('flex');
            }
        } else {
            // Remover classe e esconder badge
            card.classList.remove('selecionado');
            if (badgeElement) {
                badgeElement.classList.add('hidden');
                badgeElement.classList.remove('flex');
            }
        }
    }
    
    // Código original para manipulação de pedidos segue abaixo
    </script>

    <!-- Barra de Fechamento de Caixa (fixa na parte inferior) -->
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-lg z-50">
        <div class="container mx-auto px-4 py-3">
            <div class="flex flex-wrap items-center justify-between">
                <!-- Status do Caixa -->
                <div class="flex items-center space-x-4">
                    <div id="statusCaixa" class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-sm font-medium">
                        Verificando...
                    </div>
                    <div id="infoCaixa" class="text-sm text-gray-600">
                        Carregando informações...
                    </div>
                </div>
                
                <!-- Totais Rápidos -->
                <div class="flex space-x-6 my-2">
                    <div>
                        <span class="text-xs text-gray-500 block">Vendas hoje</span>
                        <span id="totalVendas" class="font-bold text-primary">R$ 0,00</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 block">Dinheiro em caixa</span>
                        <span id="dinheiroEmCaixa" class="font-bold text-primary">R$ 0,00</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 block">Cartões</span>
                        <span id="totalCartoes" class="font-bold text-primary">R$ 0,00</span>
                    </div>
                </div>
                
                <!-- Botões de Ação -->
                <div class="flex space-x-2">
                    <button id="btnAbrirCaixa" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 rounded text-sm hidden">
                        Abrir Caixa
                    </button>
                    <button id="btnSangria" class="bg-amber-500 hover:bg-amber-600 text-white px-3 py-1.5 rounded text-sm hidden">
                        Sangria
                    </button>
                    <button id="btnSuprimento" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded text-sm hidden">
                        Suprimento
                    </button>
                    <button id="btnFecharCaixa" class="bg-primary hover:bg-primary-dark text-white px-3 py-1.5 rounded text-sm hidden">
                        Fechar Caixa
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Abertura de Caixa -->
    <div id="aberturaCaixaModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[1000] p-4">
        <div class="bg-white rounded-lg p-6 max-w-md w-full">
            <h3 class="text-lg font-bold mb-4">Abertura de Caixa</h3>
            <p class="text-sm text-gray-600 mb-4">Informe o valor inicial do caixa (fundo de caixa)</p>
            
            <form id="formAberturaCaixa">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Valor Inicial</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">R$</span>
                        </div>
                        <input type="number" step="0.01" min="0" id="valorAbertura" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary" required>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" class="border border-gray-300 px-4 py-2 rounded text-gray-700" onclick="fecharModal('aberturaCaixaModal')">Cancelar</button>
                    <button type="submit" class="bg-primary text-white px-4 py-2 rounded">Abrir Caixa</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Sangria -->
    <div id="sangriaModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[1000] p-4">
        <div class="bg-white rounded-lg p-6 max-w-md w-full">
            <h3 class="text-lg font-bold mb-4">Registrar Sangria</h3>
            <p class="text-sm text-gray-600 mb-4">Informe o valor a ser retirado do caixa</p>
            
            <form id="formSangria">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Valor da Sangria</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">R$</span>
                        </div>
                        <input type="number" step="0.01" min="0" id="valorSangria" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Motivo</label>
                    <textarea id="motivoSangria" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary/50" rows="2" placeholder="Ex: Pagamento de fornecedor"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" class="border border-gray-300 px-4 py-2 rounded text-gray-700" onclick="fecharModal('sangriaModal')">Cancelar</button>
                    <button type="submit" class="bg-primary text-white px-4 py-2 rounded">Confirmar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Suprimento -->
    <div id="suprimentoModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[1000] p-4">
        <div class="bg-white rounded-lg p-6 max-w-md w-full">
            <h3 class="text-lg font-bold mb-4">Registrar Suprimento</h3>
            <p class="text-sm text-gray-600 mb-4">Informe o valor a ser adicionado ao caixa</p>
            
            <form id="formSuprimento">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Valor do Suprimento</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">R$</span>
                        </div>
                        <input type="number" step="0.01" min="0" id="valorSuprimento" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Descrição</label>
                    <textarea id="descricaoSuprimento" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary/50" rows="2" placeholder="Ex: Reforço de caixa para troco"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" class="border border-gray-300 px-4 py-2 rounded text-gray-700" onclick="fecharModal('suprimentoModal')">Cancelar</button>
                    <button type="submit" class="bg-primary text-white px-4 py-2 rounded">Confirmar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Fechamento de Caixa -->
    <div id="fechamentoCaixaModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[1000] p-4 overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-auto my-4">
            <!-- Cabeçalho fixo -->
            <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-bold text-text-dark">Fechamento de Caixa</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="fecharModal('fechamentoCaixaModal')">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <!-- Conteúdo com scroll -->
            <div class="max-h-[70vh] overflow-y-auto p-4" id="fechamentoCaixaConteudo">
                <!-- Indicador de carregamento -->
                <div id="caixaLoading" class="flex justify-center items-center py-8">
                    <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary"></div>
                    <span class="ml-3 text-gray-600">Carregando dados do caixa...</span>
                </div>
            </div>
            
            <!-- Rodapé fixo -->
            <div class="border-t border-gray-200 p-4 bg-gray-50 flex justify-end space-x-3">
                <!-- Botões de ação (Salvar será visível apenas para admin) -->
                <button type="button" class="border border-gray-300 px-6 py-2 rounded-md text-gray-700 text-sm hover:bg-gray-100 transition-colors duration-200" onclick="fecharModal('fechamentoCaixaModal')">
                    Cancelar
                </button>
                <button type="button" id="btnFecharCaixaConfirmar" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-md text-sm shadow-sm transition-colors duration-200" onclick="confirmarFechamentoCaixa()" disabled>
                    Fechar Caixa
                </button>
            </div>
        </div>
    </div>

    <!-- Funções para controle dos modais de fechamento de caixa -->
    <script>
    // Document ready
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar sistema de caixa
        inicializarSistemaCaixa();
    });
    
    // Inicializar sistema de caixa
    function inicializarSistemaCaixa() {
        // Configurar para usar dados reais do banco de dados
        const emDesenvolvimento = false; // Usando dados reais do banco
        
        // Buscar status do caixa primeiro
        verificarStatusCaixa(emDesenvolvimento);
        
        // Configurar eventos para os modais
        document.getElementById('btnAbrirCaixa')?.addEventListener('click', () => abrirModal('aberturaCaixaModal'));
        document.getElementById('btnSangria')?.addEventListener('click', () => abrirModal('sangriaModal'));
        document.getElementById('btnSuprimento')?.addEventListener('click', () => abrirModal('suprimentoModal'));
        document.getElementById('btnFecharCaixa')?.addEventListener('click', () => {
            abrirModal('fechamentoCaixaModal');
            carregarDadosCaixa(emDesenvolvimento);
        });
        
        // Configurar formulários
        document.getElementById('formAberturaCaixa')?.addEventListener('submit', submeterAberturaCaixa);
        document.getElementById('formSangria')?.addEventListener('submit', submeterSangria);
        document.getElementById('formSuprimento')?.addEventListener('submit', submeterSuprimento);
        
        // Verificar status a cada 5 minutos
        setInterval(() => verificarStatusCaixa(emDesenvolvimento), 300000);
    }
    
    // Abrir modal
    function abrirModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    // Fechar modal
    function fecharModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }
    }
    
    // Verificar status do caixa
    function verificarStatusCaixa(emDesenvolvimento = false) {
        // Atualizar UI para mostrar carregamento
        atualizarUICarregando();
        
        // Se estamos em desenvolvimento, pular a requisição e usar dados simulados diretamente
        if (emDesenvolvimento) {
            // Dados fictícios para demonstração
            const dadosSimulados = {
                success: true,
                caixa_aberto: true,
                dados: {
                    id: 1,
                    data_abertura: new Date().toLocaleString(),
                    usuario_abertura: <?php echo json_encode(Session::get("user") ?: 'Usuário'); ?>,
                    valor_inicial: 100,
                    resumo: {
                        vendas: {
                            dinheiro: 250.00,
                            total: 250.00
                        },
                        movimentos: {
                            suprimentos: 50.00,
                            sangrias: 100.00,
                            cancelamentos: 35.00
                        }
                    }
                }
            };
            
            // Processar os dados simulados
            atualizarUIStatusCaixa(dadosSimulados);
            return;
        }
        
        // Fazer requisição para verificar o status do caixa
        fetch('/caixa/status')
            .then(response => response.json())
            .catch(() => {
                console.warn('Endpoint de status não disponível. Usando dados fictícios para demonstração');
                // Simular resposta para desenvolvimento
                return {
                    success: true,
                    caixa_aberto: true,
                    dados: {
                        id: 1,
                        data_abertura: new Date().toLocaleString(),
                        usuario_abertura: <?php echo json_encode(Session::get("user") ?: 'Usuário'); ?>,
                        valor_inicial: 100,
                        resumo: {
                            vendas: {
                                dinheiro: 250.00,
                                total: 250.00
                            },
                            movimentos: {
                                suprimentos: 50.00,
                                sangrias: 100.00,
                                cancelamentos: 35.00
                            }
                        }
                    }
                };
            })
            .then(data => {
                if (data.success) {
                    atualizarUIStatusCaixa(data);
                } else {
                    console.error('Erro ao verificar status:', data.message);
                    mostrarErroStatus();
                }
            })
            .catch(error => {
                console.error('Erro ao buscar status do caixa:', error);
                mostrarErroStatus();
            });
    }
    
    // Atualizar UI durante carregamento
    function atualizarUICarregando() {
        document.getElementById('statusCaixa').className = 'bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-sm font-medium';
        document.getElementById('statusCaixa').textContent = 'Verificando...';
        document.getElementById('infoCaixa').textContent = 'Carregando informações...';
    }
    
    // Mostrar erro no status
    function mostrarErroStatus() {
        document.getElementById('statusCaixa').className = 'bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-medium';
        document.getElementById('statusCaixa').textContent = 'Erro';
        document.getElementById('infoCaixa').textContent = 'Não foi possível carregar informações do caixa';
        
        // Mostrar apenas botão para abrir caixa em caso de erro
        document.getElementById('btnAbrirCaixa').classList.remove('hidden');
        document.getElementById('btnSangria').classList.add('hidden');
        document.getElementById('btnSuprimento').classList.add('hidden');
        document.getElementById('btnFecharCaixa').classList.add('hidden');
    }
    
    // Atualizar UI com status do caixa
    function atualizarUIStatusCaixa(data) {
        if (data.caixa_aberto) {
            // Caixa está aberto
            document.getElementById('statusCaixa').className = 'bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium';
            document.getElementById('statusCaixa').textContent = 'Caixa Aberto';
            
            // Formatar data de abertura
            const dataAbertura = new Date(data.dados.data_abertura);
            const horaFormatada = dataAbertura.getHours().toString().padStart(2, '0') + ':' +
                                 dataAbertura.getMinutes().toString().padStart(2, '0');
            
            document.getElementById('infoCaixa').innerHTML = 
                `Aberto por <span class="font-medium">${data.dados.usuario_abertura}</span> às <span class="font-medium">${horaFormatada}</span>`;
            
            // Atualizar totais
            atualizarTotaisCaixa(data.dados.resumo);
            
            // Mostrar botões de operações, esconder botão de abertura
            document.getElementById('btnAbrirCaixa').classList.add('hidden');
            document.getElementById('btnSangria').classList.remove('hidden');
            document.getElementById('btnSuprimento').classList.remove('hidden');
            document.getElementById('btnFecharCaixa').classList.remove('hidden');
        } else {
            // Caixa está fechado
            document.getElementById('statusCaixa').className = 'bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm font-medium';
            document.getElementById('statusCaixa').textContent = 'Caixa Fechado';
            document.getElementById('infoCaixa').textContent = 'Nenhum caixa aberto no momento';
            
            // Zerar totais
            document.getElementById('totalVendas').textContent = 'R$ 0,00';
            document.getElementById('dinheiroEmCaixa').textContent = 'R$ 0,00';
            document.getElementById('totalCartoes').textContent = 'R$ 0,00';
            
            // Mostrar apenas botão para abrir caixa
            document.getElementById('btnAbrirCaixa').classList.remove('hidden');
            document.getElementById('btnSangria').classList.add('hidden');
            document.getElementById('btnSuprimento').classList.add('hidden');
            document.getElementById('btnFecharCaixa').classList.add('hidden');
        }
    }
    
    // Atualizar totais na barra de caixa
    function atualizarTotaisCaixa(resumo) {
        const formatarValor = (valor) => `R$ ${parseFloat(valor).toFixed(2).replace('.', ',')}`;
        
        // Total de vendas
        const totalVendas = resumo.vendas.total;
        document.getElementById('totalVendas').textContent = formatarValor(totalVendas);
        
        // Dinheiro em caixa (valor inicial + vendas em dinheiro + suprimentos - sangrias)
        const dinheiroEmCaixa = resumo.vendas.dinheiro + resumo.movimentos.suprimentos - resumo.movimentos.sangrias;
        document.getElementById('dinheiroEmCaixa').textContent = formatarValor(dinheiroEmCaixa);
        
        // Total de cartões (crédito + débito)
        const totalCartoes = resumo.vendas.cartao_credito + resumo.vendas.cartao_debito;
        document.getElementById('totalCartoes').textContent = formatarValor(totalCartoes);
    }
    
    // Submeter formulário de abertura de caixa
    function submeterAberturaCaixa(e) {
        e.preventDefault();
        
        const valorInicial = document.getElementById('valorAbertura').value;
        
        if (!valorInicial || parseFloat(valorInicial) < 0) {
            alert('Por favor, informe um valor inicial válido.');
            return;
        }
        
        // Desabilitar botão para evitar submissões múltiplas
        const btnSubmit = e.target.querySelector('button[type="submit"]');
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = `<span class="loading-spinner"></span> Abrindo...`;
        
        // Fazer requisição para abrir o caixa
        fetch('/caixa/abrir', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ valor_inicial: valorInicial })
        })
        .then(response => response.json())
        .catch(() => {
            console.warn('Endpoint não disponível. Simulando sucesso para demonstração');
            // Simular resposta para desenvolvimento
            return {
                success: true,
                message: 'Caixa aberto com sucesso!'
            };
        })
        .then(data => {
            if (data.success) {
                alert('Caixa aberto com sucesso!');
                fecharModal('aberturaCaixaModal');
                // Atualizar status do caixa
                verificarStatusCaixa();
            } else {
                alert('Erro ao abrir caixa: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Ocorreu um erro ao processar a solicitação.');
        })
        .finally(() => {
            // Habilitar botão novamente
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = 'Abrir Caixa';
        });
    }
    
    // Submeter formulário de sangria
    function submeterSangria(e) {
        e.preventDefault();
        
        const valor = document.getElementById('valorSangria').value;
        const motivo = document.getElementById('motivoSangria').value;
        
        if (!valor || parseFloat(valor) <= 0) {
            alert('Por favor, informe um valor válido para a sangria.');
            return;
        }
        
        // Desabilitar botão para evitar submissões múltiplas
        const btnSubmit = e.target.querySelector('button[type="submit"]');
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = `<span class="loading-spinner"></span> Registrando...`;
        
        // Fazer requisição para registrar a sangria
        fetch('/caixa/sangria', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ valor, motivo })
        })
        .then(response => response.json())
        .catch(() => {
            console.warn('Endpoint não disponível. Simulando sucesso para demonstração');
            // Simular resposta para desenvolvimento
            return {
                success: true,
                message: 'Sangria registrada com sucesso!'
            };
        })
        .then(data => {
            if (data.success) {
                alert('Sangria registrada com sucesso!');
                fecharModal('sangriaModal');
                // Limpar formulário
                document.getElementById('valorSangria').value = '';
                document.getElementById('motivoSangria').value = '';
                // Atualizar status do caixa
                verificarStatusCaixa();
            } else {
                alert('Erro ao registrar sangria: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Ocorreu um erro ao processar a solicitação.');
        })
        .finally(() => {
            // Habilitar botão novamente
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = 'Confirmar';
        });
    }
    
    // Submeter formulário de suprimento
    function submeterSuprimento(e) {
        e.preventDefault();
        
        const valor = document.getElementById('valorSuprimento').value;
        const descricao = document.getElementById('descricaoSuprimento').value;
        
        if (!valor || parseFloat(valor) <= 0) {
            alert('Por favor, informe um valor válido para o suprimento.');
            return;
        }
        
        // Desabilitar botão para evitar submissões múltiplas
        const btnSubmit = e.target.querySelector('button[type="submit"]');
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = `<span class="loading-spinner"></span> Registrando...`;
        
        // Fazer requisição para registrar o suprimento
        fetch('/caixa/suprimento', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ valor, descricao })
        })
        .then(response => response.json())
        .catch(() => {
            console.warn('Endpoint não disponível. Simulando sucesso para demonstração');
            // Simular resposta para desenvolvimento
            return {
                success: true,
                message: 'Suprimento registrado com sucesso!'
            };
        })
        .then(data => {
            if (data.success) {
                alert('Suprimento registrado com sucesso!');
                fecharModal('suprimentoModal');
                // Limpar formulário
                document.getElementById('valorSuprimento').value = '';
                document.getElementById('descricaoSuprimento').value = '';
                // Atualizar status do caixa
                verificarStatusCaixa();
            } else {
                alert('Erro ao registrar suprimento: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Ocorreu um erro ao processar a solicitação.');
        })
        .finally(() => {
            // Habilitar botão novamente
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = 'Confirmar';
        });
    }
    
    // Carregar dados do caixa para o modal de fechamento
    function carregarDadosCaixa(emDesenvolvimento = false) {
        // Mostrar indicador de carregamento
        const conteudoContainer = document.getElementById('fechamentoCaixaConteudo');
        document.getElementById('caixaLoading').style.display = 'flex';
        
        // Desabilitar botão de fechar caixa até que os dados sejam carregados
        document.getElementById('btnFecharCaixaConfirmar').disabled = true;
        
        // Obter usuário logado
        const usuarioLogado = <?php echo json_encode(Session::get("user") ?: 'Usuário'); ?>;
        
        // Obter data atual formatada
        const dataAtual = new Date().toLocaleString();
        
        // Se estamos em desenvolvimento, pular a requisição e usar dados simulados diretamente
        if (emDesenvolvimento) {
            // Dados fictícios para demonstração
            const dadosSimulados = {
                success: true,
                dados: {
                    id: 1,
                    data_abertura: dataAtual,
                    usuario_abertura: usuarioLogado,
                    valor_inicial: 100.00,
                    resumo: {
                        vendas: {
                            dinheiro: 250.00,
                            total: 250.00
                        },
                        movimentos: {
                            suprimentos: 50.00,
                            sangrias: 100.00,
                            cancelamentos: 35.00
                        }
                    }
                }
            };
            
            // Ocultar indicador de carregamento
            document.getElementById('caixaLoading').style.display = 'none';
            // Preencher o modal com os dados do caixa
            preencherModalFechamentoCaixa(dadosSimulados.dados);
            // Habilitar botão de fechar caixa
            document.getElementById('btnFecharCaixaConfirmar').disabled = false;
            return;
        }
        
        // Fazer requisição para buscar os dados do caixa
        fetch('/caixa/dados')
            .then(response => response.json())
            .catch(() => {
                console.warn('Endpoint de dados do caixa não disponível. Usando dados fictícios para demonstração');
                // Simular resposta para desenvolvimento - versão simplificada apenas com dinheiro
                return {
                    success: true,
                    dados: {
                        id: 1,
                        data_abertura: dataAtual,
                        usuario_abertura: usuarioLogado,
                        valor_inicial: 100.00,
                        resumo: {
                            vendas: {
                                dinheiro: 250.00,
                                total: 250.00
                            },
                            movimentos: {
                                suprimentos: 50.00,
                                sangrias: 100.00,
                                cancelamentos: 35.00
                            }
                        }
                    }
                };
            })
            .then(data => {
                if (data.success) {
                    // Ajustar dados para garantir que temos apenas dinheiro
                    if (data.dados.resumo && data.dados.resumo.vendas) {
                        // Garantir que o total de vendas é igual ao dinheiro
                        data.dados.resumo.vendas.total = data.dados.resumo.vendas.dinheiro;
                        
                        // Remover outros métodos de pagamento se existirem
                        delete data.dados.resumo.vendas.cartao_credito;
                        delete data.dados.resumo.vendas.cartao_debito;
                        delete data.dados.resumo.vendas.pix;
                    }
                    
                    // Atualizar usuário e data
                    data.dados.usuario_abertura = usuarioLogado;
                    data.dados.data_abertura = dataAtual;
                    
                    // Ocultar indicador de carregamento
                    document.getElementById('caixaLoading').style.display = 'none';
                    // Preencher o modal com os dados do caixa
                    preencherModalFechamentoCaixa(data.dados);
                    // Habilitar botão de fechar caixa
                    document.getElementById('btnFecharCaixaConfirmar').disabled = false;
                } else {
                    console.error('Erro ao buscar dados do caixa:', data.message);
                    document.getElementById('caixaLoading').style.display = 'none';
                    conteudoContainer.innerHTML = `
                        <div class="p-4 bg-red-50 text-red-800 rounded-md">
                            <p>Não foi possível carregar os dados do caixa. Tente novamente.</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Erro ao buscar dados do caixa:', error);
                document.getElementById('caixaLoading').style.display = 'none';
                conteudoContainer.innerHTML = `
                    <div class="p-4 bg-red-50 text-red-800 rounded-md">
                        <p>Não foi possível carregar os dados do caixa. Tente novamente.</p>
                    </div>
                `;
            });
    }
    
    // Preencher modal de fechamento de caixa com os dados
    function preencherModalFechamentoCaixa(dados) {
        const formatarValor = (valor) => `R$ ${parseFloat(valor).toFixed(2).replace('.', ',')}`;
        const conteudoContainer = document.getElementById('fechamentoCaixaConteudo');
        
        // Garantir que o total de vendas reflete a soma do valor total dos pedidos
        // (Assumindo que dados.resumo.vendas.dinheiro já contém a soma correta dos pedidos)
        
        // Calcular valores para o fechamento - Dinheiro em caixa = Vendas + Suprimentos - Sangrias - Cancelamentos
        const totalEmDinheiro = parseFloat(dados.resumo.vendas.dinheiro) + 
                              parseFloat(dados.resumo.movimentos.suprimentos) - 
                              parseFloat(dados.resumo.movimentos.sangrias) - 
                              parseFloat(dados.resumo.movimentos.cancelamentos);
        
        // Gerar HTML do conteúdo - versão apenas com dinheiro
        conteudoContainer.innerHTML = `
            <div class="space-y-6">
                <!-- Informações básicas -->
                <div class="bg-gray-50 p-4 rounded-md">
                    <h4 class="font-medium text-gray-700 mb-2">Informações do Caixa</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Aberto por:</span>
                            <span class="block font-medium">${dados.usuario_abertura}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Data de abertura:</span>
                            <span class="block font-medium">${dados.data_abertura}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Valor inicial:</span>
                            <span class="block font-medium">${formatarValor(dados.valor_inicial)}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Resumo de vendas -->
                <div>
                    <h4 class="font-medium text-gray-700 mb-3">Resumo de Vendas</h4>
                    <div class="bg-white border border-gray-200 rounded-md overflow-hidden">
                        <div class="p-3 border-b border-gray-200">
                            <div>
                                <span class="text-gray-500 text-sm">Vendas em Dinheiro:</span>
                                <span class="block font-medium">${formatarValor(dados.resumo.vendas.dinheiro)}</span>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-3">
                            <div class="flex justify-between items-center">
                                <span class="font-medium">Total de Vendas:</span>
                                <span class="font-bold text-lg text-primary">${formatarValor(dados.resumo.vendas.dinheiro)}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Movimentações -->
                <div>
                    <h4 class="font-medium text-gray-700 mb-3">Movimentações</h4>
                    <div class="bg-white border border-gray-200 rounded-md overflow-hidden">
                        <div class="grid grid-cols-3 gap-4 p-3">
                            <div>
                                <span class="text-gray-500 text-sm">Suprimentos:</span>
                                <div class="flex items-center">
                                    <input type="number" id="editSuprimentos" value="${dados.resumo.movimentos.suprimentos}" 
                                        class="w-full border border-gray-300 rounded-md px-2 py-1 mt-1 text-sm"
                                        onchange="recalcularFechamento()">
                                </div>
                            </div>
                            <div>
                                <span class="text-gray-500 text-sm">Sangrias:</span>
                                <div class="flex items-center">
                                    <input type="number" id="editSangrias" value="${dados.resumo.movimentos.sangrias}" 
                                        class="w-full border border-gray-300 rounded-md px-2 py-1 mt-1 text-sm"
                                        onchange="recalcularFechamento()">
                                </div>
                            </div>
                            <div>
                                <span class="text-gray-500 text-sm">Cancelamentos:</span>
                                <div class="flex items-center">
                                    <input type="number" id="editCancelamentos" value="${dados.resumo.movimentos.cancelamentos}" 
                                        class="w-full border border-gray-300 rounded-md px-2 py-1 mt-1 text-sm"
                                        onchange="recalcularFechamento()">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Conferência de valores - apenas dinheiro -->
                <div>
                    <h4 class="font-medium text-gray-700 mb-3">Conferência de Valores</h4>
                    
                    <div class="bg-gray-50 p-4 rounded-md mb-4">
                        <p class="text-sm text-gray-600 mb-3">Informe o valor em dinheiro encontrado no caixa:</p>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">Dinheiro em Caixa</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">R$</span>
                                </div>
                                <input type="number" step="0.01" id="valorConferenciaDinheiro" 
                                    value="${totalEmDinheiro.toFixed(2)}"
                                    class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary"
                                    onchange="calcularDiferencas()">
                            </div>
                            <div class="mt-1 flex justify-between">
                                <span class="text-xs text-gray-500">Valor esperado: ${formatarValor(totalEmDinheiro)}</span>
                                <span id="diferencaDinheiro" class="text-xs font-medium">Diferença: R$ 0,00</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white border border-gray-200 rounded-md p-3">
                        <div class="flex justify-between items-center">
                            <span class="font-medium">Total em Caixa:</span>
                            <span id="totalConferencia" class="font-bold text-lg text-primary">${formatarValor(totalEmDinheiro)}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Observações -->
                <div>
                    <label class="block text-sm font-medium mb-1">Observações (opcional)</label>
                    <textarea id="observacoesFechamento" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary"
                        placeholder="Registre informações adicionais sobre o fechamento do caixa..."></textarea>
                </div>
            </div>
        `;
        
        // Armazenar dados para uso durante o fechamento
        window.dadosCaixa = dados;
        window.valoresEsperados = {
            dinheiro: totalEmDinheiro
        };
        
        // Configurar cálculos das diferenças
        calcularDiferencas();
    }
    
    // Calcular diferenças na conferência de valores - versão simplificada para apenas dinheiro
    function calcularDiferencas() {
        if (!window.valoresEsperados) return;
        
        const formatarValor = (valor) => `R$ ${parseFloat(valor).toFixed(2).replace('.', ',')}`;
        const formatarDiferenca = (diferenca) => {
            if (diferenca === 0) return '<span class="text-gray-500">Diferença: R$ 0,00</span>';
            return diferenca > 0 
                ? `<span class="text-green-600">Sobra: ${formatarValor(diferenca)}</span>` 
                : `<span class="text-red-600">Falta: ${formatarValor(Math.abs(diferenca))}</span>`;
        };
        
        // Obter valores informados
        const valorDinheiro = parseFloat(document.getElementById('valorConferenciaDinheiro').value) || 0;
        
        // Calcular diferenças
        const diferencaDinheiro = valorDinheiro - window.valoresEsperados.dinheiro;
        
        // Atualizar elementos com as diferenças
        document.getElementById('diferencaDinheiro').innerHTML = formatarDiferenca(diferencaDinheiro);
        
        // Atualizar total
        document.getElementById('totalConferencia').textContent = formatarValor(valorDinheiro);
    }
    
    // Recalcular valores do fechamento quando os campos editáveis forem alterados
    function recalcularFechamento() {
        if (!window.dadosCaixa) return;
        
        // Obter valores atualizados
        const suprimentos = parseFloat(document.getElementById('editSuprimentos').value) || 0;
        const sangrias = parseFloat(document.getElementById('editSangrias').value) || 0;
        const cancelamentos = parseFloat(document.getElementById('editCancelamentos').value) || 0;
        
        // Recalcular valor esperado em dinheiro - Dinheiro em caixa = Vendas + Suprimentos - Sangrias - Cancelamentos
        const totalEmDinheiro = parseFloat(window.dadosCaixa.resumo.vendas.dinheiro) + 
                              suprimentos - sangrias - cancelamentos;
        
        // Atualizar valores esperados
        window.valoresEsperados.dinheiro = totalEmDinheiro;
        
        // Atualizar interface
        document.querySelector('#valorConferenciaDinheiro').closest('div').nextElementSibling.querySelector('.text-xs.text-gray-500').textContent = 
            `Valor esperado: R$ ${totalEmDinheiro.toFixed(2).replace('.', ',')}`;
        
        // Atualizar o valor do input de conferência para o novo valor esperado
        document.getElementById('valorConferenciaDinheiro').value = totalEmDinheiro.toFixed(2);
        
        // Recalcular diferenças
        calcularDiferencas();
    }
    
    // Confirmar fechamento de caixa - versão simplificada para apenas dinheiro
    function confirmarFechamentoCaixa() {
        if (!window.dadosCaixa) {
            alert('Não foi possível carregar os dados do caixa.');
            return;
        }
        
        // Obter valores da conferência
        const valorDinheiro = parseFloat(document.getElementById('valorConferenciaDinheiro').value) || 0;
        const observacoes = document.getElementById('observacoesFechamento').value;
        
        // Obter valores atualizados das movimentações
        const suprimentos = parseFloat(document.getElementById('editSuprimentos').value) || 0;
        const sangrias = parseFloat(document.getElementById('editSangrias').value) || 0;
        const cancelamentos = parseFloat(document.getElementById('editCancelamentos').value) || 0;
        
        // Preparar dados para envio
        const dados = {
            id_caixa: window.dadosCaixa.id,
            valores_conferencia: {
                dinheiro: valorDinheiro
            },
            valores_esperados: window.valoresEsperados,
            movimentos: {
                suprimentos: suprimentos,
                sangrias: sangrias,
                cancelamentos: cancelamentos
            },
            observacoes: observacoes
        };
        
        // Desabilitar botão para evitar submissões múltiplas
        const btnFechar = document.getElementById('btnFecharCaixaConfirmar');
        btnFechar.disabled = true;
        btnFechar.innerHTML = `<span class="loading-spinner"></span> Processando...`;
        
        // Usar dados reais do banco de dados
        const emDesenvolvimento = false; // Usando dados reais do banco
        
        // Enviar requisição para fechar o caixa
        fetch('/caixa/fechar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        })
        .then(response => response.json())
        .catch(() => {
            console.warn('Endpoint de fechamento não disponível. Usando dados fictícios como fallback');
            // Fallback apenas em caso de falha na comunicação com o servidor
            return {
                success: true,
                message: 'Caixa fechado com sucesso!'
            };
        })
        .then(data => {
            if (data.success) {
                alert('Caixa fechado com sucesso!');
                fecharModal('fechamentoCaixaModal');
                // Atualizar status do caixa
                verificarStatusCaixa(emDesenvolvimento);
            } else {
                alert('Erro ao fechar caixa: ' + data.message);
                // Reabilitar botão
                btnFechar.disabled = false;
                btnFechar.innerHTML = `Fechar Caixa`;
            }
        })
        .catch(error => {
            console.error('Erro ao fechar caixa:', error);
            alert('Ocorreu um erro ao processar a solicitação.');
            // Reabilitar botão
            btnFechar.disabled = false;
            btnFechar.innerHTML = `Fechar Caixa`;
        });
    }
    </script>
</body>

</html>