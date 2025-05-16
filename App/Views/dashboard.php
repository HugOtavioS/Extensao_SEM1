<?php
use Models\Session\Session;
use Config\Database\Database;
use Config\env;

Session::init();

// Verificar se o usuário é administrador
if (!Session::get("user") || strpos(Session::get("user"), "admin") === false) {
    header('Location: /');
    exit;
}

// Conectar diretamente ao banco de dados
$db = new Database(new env());

// Extrair variáveis passadas pelo controller (mantemos como fallback)
$metricas = $data['metricas'] ?? [];
$resumo = $metricas['resumo'] ?? [];

// Definir preços médios para o mapa de coleta
$mediaPrecos = [
    'aluminio' => '18,75',
    'papel' => '2,35',
    'plastico' => '3,75',
    'vidro' => '1,25'
];
// Caso venha do controller, substituir
if (isset($data['mediaPrecos'])) {
    $mediaPrecos = $data['mediaPrecos'];
}

// 1. Pedidos
$filtroStatus = $_GET['status'] ?? 'todos';
$filtroPago = $_GET['pago'] ?? 'todos';
$filtroDataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$filtroDataFim = $_GET['data_fim'] ?? date('Y-m-d');

// Construir condições WHERE para o filtro
$whereConditions = [];

// Filtro por status
if ($filtroStatus !== 'todos') {
    $whereConditions[] = "status = '$filtroStatus'";
}

// Filtro por pagamento
if ($filtroPago !== 'todos') {
    $whereConditions[] = "pago = " . ($filtroPago === 'pago' ? '1' : '0');
}

// Filtro por data
$whereConditions[] = "data_pedido BETWEEN '$filtroDataInicio 00:00:00' AND '$filtroDataFim 23:59:59'";

// Montar a cláusula WHERE
$whereClause = !empty($whereConditions) ? implode(" AND ", $whereConditions) : null;

// Buscar pedidos com filtros aplicados
$pedidos = $db->read("tb_pedidos", ["*"], $whereClause);
$totalPedidos = count($pedidos);

// 2. Produtos
$produtos = $db->read("tb_produtos", ["*"]);

// Criar mapa de produtos para referência rápida
$produtosMap = [];
foreach ($produtos as $produto) {
    $produtosMap[$produto['id']] = $produto;
}

// 3. Vendas (se existirem)
$vendas = [];
try {
    $vendas = $db->read("tb_vendas", ["*"]);
} catch (Exception $e) {
    // Tabela pode não existir, continuamos com os pedidos
}

// Calcular métricas
$valorTotalVendas = 0;
$totalItensVendidos = 0;
$pedidosPagos = 0;
$vendasPorStatus = [
    'preparando' => 0,
    'pronto' => 0,
    'entregue' => 0,
    'cancelado' => 0
];
$vendasPorDia = [];
$produtosVendidos = [];
$mesasUtilizadas = [];
$horariosVendas = [];

// Processar pedidos
foreach ($pedidos as $pedido) {
    // Somar valor total
    $valorTotalVendas += floatval($pedido['valor_total']);
    
    // Contar itens
    $totalItensVendidos += intval($pedido['itens']);
    
    // Contar pedidos pagos
    if ($pedido['pago'] == 1) {
        $pedidosPagos++;
    }
    
    // Contar por status
    $status = strtolower($pedido['status']);
    if (isset($vendasPorStatus[$status])) {
        $vendasPorStatus[$status]++;
    }
    
    // Agrupar por dia para vendas dos últimos 7 dias
    $dataPedido = date('Y-m-d', strtotime($pedido['data_pedido']));

    // Para o gráfico de "Vendas dos Últimos 7 Dias", não aplicamos os filtros de status e pagamento
    // Verificamos apenas se a data está dentro do período dos últimos 7 dias
    $hoje = date('Y-m-d');
    $data7DiasAtras = date('Y-m-d', strtotime('-6 days'));

    if (!isset($vendasPorDia[$dataPedido])) {
        $vendasPorDia[$dataPedido] = 0;
    }
    $vendasPorDia[$dataPedido] += floatval($pedido['valor_total']);
    
    // Agrupar por hora
    $horaPedido = date('H', strtotime($pedido['data_pedido']));
    if (!isset($horariosVendas[$horaPedido])) {
        $horariosVendas[$horaPedido] = 0;
    }
    $horariosVendas[$horaPedido]++;
    
    // Contar uso de mesas
    if (!empty($pedido['mesa'])) {
        if (!isset($mesasUtilizadas[$pedido['mesa']])) {
            $mesasUtilizadas[$pedido['mesa']] = 0;
        }
        $mesasUtilizadas[$pedido['mesa']]++;
    }
    
    // Processar produtos vendidos
    if (!empty($pedido['produtos'])) {
        $produtosPedido = json_decode($pedido['produtos'], true);
        if (is_array($produtosPedido)) {
            foreach ($produtosPedido as $produtoId => $quantidade) {
                if (!isset($produtosVendidos[$produtoId])) {
                    $produtosVendidos[$produtoId] = 0;
                }
                $produtosVendidos[$produtoId] += intval($quantidade);
            }
        }
    }
}

// Calcular médias e percentuais
$mediaPorPedido = $totalPedidos > 0 ? ($valorTotalVendas / $totalPedidos) : 0;
$mediaItensPorPedido = $totalPedidos > 0 ? ($totalItensVendidos / $totalPedidos) : 0;
$percentualPagos = $totalPedidos > 0 ? ($pedidosPagos / $totalPedidos) * 100 : 0;

// Obter vendas dos últimos 7 dias
$ultimosDias = [];
// Se o período de filtro for menor que 7 dias, mostramos todo o período
// Caso contrário, mostramos os últimos 7 dias do período selecionado
$dataInicio = new DateTime($filtroDataInicio);
$dataFim = new DateTime($filtroDataFim);
$diferenca = $dataInicio->diff($dataFim)->days + 1;

// Se o período for maior que 7 dias, ajustamos para mostrar só os últimos 7
if ($diferenca > 7) {
    $dataInicio = clone $dataFim;
    $dataInicio->modify('-6 days');
    $diferenca = 7;
}

// Gerar array com as datas do período
for ($i = 0; $i < $diferenca; $i++) {
    $data = date('Y-m-d', strtotime("+$i days", strtotime($dataInicio->format('Y-m-d'))));
    $label = date('d/m', strtotime($data));
    $valor = isset($vendasPorDia[$data]) ? $vendasPorDia[$data] : 0;
    
    $ultimosDias[] = [
        'data' => $data,
        'label' => $label,
        'valor' => $valor
    ];
}

// Calcular crescimento (comparando hoje com ontem)
$dataFimStr = $dataFim->format('Y-m-d');
$valorHoje = isset($vendasPorDia[$dataFimStr]) ? $vendasPorDia[$dataFimStr] : 0;

$ontemDateTime = clone $dataFim;
$ontemDateTime->modify('-1 day');
$ontem = $ontemDateTime->format('Y-m-d');
$valorOntem = isset($vendasPorDia[$ontem]) ? $vendasPorDia[$ontem] : 0;
$crescimento = 0;

if ($valorOntem > 0) {
    $crescimento = (($valorHoje - $valorOntem) / $valorOntem) * 100;
} elseif ($valorHoje > 0) {
    $crescimento = 100; // Se ontem foi zero e hoje tem valor, crescimento de 100%
}

// Formatar produtos mais vendidos
arsort($produtosVendidos);
$produtosMaisVendidos = [];
$i = 0;
foreach ($produtosVendidos as $produtoId => $quantidade) {
    if ($i >= 10) break; // Limitar a 10 produtos
    
    $produto = $produtosMap[$produtoId] ?? null;
    $nome = $produto ? $produto['nome'] : "Produto #$produtoId";
    $preco = $produto ? floatval($produto['preco']) : 0;
    
    $produtosMaisVendidos[] = [
        'id' => $produtoId,
        'nome' => $nome,
        'quantidade' => $quantidade,
        'valorTotal' => $quantidade * $preco
    ];
    $i++;
}

// Formatar mesas mais utilizadas
arsort($mesasUtilizadas);
$mesasMaisUtilizadas = [];
$i = 0;
foreach ($mesasUtilizadas as $mesa => $quantidade) {
    if ($i >= 5) break; // Limitar a 5 mesas
    
    $mesasMaisUtilizadas[] = [
        'mesa' => $mesa,
        'quantidade' => $quantidade,
        'porcentagem' => $totalPedidos > 0 ? round(($quantidade / $totalPedidos) * 100) : 0
    ];
    $i++;
}

// Formatar horários de venda
$horariosVendasFormatados = [];
for ($hora = 0; $hora < 24; $hora++) {
    $horaFormatada = str_pad($hora, 2, '0', STR_PAD_LEFT) . 'h';
    $horariosVendasFormatados[] = [
        'hora' => $horaFormatada,
        'pedidos' => $horariosVendas[$hora] ?? 0
    ];
}

// Definir variáveis para uso na view
$resumo = [
    'totalPedidos' => $totalPedidos,
    'totalVendas' => number_format($valorTotalVendas, 2, ',', '.'),
    'totalVendasNumerico' => $valorTotalVendas,
    'totalItens' => $totalItensVendidos,
    'mediaPorPedido' => number_format($mediaPorPedido, 2, ',', '.'),
    'mediaItensPorPedido' => round($mediaItensPorPedido, 1),
    'percentualPagos' => round($percentualPagos),
    'crescimentoDiario' => round($crescimento, 1)
];
$vendasPorDia = $ultimosDias;
$horariosVendas = $horariosVendasFormatados;

// Garantir que os arrays estejam no formato esperado para os gráficos
$vendasPorStatus = array_map('intval', $vendasPorStatus);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrativo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <!-- Leaflet CSS e JavaScript -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
          crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
          integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
          crossorigin=""></script>
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
    </style>
</head>

<body class="bg-background min-h-screen flex flex-col">
    <?php require "Components/headerAdm.php"; ?>

    <div class="container mx-auto px-4 py-8 flex-grow">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-text-dark">Dashboard</h1>
            <div>
                <a href="/pedidos" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg shadow-md transition-colors duration-200 inline-flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    Gerenciar Pedidos
                </a>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-8">
            <h2 class="card-header">Filtros</h2>
            <form id="filtroForm" method="get" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="status" class="block text-sm font-medium text-text-dark mb-1">Status</label>
                    <select name="status" id="status" class="filter-select">
                        <option value="todos" <?= $filtroStatus === 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="preparando" <?= $filtroStatus === 'preparando' ? 'selected' : '' ?>>Preparando</option>
                        <option value="pronto" <?= $filtroStatus === 'pronto' ? 'selected' : '' ?>>Pronto</option>
                        <option value="entregue" <?= $filtroStatus === 'entregue' ? 'selected' : '' ?>>Entregue</option>
                        <option value="cancelado" <?= $filtroStatus === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                <div>
                    <label for="pago" class="block text-sm font-medium text-text-dark mb-1">Pagamento</label>
                    <select name="pago" id="pago" class="filter-select">
                        <option value="todos" <?= $filtroPago === 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="pago" <?= $filtroPago === 'pago' ? 'selected' : '' ?>>Pago</option>
                        <option value="nao_pago" <?= $filtroPago === 'nao_pago' ? 'selected' : '' ?>>Não Pago</option>
                    </select>
                </div>
                <div>
                    <label for="data_inicio" class="block text-sm font-medium text-text-dark mb-1">Data Início</label>
                    <input type="date" name="data_inicio" id="data_inicio" value="<?= $filtroDataInicio ?>" class="date-input">
                </div>
                <div>
                    <label for="data_fim" class="block text-sm font-medium text-text-dark mb-1">Data Fim</label>
                    <input type="date" name="data_fim" id="data_fim" value="<?= $filtroDataFim ?>" class="date-input">
                </div>
                <div class="md:col-span-2 lg:col-span-4 flex justify-end">
                    <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg shadow-md transition-colors duration-200 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Aplicar Filtros
                    </button>
                    <a href="/pedidos/dashboard" class="ml-2 border border-gray-300 hover:bg-gray-100 text-text-dark px-4 py-2 rounded-lg transition-colors duration-200 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Limpar
                    </a>
                </div>
            </form>
        </div>

        <?php if (isset($data['error']) && !empty($data['error'])) : ?>
        <div class="bg-danger/10 border border-danger/20 text-danger-dark px-4 py-3 rounded-lg mb-6">
            <div class="flex">
                <div class="py-1 mr-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <p class="font-medium">Erro ao carregar dados do dashboard</p>
                    <p class="text-sm"><?= htmlspecialchars($data['error']) ?></p>
                    <p class="text-sm mt-2">Os dados exibidos podem não refletir os valores reais. Por favor, verifique a conexão com o banco de dados.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($whereConditions)): ?>
        <div class="bg-blue-50 border border-blue-100 text-blue-700 px-4 py-3 rounded-lg mb-6 flex items-start">
            <div class="flex-shrink-0 mr-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
            </div>
            <div>
                <p class="font-medium">Filtros aplicados</p>
                <ul class="text-sm mt-1 list-disc pl-5">
                    <?php if ($filtroStatus !== 'todos'): ?>
                        <li>Status: <span class="font-medium"><?= ucfirst($filtroStatus) ?></span></li>
                    <?php endif; ?>
                    
                    <?php if ($filtroPago !== 'todos'): ?>
                        <li>Pagamento: <span class="font-medium"><?= $filtroPago === 'pago' ? 'Pago' : 'Não pago' ?></span></li>
                    <?php endif; ?>
                    
                    <li>Período: <span class="font-medium"><?= date('d/m/Y', strtotime($filtroDataInicio)) ?> até <?= date('d/m/Y', strtotime($filtroDataFim)) ?></span></li>
                </ul>
                <p class="text-xs mt-2">
                    Exibindo <span class="font-medium"><?= $totalPedidos ?></span> pedidos que correspondem aos critérios.
                    <?php if ($totalPedidos === 0): ?>
                        <a href="/pedidos/dashboard" class="underline">Limpar filtros</a>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Cards de estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total de Pedidos -->
            <div class="card">
                <div class="stat-label">Total de Pedidos</div>
                <div class="stat-value"><?= $resumo['totalPedidos'] ?? 0 ?></div>
                <div class="flex items-center mt-2">
                    <span class="text-xs text-<?= ($resumo['crescimentoDiario'] ?? 0) >= 0 ? 'success' : 'danger' ?>">
                        <?= ($resumo['crescimentoDiario'] ?? 0) >= 0 ? '+' : '' ?><?= $resumo['crescimentoDiario'] ?? 0 ?>% hoje
                    </span>
                </div>
            </div>

            <!-- Total de Vendas -->
            <div class="card">
                <div class="stat-label">Total de Vendas</div>
                <div class="stat-value">R$ <?= $resumo['totalVendas'] ?? '0,00' ?></div>
                <div class="flex items-center mt-2">
                    <span class="text-xs">Média: R$ <?= $resumo['mediaPorPedido'] ?? '0,00' ?> por pedido</span>
                </div>
            </div>

            <!-- Itens Vendidos -->
            <div class="card">
                <div class="stat-label">Total de Itens</div>
                <div class="stat-value"><?= $resumo['totalItens'] ?? 0 ?></div>
                <div class="flex items-center mt-2">
                    <span class="text-xs">Média: <?= $resumo['mediaItensPorPedido'] ?? 0 ?> itens por pedido</span>
                </div>
            </div>

            <!-- Pedidos Pagos -->
            <div class="card">
                <div class="stat-label">Pedidos Pagos</div>
                <div class="stat-value"><?= $resumo['percentualPagos'] ?? 0 ?>%</div>
                <div class="h-1 w-full bg-gray-200 mt-3 rounded-full overflow-hidden">
                    <div class="h-1 bg-primary rounded-full" style="width: <?= $resumo['percentualPagos'] ?? 0 ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Gráficos principais -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Gráfico de vendas dos últimos 7 dias -->
            <div class="card">
                <h2 class="card-header">
                    Vendas dos Últimos 7 Dias
                    <?php if ($filtroStatus !== 'todos' || $filtroPago !== 'todos'): ?>
                    <span class="text-xs text-text-light font-normal ml-2">
                        <?php if ($filtroStatus !== 'todos'): ?>
                        (<?= ucfirst($filtroStatus) ?>)
                        <?php endif; ?>
                        <?php if ($filtroPago !== 'todos'): ?>
                        (<?= $filtroPago === 'pago' ? 'Pago' : 'Não pago' ?>)
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                </h2>
                <div class="h-64">
                    <canvas id="vendasSemanaChart"></canvas>
                </div>
            </div>

            <!-- Gráfico de pedidos por status -->
            <div class="card">
                <h2 class="card-header">
                    Pedidos por Status
                    <?php if ($filtroPago !== 'todos'): ?>
                    <span class="text-xs text-text-light font-normal ml-2">
                        (<?= $filtroPago === 'pago' ? 'Pago' : 'Não pago' ?>)
                    </span>
                    <?php endif; ?>
                </h2>
                <div class="h-64">
                    <canvas id="statusPedidosChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Gráficos secundários -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Gráfico de horários de vendas -->
            <div class="card">
                <h2 class="card-header">
                    Distribuição de Pedidos por Horário
                    <?php if ($filtroStatus !== 'todos' || $filtroPago !== 'todos'): ?>
                    <span class="text-xs text-text-light font-normal ml-2">
                        <?php if ($filtroStatus !== 'todos'): ?>
                        (<?= ucfirst($filtroStatus) ?>)
                        <?php endif; ?>
                        <?php if ($filtroPago !== 'todos'): ?>
                        (<?= $filtroPago === 'pago' ? 'Pago' : 'Não pago' ?>)
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                </h2>
                <div class="h-64">
                    <canvas id="horariosVendasChart"></canvas>
                </div>
            </div>

            <!-- Mesas mais utilizadas -->
            <div class="card">
                <h2 class="card-header">
                    Mesas Mais Utilizadas
                    <?php if ($filtroStatus !== 'todos' || $filtroPago !== 'todos'): ?>
                    <span class="text-xs text-text-light font-normal ml-2">
                        <?php if ($filtroStatus !== 'todos'): ?>
                        (<?= ucfirst($filtroStatus) ?>)
                        <?php endif; ?>
                        <?php if ($filtroPago !== 'todos'): ?>
                        (<?= $filtroPago === 'pago' ? 'Pago' : 'Não pago' ?>)
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                </h2>
                <div class="h-64 overflow-auto">
                    <?php if (empty($mesasMaisUtilizadas)): ?>
                        <p class="text-text-light text-center py-4">Nenhum dado de mesa disponível</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($mesasMaisUtilizadas as $mesa): ?>
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium text-text-dark">Mesa <?= $mesa['mesa'] ?></span>
                                    <span class="text-sm font-medium text-text-dark"><?= $mesa['quantidade'] ?> pedidos (<?= $mesa['porcentagem'] ?>%)</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="bg-secondary h-2.5 rounded-full" style="width: <?= $mesa['porcentagem'] ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Produtos mais vendidos -->
        <div class="card mb-8">
            <h2 class="card-header">
                Produtos mais Vendidos
                <?php if ($filtroStatus !== 'todos' || $filtroPago !== 'todos'): ?>
                <span class="text-xs text-text-light font-normal ml-2">
                    <?php if ($filtroStatus !== 'todos'): ?>
                    (<?= ucfirst($filtroStatus) ?>)
                    <?php endif; ?>
                    <?php if ($filtroPago !== 'todos'): ?>
                    (<?= $filtroPago === 'pago' ? 'Pago' : 'Não pago' ?>)
                    <?php endif; ?>
                </span>
                <?php endif; ?>
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-text-light uppercase tracking-wider">Produto</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-text-light uppercase tracking-wider">Quantidade</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-text-light uppercase tracking-wider">Valor Total</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-text-light uppercase tracking-wider">Porcentagem</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php 
                        $totalQuantidade = 0;
                        foreach ($produtosMaisVendidos as $produto) {
                            $totalQuantidade += intval($produto['quantidade'] ?? 0);
                        }
                        
                        if (empty($produtosMaisVendidos)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-text-light text-center">
                                Nenhum produto vendido ainda
                            </td>
                        </tr>
                        <?php else: 
                            foreach ($produtosMaisVendidos as $produto): 
                                $porcentagem = $totalQuantidade > 0 ? round((intval($produto['quantidade'] ?? 0) / $totalQuantidade) * 100) : 0;
                                $valorTotal = number_format(floatval($produto['valorTotal'] ?? 0), 2, ',', '.');
                        ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-text-dark">
                                <?= htmlspecialchars($produto['nome'] ?? "Produto #{$produto['id']}") ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-text-dark text-center">
                                <?= intval($produto['quantidade'] ?? 0) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-text-dark text-center">
                                R$ <?= $valorTotal ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-text-dark">
                                <div class="flex items-center">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="bg-primary h-2.5 rounded-full" style="width: <?= $porcentagem ?>%"></div>
                                    </div>
                                    <span class="ml-2"><?= $porcentagem ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; 
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Seção de Gerenciamento de Caixa -->
        <div class="border-t-2 border-gray-200 pt-8 mb-8">
            <h2 class="text-2xl font-bold text-text-dark mb-6 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z" />
                </svg>
                Gerenciamento de Caixa
            </h2>
            <p class="text-text-light mb-6">Visualize informações sobre abertura, fechamento e movimentações do caixa.</p>
        </div>

        <?php
        // Obter dados do caixa diretamente do banco de dados
        $dadosCaixa = [];
        $caixaAtual = null;
        $fechamentosCaixa = [];
        $movimentosCaixa = [];
        $resumoCaixa = [
            'total_vendas_periodo' => 0,
            'total_vendas_hoje' => 0,
            'total_dinheiro' => 0,
            'total_cartao_credito' => 0,
            'total_cartao_debito' => 0,
            'total_pix' => 0,
            'total_sangrias' => 0,
            'total_suprimentos' => 0,
            'saldo_em_caixa' => 0,
            'caixa_status' => 'fechado',
            'data_abertura' => null,
            'valor_inicial' => 0
        ];

        try {
            // Verificar se há um caixa aberto
            $caixaAtual = $db->read("tb_fechamentos_caixa", ["*"], "status = 'aberto'", "id DESC", 1);
            if (!empty($caixaAtual)) {
                $caixaAtual = $caixaAtual[0];
                
                // Buscar movimentos do caixa atual
                $movimentosCaixa = $db->read(
                    "tb_movimentos_caixa", 
                    ["*"], 
                    "id_fechamento = {$caixaAtual['id']}"
                );
                
                // Calcular resumo
                $resumoCaixa['caixa_status'] = 'aberto';
                $resumoCaixa['data_abertura'] = $caixaAtual['data_abertura'];
                $resumoCaixa['valor_inicial'] = floatval($caixaAtual['valor_inicial']);
                
                $hoje = date('Y-m-d');
                
                foreach ($movimentosCaixa as $movimento) {
                    // Calcular totais por tipo de movimento
                    if ($movimento['tipo'] == 'venda') {
                        $resumoCaixa['total_vendas_periodo'] += floatval($movimento['valor']);
                        
                        // Verificar se é uma venda de hoje
                        $dataMovimento = date('Y-m-d', strtotime($movimento['data_hora']));
                        if ($dataMovimento == $hoje) {
                            $resumoCaixa['total_vendas_hoje'] += floatval($movimento['valor']);
                        }
                        
                        // Acumular por método de pagamento
                        $metodo = $movimento['metodo_pagamento'];
                        if (isset($resumoCaixa['total_' . $metodo])) {
                            $resumoCaixa['total_' . $metodo] += floatval($movimento['valor']);
                        }
                        
                        // Verificar se é venda de material reciclável pela observação
                        if (strpos($movimento['observacao'], 'Material reciclável') !== false) {
                            // Se quiser fazer algo específico para vendas de material reciclável
                            // Por exemplo, adicionar a uma categoria específica
                        }
                    } elseif ($movimento['tipo'] == 'sangria') {
                        $resumoCaixa['total_sangrias'] += floatval($movimento['valor']);
                    } elseif ($movimento['tipo'] == 'suprimento') {
                        $resumoCaixa['total_suprimentos'] += floatval($movimento['valor']);
                    }
                }
                
                // Calcular saldo em caixa (apenas dinheiro)
                $resumoCaixa['saldo_em_caixa'] = 
                    $resumoCaixa['valor_inicial'] + 
                    $resumoCaixa['total_dinheiro'] + 
                    $resumoCaixa['total_suprimentos'] - 
                    $resumoCaixa['total_sangrias'];
            }
            
            // Obter os últimos 5 fechamentos de caixa
            $fechamentosCaixa = $db->read(
                "tb_fechamentos_caixa", 
                ["*"], 
                "status = 'fechado'", 
                "data_fechamento DESC",
                5
            );
        } catch (Exception $e) {
            // Se ocorrer um erro, apenas registramos e seguimos em frente
            error_log("Erro ao obter dados de caixa: " . $e->getMessage());
        }
        ?>

        <!-- Resumo do Caixa Atual -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Status do Caixa -->
            <div class="card">
                <div class="stat-label">Status do Caixa</div>
                <div class="stat-value flex items-center">
                    <?php if ($resumoCaixa['caixa_status'] === 'aberto'): ?>
                        <span class="bg-success/10 text-success rounded-full px-3 py-1 font-bold">Aberto</span>
                    <?php else: ?>
                        <span class="bg-danger/10 text-danger rounded-full px-3 py-1 font-bold">Fechado</span>
                    <?php endif; ?>
                </div>
                <?php if ($resumoCaixa['caixa_status'] === 'aberto'): ?>
                <div class="flex items-center mt-2">
                    <span class="text-xs text-text-light">
                        Aberto em <?= date('d/m/Y \à\s H:i', strtotime($resumoCaixa['data_abertura'])) ?>
                    </span>
                </div>
                <?php else: ?>
                <div class="flex items-center mt-2">
                    <span class="text-xs text-text-light">
                        <?= empty($fechamentosCaixa) ? 'Nenhum fechamento registrado' : 'Último fechamento: ' . date('d/m/Y \à\s H:i', strtotime($fechamentosCaixa[0]['data_fechamento'])) ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Total de Vendas do Período -->
            <div class="card">
                <div class="stat-label">Vendas do Período</div>
                <div class="stat-value">R$ <?= number_format($resumoCaixa['total_vendas_periodo'], 2, ',', '.') ?></div>
                <div class="flex items-center mt-2">
                    <span class="text-xs text-text-light">
                        Desde <?= $resumoCaixa['data_abertura'] ? date('d/m', strtotime($resumoCaixa['data_abertura'])) : 'N/A' ?>
                    </span>
                </div>
            </div>

            <!-- Vendas do Dia -->
            <div class="card">
                <div class="stat-label">Vendas de Hoje</div>
                <div class="stat-value">R$ <?= number_format($resumoCaixa['total_vendas_hoje'], 2, ',', '.') ?></div>
                <div class="flex items-center mt-2">
                    <span class="text-xs text-text-light">
                        <?= date('d/m/Y') ?>
                    </span>
                </div>
            </div>

            <!-- Saldo em Caixa (Dinheiro) -->
            <div class="card">
                <div class="stat-label">Saldo em Caixa (Dinheiro)</div>
                <div class="stat-value">R$ <?= number_format($resumoCaixa['saldo_em_caixa'], 2, ',', '.') ?></div>
                <div class="flex items-center mt-2">
                    <span class="text-xs text-<?= $resumoCaixa['saldo_em_caixa'] >= 0 ? 'success' : 'danger' ?>">
                        <?= $resumoCaixa['saldo_em_caixa'] >= 0 ? 'Saldo positivo' : 'Saldo negativo' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Seção de Vendas de Materiais Recicláveis -->
        <?php
        // Calcular o total de vendas de materiais recicláveis
        $totalVendasMateriais = 0;
        $vendasMateriaisPorTipo = [
            'aluminio' => 0,
            'papel' => 0,
            'plastico' => 0,
            'vidro' => 0,
            'outros' => 0
        ];

        if (!empty($movimentosCaixa)) {
            foreach ($movimentosCaixa as $movimento) {
                if ($movimento['tipo'] == 'venda' && strpos($movimento['observacao'], 'Material reciclável') !== false) {
                    $totalVendasMateriais += floatval($movimento['valor']);
                    
                    // Classificar por tipo de material
                    if (strpos($movimento['observacao'], 'Alumínio') !== false) {
                        $vendasMateriaisPorTipo['aluminio'] += floatval($movimento['valor']);
                    } elseif (strpos($movimento['observacao'], 'Papel') !== false) {
                        $vendasMateriaisPorTipo['papel'] += floatval($movimento['valor']);
                    } elseif (strpos($movimento['observacao'], 'Plástico') !== false) {
                        $vendasMateriaisPorTipo['plastico'] += floatval($movimento['valor']);
                    } elseif (strpos($movimento['observacao'], 'Vidro') !== false) {
                        $vendasMateriaisPorTipo['vidro'] += floatval($movimento['valor']);
                    } else {
                        $vendasMateriaisPorTipo['outros'] += floatval($movimento['valor']);
                    }
                }
            }
        }

        // Porcentagem das vendas totais que são de materiais recicláveis
        $porcentagemMateriais = $resumoCaixa['total_vendas_periodo'] > 0 ? 
            ($totalVendasMateriais / $resumoCaixa['total_vendas_periodo']) * 100 : 0;
        ?>

        <div class="card mb-8">
            <h2 class="card-header">Vendas de Materiais Recicláveis</h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-4">
                <!-- Resumo das vendas de materiais -->
                <div>
                    <div class="mb-4">
                        <div class="text-lg font-semibold text-primary">
                            R$ <?= number_format($totalVendasMateriais, 2, ',', '.') ?>
                        </div>
                        <div class="text-sm text-text-light">
                            Total de vendas de materiais recicláveis (<?= number_format($porcentagemMateriais, 1) ?>% das vendas)
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <!-- Alumínio -->
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-sm font-medium text-text-dark">Alumínio</span>
                                <span class="text-sm font-medium text-text-dark">R$ <?= number_format($vendasMateriaisPorTipo['aluminio'], 2, ',', '.') ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <?php 
                                $porcentagemAluminio = $totalVendasMateriais > 0 ? 
                                    ($vendasMateriaisPorTipo['aluminio'] / $totalVendasMateriais) * 100 : 0;
                                ?>
                                <div class="bg-amber-500 h-2.5 rounded-full" style="width: <?= $porcentagemAluminio ?>%"></div>
                            </div>
                        </div>
                        
                        <!-- Papel -->
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-sm font-medium text-text-dark">Papel</span>
                                <span class="text-sm font-medium text-text-dark">R$ <?= number_format($vendasMateriaisPorTipo['papel'], 2, ',', '.') ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <?php 
                                $porcentagemPapel = $totalVendasMateriais > 0 ? 
                                    ($vendasMateriaisPorTipo['papel'] / $totalVendasMateriais) * 100 : 0;
                                ?>
                                <div class="bg-blue-500 h-2.5 rounded-full" style="width: <?= $porcentagemPapel ?>%"></div>
                            </div>
                        </div>
                        
                        <!-- Plástico -->
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-sm font-medium text-text-dark">Plástico</span>
                                <span class="text-sm font-medium text-text-dark">R$ <?= number_format($vendasMateriaisPorTipo['plastico'], 2, ',', '.') ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <?php 
                                $porcentagemPlastico = $totalVendasMateriais > 0 ? 
                                    ($vendasMateriaisPorTipo['plastico'] / $totalVendasMateriais) * 100 : 0;
                                ?>
                                <div class="bg-emerald-500 h-2.5 rounded-full" style="width: <?= $porcentagemPlastico ?>%"></div>
                            </div>
                        </div>
                        
                        <!-- Vidro -->
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-sm font-medium text-text-dark">Vidro</span>
                                <span class="text-sm font-medium text-text-dark">R$ <?= number_format($vendasMateriaisPorTipo['vidro'], 2, ',', '.') ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <?php 
                                $porcentagemVidro = $totalVendasMateriais > 0 ? 
                                    ($vendasMateriaisPorTipo['vidro'] / $totalVendasMateriais) * 100 : 0;
                                ?>
                                <div class="bg-indigo-500 h-2.5 rounded-full" style="width: <?= $porcentagemVidro ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Instruções ou mensagem -->
                <div class="flex items-center justify-center">
                    <?php if ($totalVendasMateriais > 0): ?>
                        <div class="text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-success mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <p class="text-text-dark font-medium">Vendas de materiais recicláveis registradas com sucesso!</p>
                            <p class="text-text-light text-sm mt-1">Os valores serão incluídos no fechamento do caixa.</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-text-light mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-text-dark font-medium">Nenhuma venda de material reciclável registrada</p>
                            <p class="text-text-light text-sm mt-1">Use o formulário de registro para incluir vendas de materiais.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Detalhamento de Movimentações -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Formas de Pagamento -->
            <div class="card">
                <h2 class="card-header">Formas de Pagamento</h2>
                <div class="mt-4 space-y-4">
                    <!-- Dinheiro -->
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-medium text-text-dark">Dinheiro</span>
                            <span class="text-sm font-medium text-text-dark">R$ <?= number_format($resumoCaixa['total_dinheiro'], 2, ',', '.') ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <?php 
                            $totalPagamentos = $resumoCaixa['total_dinheiro'] + $resumoCaixa['total_cartao_credito'] + 
                                              $resumoCaixa['total_cartao_debito'] + $resumoCaixa['total_pix'];
                            $porcentagemDinheiro = $totalPagamentos > 0 ? ($resumoCaixa['total_dinheiro'] / $totalPagamentos) * 100 : 0;
                            ?>
                            <div class="bg-green-500 h-2.5 rounded-full" style="width: <?= $porcentagemDinheiro ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- Cartão de Crédito -->
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-medium text-text-dark">Cartão de Crédito</span>
                            <span class="text-sm font-medium text-text-dark">R$ <?= number_format($resumoCaixa['total_cartao_credito'], 2, ',', '.') ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <?php 
                            $porcentagemCredito = $totalPagamentos > 0 ? ($resumoCaixa['total_cartao_credito'] / $totalPagamentos) * 100 : 0;
                            ?>
                            <div class="bg-blue-500 h-2.5 rounded-full" style="width: <?= $porcentagemCredito ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- Cartão de Débito -->
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-medium text-text-dark">Cartão de Débito</span>
                            <span class="text-sm font-medium text-text-dark">R$ <?= number_format($resumoCaixa['total_cartao_debito'], 2, ',', '.') ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <?php 
                            $porcentagemDebito = $totalPagamentos > 0 ? ($resumoCaixa['total_cartao_debito'] / $totalPagamentos) * 100 : 0;
                            ?>
                            <div class="bg-indigo-500 h-2.5 rounded-full" style="width: <?= $porcentagemDebito ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- PIX -->
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-medium text-text-dark">PIX</span>
                            <span class="text-sm font-medium text-text-dark">R$ <?= number_format($resumoCaixa['total_pix'], 2, ',', '.') ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <?php 
                            $porcentagemPix = $totalPagamentos > 0 ? ($resumoCaixa['total_pix'] / $totalPagamentos) * 100 : 0;
                            ?>
                            <div class="bg-purple-500 h-2.5 rounded-full" style="width: <?= $porcentagemPix ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Suprimentos e Sangrias -->
            <div class="card">
                <h2 class="card-header">Suprimentos e Sangrias</h2>
                <div class="mt-4">
                    <div class="flex justify-between mb-4">
                        <div class="text-center">
                            <div class="text-sm text-text-light mb-1">Suprimentos</div>
                            <div class="text-xl font-bold text-success">+ R$ <?= number_format($resumoCaixa['total_suprimentos'], 2, ',', '.') ?></div>
                        </div>
                        <div class="text-center">
                            <div class="text-sm text-text-light mb-1">Sangrias</div>
                            <div class="text-xl font-bold text-danger">- R$ <?= number_format($resumoCaixa['total_sangrias'], 2, ',', '.') ?></div>
                        </div>
                        <div class="text-center">
                            <div class="text-sm text-text-light mb-1">Saldo</div>
                            <div class="text-xl font-bold text-primary">
                                R$ <?= number_format($resumoCaixa['total_suprimentos'] - $resumoCaixa['total_sangrias'], 2, ',', '.') ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($movimentosCaixa)): ?>
                    <div class="mt-4">
                        <h3 class="text-sm font-semibold text-text-dark mb-2">Movimentações Recentes</h3>
                        <div class="max-h-[200px] overflow-y-auto pr-2">
                            <?php 
                            // Filtrar apenas suprimentos e sangrias
                            $movimentacoesRecentes = array_filter($movimentosCaixa, function($movimento) {
                                return $movimento['tipo'] == 'suprimento' || $movimento['tipo'] == 'sangria';
                            });
                            
                            // Ordenar por data (mais recente primeiro)
                            usort($movimentacoesRecentes, function($a, $b) {
                                return strtotime($b['data_hora']) - strtotime($a['data_hora']);
                            });
                            
                            // Pegar apenas as 5 mais recentes
                            $movimentacoesRecentes = array_slice($movimentacoesRecentes, 0, 5);
                            
                            if (empty($movimentacoesRecentes)):
                            ?>
                                <p class="text-text-light text-sm italic text-center">Nenhuma movimentação de suprimento ou sangria registrada.</p>
                            <?php else: ?>
                                <div class="space-y-2">
                                    <?php foreach ($movimentacoesRecentes as $mov): ?>
                                        <div class="flex justify-between items-center p-2 bg-gray-50 rounded-lg">
                                            <div>
                                                <div class="text-sm font-medium text-text-dark">
                                                    <?= $mov['tipo'] == 'suprimento' ? 'Suprimento' : 'Sangria' ?>
                                                </div>
                                                <div class="text-xs text-text-light">
                                                    <?= date('d/m/Y H:i', strtotime($mov['data_hora'])) ?>
                                                </div>
                                            </div>
                                            <div class="text-sm font-semibold <?= $mov['tipo'] == 'suprimento' ? 'text-success' : 'text-danger' ?>">
                                                <?= $mov['tipo'] == 'suprimento' ? '+' : '-' ?> R$ <?= number_format($mov['valor'], 2, ',', '.') ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Histórico de Fechamentos -->
        <div class="card mb-8">
            <h2 class="card-header">Histórico de Fechamentos</h2>
            <?php if (empty($fechamentosCaixa)): ?>
            <div class="py-6 text-center text-text-light">
                <p>Nenhum fechamento de caixa registrado ainda.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-text-light uppercase tracking-wider">Data</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-text-light uppercase tracking-wider">Usuário</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-text-light uppercase tracking-wider">Valor Inicial</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-text-light uppercase tracking-wider">Valor Final</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-text-light uppercase tracking-wider">Valor Esperado</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-text-light uppercase tracking-wider">Diferença</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php foreach ($fechamentosCaixa as $fechamento): ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <div class="font-medium text-text-dark">
                                    <?= date('d/m/Y', strtotime($fechamento['data_fechamento'])) ?>
                                </div>
                                <div class="text-xs text-text-light">
                                    <?= date('H:i', strtotime($fechamento['data_fechamento'])) ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-text-dark text-center">
                                <?= htmlspecialchars($fechamento['usuario_fechamento']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-text-dark text-center">
                                R$ <?= number_format($fechamento['valor_inicial'], 2, ',', '.') ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-text-dark text-center">
                                R$ <?= number_format($fechamento['valor_final'], 2, ',', '.') ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-text-dark text-center">
                                R$ <?= number_format($fechamento['valor_esperado'], 2, ',', '.') ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                <?php 
                                $diferenca = floatval($fechamento['diferenca']);
                                $textClass = $diferenca >= 0 ? 'text-success' : 'text-danger';
                                $sinal = $diferenca >= 0 ? '+' : '';
                                ?>
                                <span class="font-medium <?= $textClass ?>">
                                    <?= $sinal ?> R$ <?= number_format(abs($diferenca), 2, ',', '.') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Configuração de cores
    const colors = {
        primary: '#FF6B35',
        secondary: '#2EC4B6',
        accent: '#FFBF69',
        success: '#43A047',
        danger: '#E53935',
        preparando: '#FFB74D',
        pronto: '#4FC3F7', 
        entregue: '#66BB6A',
        cancelado: '#EF5350',
        transparentPrimary: 'rgba(255, 107, 53, 0.2)',
        transparentSecondary: 'rgba(46, 196, 182, 0.2)'
    };

    // Inicializar date pickers
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar datepickers
        flatpickr("#data_inicio", {
            dateFormat: "Y-m-d",
            maxDate: "today"
        });
        
        flatpickr("#data_fim", {
            dateFormat: "Y-m-d",
            maxDate: "today"
        });
        
        // Atualizar título com base nos filtros aplicados
        atualizarTituloDashboard();
    });
    
    // Função para o toggle do menu mobile
    function toggleMobileMenu(menuId) {
        const menu = document.getElementById(menuId);
        if (menu) {
            if (menu.classList.contains('hidden')) {
                menu.classList.remove('hidden');
                menu.classList.add('block');
            } else {
                menu.classList.remove('block');
                menu.classList.add('hidden');
            }
        }
    }

    // Função para atualizar o título do dashboard com base nos filtros
    function atualizarTituloDashboard() {
        const filtroStatus = "<?= htmlspecialchars($filtroStatus) ?>";
        const filtroPago = "<?= htmlspecialchars($filtroPago) ?>";
        const filtroDataInicio = "<?= htmlspecialchars($filtroDataInicio) ?>";
        const filtroDataFim = "<?= htmlspecialchars($filtroDataFim) ?>";
        
        let titulo = document.querySelector('h1');
        let subtitulo = '';
        
        if (filtroStatus !== 'todos') {
            subtitulo += `Status: ${filtroStatus.charAt(0).toUpperCase() + filtroStatus.slice(1)} | `;
        }
        
        if (filtroPago !== 'todos') {
            subtitulo += `Pagamento: ${filtroPago === 'pago' ? 'Pago' : 'Não pago'} | `;
        }
        
        // Adicionar período
        const dataInicio = new Date(filtroDataInicio);
        const dataFim = new Date(filtroDataFim);
        
        const formatarData = (data) => {
            return data.toLocaleDateString('pt-BR');
        };
        
        subtitulo += `Período: ${formatarData(dataInicio)} até ${formatarData(dataFim)}`;
        
        // Criar ou atualizar elemento de subtítulo
        let subtituloEl = document.getElementById('dashboard-subtitulo');
        if (!subtituloEl) {
            subtituloEl = document.createElement('p');
            subtituloEl.id = 'dashboard-subtitulo';
            subtituloEl.className = 'text-sm text-text-light mt-1';
            titulo.parentNode.insertBefore(subtituloEl, titulo.nextSibling);
        }
        
        subtituloEl.textContent = subtitulo;
    }

    // Função para garantir arrays não vazios
    function garantirArrayNaoVazio(arr, tamanho = 7, valorPadrao = 0) {
        if (!arr || !Array.isArray(arr) || arr.length === 0) {
            return Array(tamanho).fill(valorPadrao);
        }
        return arr;
    }

    // Dados para os gráficos
    let vendasSemana = <?= json_encode(array_map(function($dia) { 
        return floatval($dia['valor'] ?? 0); 
    }, $vendasPorDia)) ?>;
    
    let labelsSemana = <?= json_encode(array_map(function($dia) { 
        return $dia['label'] ?? ''; 
    }, $vendasPorDia)) ?>;
    
    // Garantir que temos os valores corretos para o status dos pedidos
    let statusValores = [
        <?= $vendasPorStatus['preparando'] ?? 0 ?>,
        <?= $vendasPorStatus['pronto'] ?? 0 ?>,
        <?= $vendasPorStatus['entregue'] ?? 0 ?>,
        <?= $vendasPorStatus['cancelado'] ?? 0 ?>
    ];
    
    let statusLabels = ['Preparando', 'Pronto', 'Entregue', 'Cancelado'];
    const colorsStatus = [colors.preparando, colors.pronto, colors.entregue, colors.cancelado];
    
    // Dados para o gráfico de horários
    let horariosData = <?= json_encode(array_map(function($hora) { 
        return intval($hora['pedidos'] ?? 0); 
    }, $horariosVendas)) ?>;
    
    let horariosLabels = <?= json_encode(array_map(function($hora) { 
        return $hora['hora'] ?? ''; 
    }, $horariosVendas)) ?>;

    // Garantir que temos dados para todos os gráficos
    vendasSemana = garantirArrayNaoVazio(vendasSemana);
    labelsSemana = garantirArrayNaoVazio(labelsSemana, 7, '');
    statusValores = garantirArrayNaoVazio(statusValores, 4);
    horariosData = garantirArrayNaoVazio(horariosData, 24);
    horariosLabels = garantirArrayNaoVazio(horariosLabels, 24, '');

    // Debug: Mostrar dados dos gráficos no console
    console.log("Dados de vendas por semana:", {valores: vendasSemana, labels: labelsSemana});
    console.log("Dados de pedidos por status:", {valores: statusValores, labels: statusLabels});
    console.log("Dados de horários:", {valores: horariosData, labels: horariosLabels});

    // Inicializar gráficos quando o DOM estiver pronto
    document.addEventListener('DOMContentLoaded', function() {
        try {
            // Gráfico de vendas da semana
            const ctxVendas = document.getElementById('vendasSemanaChart').getContext('2d');
            new Chart(ctxVendas, {
                type: 'line',
                data: {
                    labels: labelsSemana,
                    datasets: [{
                        label: 'Vendas (R$)',
                        data: vendasSemana,
                        borderColor: colors.primary,
                        backgroundColor: colors.transparentPrimary,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: colors.primary,
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toFixed(2).replace('.', ',');
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'R$ ' + context.raw.toFixed(2).replace('.', ',');
                                }
                            }
                        }
                    }
                }
            });

            // Gráfico de pedidos por status
            const ctxStatus = document.getElementById('statusPedidosChart').getContext('2d');
            new Chart(ctxStatus, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusValores,
                        backgroundColor: colorsStatus,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${value} pedidos (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            // Gráfico de pedidos por horário
            const ctxHorarios = document.getElementById('horariosVendasChart').getContext('2d');
            new Chart(ctxHorarios, {
                type: 'line',
                data: {
                    labels: horariosLabels,
                    datasets: [{
                        label: 'Pedidos',
                        data: horariosData,
                        backgroundColor: colors.transparentSecondary,
                        borderColor: colors.secondary,
                        borderWidth: 2,
                        pointBackgroundColor: colors.secondary,
                        pointBorderWidth: 1,
                        pointRadius: 3,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        } catch (e) {
            console.error('Erro ao carregar gráficos:', e);
            
            // Mostrar mensagem de erro nos containers de gráficos se eles existirem
            const containers = [
                'vendasSemanaChart', 
                'statusPedidosChart', 
                'horariosVendasChart'
            ];
            
            containers.forEach(id => {
                const container = document.getElementById(id);
                if (container) {
                    container.outerHTML = `
                    <div class="flex items-center justify-center h-full w-full bg-gray-100 rounded-lg">
                        <div class="text-text-light text-center p-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 text-text-light" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <p>Não foi possível carregar o gráfico</p>
                            <p class="text-xs mt-1">Erro: ${e.message}</p>
                        </div>
                    </div>`;
                }
            });
        }
    });

    // Configurar eventos para os botões e modais
    function setupEvents() {
        // Botão de localização automática
        document.getElementById('btnLocalizacao').addEventListener('click', localizarUsuario);
        
        // Botão para abrir modal de endereço manual
        document.getElementById('btnEnderecoManual').addEventListener('click', abrirModalEndereco);
        
        // Botão para fechar modal de endereço
        document.getElementById('btnFecharModalEndereco').addEventListener('click', fecharModalEndereco);
        
        // Botão para confirmar endereço manual
        document.getElementById('btnConfirmarEndereco').addEventListener('click', confirmarEnderecoManual);
        
        // Botão para buscar CEP
        document.getElementById('btnBuscarCEP').addEventListener('click', buscarCEP);
        
        // Formatação automática do campo CEP
        document.getElementById('cep').addEventListener('input', function() {
            let cep = this.value.replace(/\D/g, ''); // Remove caracteres não numéricos
            
            if (cep.length > 5) {
                cep = cep.substring(0, 5) + '-' + cep.substring(5);
            }
            
            this.value = cep;
        });
        
        // Permitir buscar CEP com a tecla Enter
        document.getElementById('cep').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                buscarCEP();
            }
        });
    }

    // Função para localizar o usuário
    function localizarUsuario() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const pos = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                    };
                    
                    // Definir a localização do usuário
                    definirLocalizacaoUsuario(pos);
                },
                (error) => {
                    console.error("Erro de geolocalização:", error);
                    let mensagemErro = "Não foi possível obter sua localização. ";
                    
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            mensagemErro += "Você precisa permitir o acesso à sua localização.";
                            break;
                        case error.POSITION_UNAVAILABLE:
                            mensagemErro += "Informação de localização indisponível.";
                            break;
                        case error.TIMEOUT:
                            mensagemErro += "Tempo limite excedido ao obter localização.";
                            break;
                        case error.UNKNOWN_ERROR:
                            mensagemErro += "Erro desconhecido ao obter localização.";
                            break;
                    }
                    
                    alert(mensagemErro + "\n\nVocê pode inserir sua localização manualmente.");
                    
                    // Abrir modal para inserção manual de endereço
                    abrirModalEndereco();
                },
                { 
                    enableHighAccuracy: true,  // Solicitar a melhor precisão possível
                    timeout: 10000,            // Tempo limite em milissegundos
                    maximumAge: 0              // Não usar cache de localização
                }
            );
        } else {
            alert("Seu navegador não suporta geolocalização. Por favor, insira sua localização manualmente.");
            abrirModalEndereco();
        }
    }

    // Definir a localização do usuário
    function definirLocalizacaoUsuario(pos) {
        console.log("Definindo localização do usuário para:", pos);
        
        // Remover todos os marcadores existentes do mapa
        limparTodosMarcadores();
        
        // Ícone personalizado para o usuário
        const blueIcon = L.icon({
            iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
            shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });
        
        // Criar novo marcador para o usuário
        userMarker = L.marker([pos.lat, pos.lng], {
            title: "Sua localização",
            icon: blueIcon
        }).addTo(map);
        
        userMarker.bindPopup("<b>Sua localização</b>").openPopup();
        
        // Centralizar o mapa na posição do usuário
        map.setView([pos.lat, pos.lng], 13);
        
        // Mostrar carregando nos cards enquanto busca os pontos
        document.querySelector('.grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3.gap-6.mb-8').innerHTML = `
            <div class="col-span-full p-6 bg-white rounded-xl shadow-md text-center">
                <p class="text-text-light mb-2">Buscando pontos de coleta próximos...</p>
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div class="bg-primary h-2.5 rounded-full w-3/4 animate-pulse"></div>
                </div>
            </div>
        `;
        
        // Gerar pontos simulados ao redor da localização do usuário
        const pontosProximos = gerarPontosSimulados(pos);
            
        // Armazenar os pontos encontrados na variável global para uso posterior
        pontosColetaAtuais = pontosProximos;
        
        // Agora adicionar os marcadores para esses pontos próximos
        adicionarMarcadoresPontos(pontosProximos);
        
        // Calcular distâncias para cada ponto de coleta
        calcularDistancias(pos, pontosProximos);
    }

    // Função para gerar pontos simulados para demonstração
    function gerarPontosSimulados(posicaoUsuario) {
        // Valores médios de preços para usar nos pontos simulados
        const precosMedias = {
            aluminio: 18.75,
            papel: 2.35,
            plastico: 3.75,
            vidro: 1.25
        };
        
        // Gerar alguns pontos simulados
        const pontosSimulados = [];
        const nomesPontos = [
            "EcoPonto Central", 
            "Reciclagem Verde", 
            "Cooperativa Recicle Já", 
            "Reciclagem Sustentável", 
            "EcoCoop do Bairro", 
            "Ponto Verde Reciclagem"
        ];
        
        // Gerar pontos dentro de um raio aleatório
        for (let i = 0; i < 6; i++) {
            // Criar uma variação aleatória em torno da localização do usuário
            // para simular pontos de coleta espalhados ao redor
            const latOffset = (Math.random() - 0.5) * 0.05; // ±0.025 graus (~2-3km)
            const lngOffset = (Math.random() - 0.5) * 0.05;
            
            // Criar uma variação de preço para cada material
            // Simular variação de até 10% para mais ou para menos
            const variacao = () => 1 + (Math.random() * 0.2 - 0.1); // -10% a +10%
            
            // Para simular alguns preços não disponíveis que usarão a média
            // Vamos aleatoriamente marcar alguns preços como "médios"
            const statusPrecos = {
                aluminio: Math.random() > 0.3, // 70% chance de ter preço real
                papel: Math.random() > 0.3,
                plastico: Math.random() > 0.3,
                vidro: Math.random() > 0.3
            };
            
            // Se o status do preço for false, usamos o preço médio
            const materiais = {
                aluminio: statusPrecos.aluminio ? precosMedias.aluminio * variacao() : precosMedias.aluminio,
                papel: statusPrecos.papel ? precosMedias.papel * variacao() : precosMedias.papel,
                plastico: statusPrecos.plastico ? precosMedias.plastico * variacao() : precosMedias.plastico,
                vidro: statusPrecos.vidro ? precosMedias.vidro * variacao() : precosMedias.vidro
            };
            
            // Registrar quais preços são médios
            const precosMedios = {
                aluminio: !statusPrecos.aluminio,
                papel: !statusPrecos.papel,
                plastico: !statusPrecos.plastico,
                vidro: !statusPrecos.vidro
            };
            
            // Calcular a distância real para este ponto
            const lat = posicaoUsuario.lat + latOffset;
            const lng = posicaoUsuario.lng + lngOffset;
            const distancia = calcularDistanciaHaversine(
                posicaoUsuario.lat, 
                posicaoUsuario.lng, 
                lat, 
                lng
            );
            
            pontosSimulados.push({
                id: i + 1,
                nome: nomesPontos[i],
                endereco: `Localidade próxima ${i + 1} (${distancia.toFixed(2)} km)`,
                lat: lat,
                lng: lng,
                materiais: materiais,
                precosMedios: precosMedios,
                distancia: distancia
            });
        }
        
        // Ordenar por distância
        pontosSimulados.sort((a, b) => a.distancia - b.distancia);
        
        return pontosSimulados;
    }

    // Calcular distâncias entre a posição do usuário e os pontos de coleta
    function calcularDistancias(posicaoUsuario, pontosProximos) {
        // Array para armazenar distâncias
        const distancias = [];
        
        // Calcular distância para cada ponto usando a fórmula de Haversine
        pontosProximos.forEach((ponto, index) => {
            const distancia = calcularDistanciaHaversine(
                posicaoUsuario.lat,
                posicaoUsuario.lng,
                ponto.lat,
                ponto.lng
            );
            
            distancias.push({
                index: index,
                distancia: distancia,
                ponto: ponto
            });
        });
        
        // Ordenar por distância
        distancias.sort((a, b) => a.distancia - b.distancia);
        
        // Atualizar as cards com informações de distância
        atualizarCardsComDistancia(distancias);
    }

    // Função para atualizar as cards com informação de distância
    function atualizarCardsComDistancia(distanciasOrdenadas) {
        console.log("Atualizando cards com", distanciasOrdenadas.length, "pontos de coleta");
        
        // Selecionar o container das cards
        const containerCards = document.querySelector('.grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3.gap-6.mb-8');
        
        // Limpar o container
        containerCards.innerHTML = '';
        
        // Se não houver pontos, mostrar mensagem
        if (distanciasOrdenadas.length === 0) {
            containerCards.innerHTML = `
                <div class="col-span-full p-6 bg-white rounded-xl shadow-md text-center">
                    <p class="text-text-light">Nenhum ponto de coleta encontrado na região. Tente buscar em outra localidade.</p>
                </div>
            `;
            return;
        }
        
        // Recriar as cards na ordem de proximidade
        distanciasOrdenadas.forEach(item => {
            const ponto = item.ponto;
            const distancia = item.distancia;
            
            // Função para criar o badge de preço com indicação se é médio
            const createPriceBadge = (material, valor, isMedio) => {
                const mediaTag = isMedio ? 
                    `<span class="text-xs text-yellow-600 ml-1 bg-yellow-100 px-1 rounded">média</span>` : '';
                
                return `
                <div class="flex items-center">
                    <div class="bg-${getBadgeColor(material)}-100 text-${getBadgeColor(material)}-800 border border-${getBadgeColor(material)}-200 text-xs font-medium py-1 px-2 rounded-full mr-1">${material.charAt(0).toUpperCase() + material.slice(1)}</div>
                    <span class="ml-1 text-primary text-sm font-medium">
                        R$ ${valor.toFixed(2).replace('.', ',')}${mediaTag}
                    </span>
                </div>
                `;
            };
            
            // Função auxiliar para obter a cor do badge baseado no material
            function getBadgeColor(material) {
                switch(material) {
                    case 'aluminio': return 'amber';
                    case 'papel': return 'blue';
                    case 'plastico': return 'emerald';
                    case 'vidro': return 'indigo';
                    default: return 'gray';
                }
            }
            
            // Criar o elemento da card
            const cardHTML = `
            <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300">
                <div class="p-5 border-b border-gray-100">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-lg font-bold text-text-dark flex items-center">
                                ${ponto.nome}
                            </h3>
                            <p class="text-text-light text-sm mt-1">
                                ${ponto.endereco}
                            </p>
                            <p class="text-primary text-sm font-medium mt-1">
                                ${distancia.toFixed(2)} km de distância
                            </p>
                        </div>
                        <button 
                            data-lat="${ponto.lat}"
                            data-lng="${ponto.lng}"
                            class="btnVerNoMapa text-secondary hover:text-secondary-dark text-sm font-medium transition-colors duration-200 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                            </svg>
                            Ver no Mapa
                        </button>
                    </div>
                </div>
                <div class="px-5 py-4">
                    <div class="mb-2 text-text-dark font-medium text-sm flex justify-between items-center">
                        <span>Materiais aceitos e preços:</span>
                        ${ponto.precosMedios ? 
                        `<span class="text-xs text-yellow-600 bg-yellow-50 py-1 px-2 rounded-full">
                            alguns preços são médios regionais
                        </span>` : ''}
                    </div>
                    <div class="grid grid-cols-2 gap-2 mb-2">
                        ${createPriceBadge('aluminio', ponto.materiais.aluminio, ponto.precosMedios?.aluminio)}
                        ${createPriceBadge('papel', ponto.materiais.papel, ponto.precosMedios?.papel)}
                        ${createPriceBadge('plastico', ponto.materiais.plastico, ponto.precosMedios?.plastico)}
                        ${createPriceBadge('vidro', ponto.materiais.vidro, ponto.precosMedios?.vidro)}
                    </div>
                    <button 
                        class="mt-2 w-full bg-primary/10 hover:bg-primary/20 text-primary font-medium p-2 rounded transition-colors duration-200 text-sm">
                        Solicitar Coleta
                    </button>
                </div>
            </div>
            `;
            
            // Adicionar a card ao container
            containerCards.innerHTML += cardHTML;
        });
        
        // Reconfigurar os eventos para os novos botões "Ver no Mapa"
        document.querySelectorAll('.btnVerNoMapa').forEach(btn => {
            btn.addEventListener('click', function() {
                const lat = parseFloat(this.getAttribute('data-lat'));
                const lng = parseFloat(this.getAttribute('data-lng'));
                
                map.setView([lat, lng], 15);
                
                // Encontrar e abrir o popup do marcador correspondente
                const index = markers.findIndex(m => {
                    const position = m.getLatLng();
                    return Math.abs(position.lat - lat) < 0.0001 && Math.abs(position.lng - lng) < 0.0001;
                });
                
                if (index >= 0) {
                    markers[index].openPopup();
                }
            });
        });
    }

    // Ajustar a vista do mapa para mostrar todos os pontos
    function ajustarVistaMapa(pontosProximos) {
        try {
            // Criar limites incluindo a posição do usuário
            let bounds = userMarker ? L.latLngBounds([userMarker.getLatLng()]) : null;
            
            if (!bounds) {
                // Se não temos usuário, usar o primeiro ponto como referência
                if (pontosProximos.length > 0) {
                    bounds = L.latLngBounds([[pontosProximos[0].lat, pontosProximos[0].lng]]);
                } else {
                    // Fallback para visão padrão
                    map.setView([defaultLat, defaultLng], 11);
                    return;
                }
            }
            
            // Adicionar todos os pontos aos limites
            pontosProximos.forEach(ponto => {
                bounds.extend([ponto.lat, ponto.lng]);
            });
            
            // Ajustar o mapa para mostrar todos os pontos
            map.fitBounds(bounds, {
                padding: [50, 50],
                maxZoom: 14
            });
            
            // Destacar o ponto mais próximo após um breve atraso
            setTimeout(() => {
                if (markers.length > 0 && userMarker) {
                    // Encontrar o ponto mais próximo
                    let pontoMaisProximo = 0;
                    let menorDistancia = Number.MAX_VALUE;
                    
                    const userPos = userMarker.getLatLng();
                    
                    pontosProximos.forEach((ponto, index) => {
                        const distancia = calcularDistanciaHaversine(
                            userPos.lat, 
                            userPos.lng, 
                            ponto.lat, 
                            ponto.lng
                        );
                        
                        if (distancia < menorDistancia) {
                            menorDistancia = distancia;
                            pontoMaisProximo = index;
                        }
                    });
                    
                    // Destacar o ponto mais próximo
                    if (markers[pontoMaisProximo]) {
                        markers[pontoMaisProximo].openPopup();
                    }
                }
            }, 800);
        } catch (error) {
            console.error("Erro ao ajustar os limites do mapa:", error);
            
            // Fallback: apenas centralizar na posição do usuário
            if (userMarker) {
                map.setView(userMarker.getLatLng(), 13);
            } else {
                map.setView([defaultLat, defaultLng], 11);
            }
        }
    }

    // Função para calcular a distância usando Haversine (considera a curvatura da Terra)
    function calcularDistanciaHaversine(lat1, lng1, lat2, lng2) {
        const raioTerra = 6371; // Raio médio da Terra em km
        
        const dLat = deg2rad(lat2 - lat1);
        const dLng = deg2rad(lng2 - lng1);
        
        const a = 
            Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) * 
            Math.sin(dLng/2) * Math.sin(dLng/2);
        
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        const distancia = raioTerra * c; // Distância em km
        
        return distancia;
    }
    
    // Converter graus para radianos
    function deg2rad(deg) {
        return deg * (Math.PI/180);
    }
    
    // Abrir modal de endereço manual
    function abrirModalEndereco() {
        const modal = document.getElementById('enderecoManualModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Se o usuário já tem uma localização, preencher os campos
        if (userMarker) {
            const position = userMarker.getLatLng();
            document.getElementById('latitude').value = position.lat.toFixed(6);
            document.getElementById('longitude').value = position.lng.toFixed(6);
        }
    }
    
    // Fechar modal de endereço manual
    function fecharModalEndereco() {
        const modal = document.getElementById('enderecoManualModal');
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }
    
    // Confirmar endereço manual
    function confirmarEnderecoManual() {
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        const endereco = document.getElementById('endereco').value.trim();
        
        let lat = parseFloat(latInput.value);
        let lng = parseFloat(lngInput.value);
        
        // Validar as coordenadas
        if (isNaN(lat) || isNaN(lng)) {
            // Se as coordenadas não forem válidas, mas temos um endereço, tentar geocodificar
            if (endereco) {
                // Mostrar mensagem de carregamento
                document.getElementById('btnConfirmarEndereco').innerHTML = 'Processando...';
                document.getElementById('btnConfirmarEndereco').disabled = true;
                
                // Usar Nominatim para geocodificar o endereço
                const params = new URLSearchParams({
                    format: 'json',
                    q: endereco,
                    limit: 1,
                    addressdetails: 1
                });
                
                fetch(`https://nominatim.openstreetmap.org/search?${params.toString()}`, {
                    headers: {
                        'User-Agent': 'ReciclagemApp/1.0'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro ao converter endereço em coordenadas');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.length === 0) {
                        // Tente a busca adicionando "Brasil" ao endereço se ainda não estiver presente
                        if (!endereco.toLowerCase().includes('brasil')) {
                            const params = new URLSearchParams({
                                format: 'json',
                                q: `${endereco}, Brasil`,
                                limit: 1
                            });
                            
                            return fetch(`https://nominatim.openstreetmap.org/search?${params.toString()}`, {
                                headers: {
                                    'User-Agent': 'ReciclagemApp/1.0'
                                }
                            }).then(response => response.json());
                        }
                        throw new Error('Não foi possível encontrar coordenadas para este endereço');
                    }
                    return data;
                })
                .then(data => {
                    if (data.length === 0) {
                        throw new Error('Não foi possível encontrar coordenadas para este endereço');
                    }
                    
                    // Coordenadas encontradas, definir localização
                    definirLocalizacaoUsuario({
                        lat: parseFloat(data[0].lat),
                        lng: parseFloat(data[0].lon)
                    });
                    
                    // Fechar o modal
                    fecharModalEndereco();
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert(error.message || 'Não foi possível converter o endereço em coordenadas. Por favor, insira as coordenadas manualmente.');
                })
                .finally(() => {
                    // Restaurar estado do botão
                    document.getElementById('btnConfirmarEndereco').innerHTML = 'Confirmar Localização';
                    document.getElementById('btnConfirmarEndereco').disabled = false;
                });
            } else {
                alert("Por favor, insira coordenadas válidas ou um endereço.");
                return;
            }
        } else {
            // Verificar se as coordenadas estão em um intervalo válido
            if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                alert("Por favor, insira coordenadas válidas: Latitude (-90 a 90) e Longitude (-180 a 180)");
                return;
            }
            
            // Definir a localização do usuário
            definirLocalizacaoUsuario({
                lat: lat,
                lng: lng
            });
            
            // Fechar o modal
            fecharModalEndereco();
        }
    }
    
    // Função para buscar CEP e converter em coordenadas
    function buscarCEP() {
        const cepInput = document.getElementById('cep');
        let cep = cepInput.value.replace(/\D/g, ''); // Remove caracteres não numéricos
        
        if (cep.length !== 8) {
            alert('Por favor, insira um CEP válido com 8 dígitos.');
            return;
        }
        
        // Mostrar indicador de carregamento
        cepInput.disabled = true;
        document.getElementById('btnBuscarCEP').innerHTML = 'Buscando...';
        document.getElementById('btnBuscarCEP').disabled = true;
        
        // Consultar a API ViaCEP
        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro ao consultar o CEP');
                }
                return response.json();
            })
            .then(data => {
                if (data.erro) {
                    throw new Error('CEP não encontrado');
                }
                
                // Preencher o campo de endereço
                const endereco = `${data.logradouro}, ${data.bairro}, ${data.localidade}, ${data.uf}, Brasil`;
                document.getElementById('endereco').value = endereco;
                
                // Formatar a consulta Nominatim de forma mais específica para o Brasil
                // Primeiro tentar com a query estruturada que é mais precisa
                const params = new URLSearchParams({
                    format: 'json',
                    country: 'Brasil',
                    state: data.uf,
                    city: data.localidade,
                    street: `${data.logradouro}`,
                    postalcode: cep,
                    limit: 1,
                    addressdetails: 1
                });
                
                return fetch(`https://nominatim.openstreetmap.org/search?${params.toString()}`, {
                    headers: {
                        'User-Agent': 'ReciclagemApp/1.0'
                    }
                });
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro ao converter endereço em coordenadas');
                }
                return response.json();
            })
            .then(data => {
                if (data.length === 0) {
                    // Se a busca estruturada falhar, tentar com busca de texto livre
                    const endereco = document.getElementById('endereco').value;
                    const params = new URLSearchParams({
                        format: 'json',
                        q: endereco,
                        limit: 1
                    });
                    
                    return fetch(`https://nominatim.openstreetmap.org/search?${params.toString()}`, {
                        headers: {
                            'User-Agent': 'ReciclagemApp/1.0'
                        }
                    }).then(response => response.json());
                }
                return data;
            })
            .then(data => {
                if (data.length === 0) {
                    // Se ainda não encontrou, tentar uma última alternativa apenas com CEP
                    const params = new URLSearchParams({
                        format: 'json',
                        country: 'Brasil',
                        postalcode: cep,
                        limit: 1
                    });
                    
                    return fetch(`https://nominatim.openstreetmap.org/search?${params.toString()}`, {
                        headers: {
                            'User-Agent': 'ReciclagemApp/1.0'
                        }
                    }).then(response => response.json());
                }
                return data;
            })
            .then(data => {
                if (data.length === 0) {
                    throw new Error('Não foi possível encontrar coordenadas para este CEP. Por favor, tente inserir o endereço completo.');
                }
                
                // Preencher os campos de latitude e longitude
                document.getElementById('latitude').value = data[0].lat;
                document.getElementById('longitude').value = data[0].lon;
                
                // Aplicar a localização imediatamente e fechar o modal
                definirLocalizacaoUsuario({
                    lat: parseFloat(data[0].lat),
                    lng: parseFloat(data[0].lon)
                });
                
                // Fechar o modal
                fecharModalEndereco();
                
                // Mostrar mensagem de sucesso
                alert('Localização encontrada! Mostrando pontos de coleta próximos.');
            })
            .catch(error => {
                console.error('Erro:', error);
                alert(error.message || 'Ocorreu um erro ao buscar o CEP. Tente inserir as coordenadas manualmente.');
            })
            .finally(() => {
                // Restaurar estado dos botões
                cepInput.disabled = false;
                document.getElementById('btnBuscarCEP').innerHTML = 'Buscar';
                document.getElementById('btnBuscarCEP').disabled = false;
            });
    }

    // Lógica para inicializar o mapa quando estiver visível
    document.addEventListener('DOMContentLoaded', function() {
        // Adicionar código do mapa aqui
        if (document.getElementById('mapa')) {
            initMap();
        }
    });
    </script>
    
    <!-- Divisor entre Dashboard e Mapa de Coleta -->
    <div class="container mx-auto px-4 mb-8">
        <div class="border-t-2 border-gray-200 pt-8">
            <h2 class="text-2xl font-bold text-text-dark mb-6 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Mapa de Pontos de Coleta
            </h2>
            <p class="text-text-light mb-6">Visualize pontos de coleta de materiais recicláveis próximos da sua localização atual.</p>
        </div>
    </div>
    
    <!-- Seção do Mapa de Coleta -->
    <div class="container mx-auto px-4 py-4 pb-8">
        <div class="flex flex-col lg:flex-row lg:justify-between lg:items-center mb-8 gap-4">
            <div class="flex space-x-2">
                <button id="btnLocalizacao" type="button"
                    class="bg-primary hover:bg-primary-dark text-white px-4 py-3 rounded-lg shadow-md transition-colors duration-200 flex items-center font-medium">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Minha Localização
                </button>
                <button id="btnEnderecoManual" type="button"
                    class="bg-secondary hover:bg-secondary-dark text-white px-4 py-3 rounded-lg shadow-md transition-colors duration-200 flex items-center font-medium">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Inserir Endereço
                </button>
            </div>
            
            <!-- Painel de Preços Médios -->
            <div class="bg-white rounded-xl shadow-md p-4 flex flex-wrap gap-3">
                <div class="text-sm font-medium text-text-dark mr-2">Preços Médios (R$/kg):</div>
                <div class="flex items-center">
                    <div class="bg-amber-100 text-amber-800 border border-amber-200 text-xs font-medium py-1 px-2 rounded-full mr-1">Alumínio</div>
                    <span class="font-bold text-primary">R$ <?= $mediaPrecos['aluminio'] ?? '0,00' ?></span>
                </div>
                <div class="flex items-center">
                    <div class="bg-blue-100 text-blue-800 border border-blue-200 text-xs font-medium py-1 px-2 rounded-full mr-1">Papel</div>
                    <span class="font-bold text-primary">R$ <?= $mediaPrecos['papel'] ?? '0,00' ?></span>
                </div>
                <div class="flex items-center">
                    <div class="bg-emerald-100 text-emerald-800 border border-emerald-200 text-xs font-medium py-1 px-2 rounded-full mr-1">Plástico</div>
                    <span class="font-bold text-primary">R$ <?= $mediaPrecos['plastico'] ?? '0,00' ?></span>
                </div>
                <div class="flex items-center">
                    <div class="bg-indigo-100 text-indigo-800 border border-indigo-200 text-xs font-medium py-1 px-2 rounded-full mr-1">Vidro</div>
                    <span class="font-bold text-primary">R$ <?= $mediaPrecos['vidro'] ?? '0,00' ?></span>
                </div>
            </div>
        </div>

        <!-- Mapa -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <div class="text-lg font-semibold text-text-dark mb-4">Pontos de Coleta Próximos</div>
            <p class="mb-4 text-text-light">Encontre pontos de coleta de materiais recicláveis mais próximos de você e confira os preços por quilo.</p>
            <div id="mapa" class="w-full h-[500px] rounded-xl shadow-lg relative z-[1]"></div>
        </div>

        <!-- Lista de Pontos de Coleta -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <!-- Cards serão preenchidos dinamicamente via JavaScript -->
        </div>
    </div>
    
    <!-- Modal de endereço manual -->
    <div id="enderecoManualModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[1000] p-4">
        <div class="bg-white rounded-lg p-6 sm:p-8 max-w-md w-full mx-auto relative z-[1001]">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-text-dark">Insira sua localização</h3>
                <button id="btnFecharModalEndereco" class="text-text-light hover:text-text-dark">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <p class="text-sm text-text-light mb-4">
                Você pode inserir as coordenadas diretamente ou usar um endereço para encontrar pontos de coleta próximos.
            </p>
            <div class="mb-4">
                <label class="block text-text-dark text-sm font-medium mb-2" for="endereco">
                    Endereço ou referência
                </label>
                <input type="text" id="endereco" placeholder="Ex: Av. Paulista, 1000, São Paulo, SP" 
                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
            </div>
            <div class="mb-4">
                <label class="block text-text-dark text-sm font-medium mb-2" for="cep">
                    CEP
                </label>
                <div class="flex">
                    <input type="text" id="cep" placeholder="Ex: 01310-100" maxlength="9"
                        class="flex-1 border border-gray-300 rounded-l-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                    <button id="btnBuscarCEP" class="bg-secondary hover:bg-secondary-dark text-white px-4 py-2 rounded-r-md transition-colors duration-200">
                        Buscar
                    </button>
                </div>
                <p class="text-xs text-text-light mt-1">Digite o CEP e clique em Buscar para preencher as coordenadas automaticamente</p>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                    <label class="block text-text-dark text-sm font-medium mb-2" for="latitude">
                        Latitude
                    </label>
                    <input type="text" id="latitude" placeholder="Ex: -23.550520" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                </div>
                <div>
                    <label class="block text-text-dark text-sm font-medium mb-2" for="longitude">
                        Longitude
                    </label>
                    <input type="text" id="longitude" placeholder="Ex: -46.633308" 
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                </div>
            </div>
            <div class="flex justify-end">
                <button id="btnConfirmarEndereco" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-md shadow-md transition-colors duration-200">
                    Confirmar Localização
                </button>
            </div>
        </div>
    </div>
    
    <!-- Script do mapa de coleta -->
    <script>
        // Variáveis globais para o mapa
        let map;
        let markers = [];
        let userMarker;
        let popup = null;
        
        // Pontos de coleta do PHP - usado apenas como fallback
        const pontosColetaEstaticos = <?= json_encode($pontosColeta ?? []) ?>;
        let pontosColetaAtuais = []; // Variável para armazenar os pontos atuais carregados
        
        // Calcular a posição central como média dos pontos (posição padrão)
        let defaultLat = -23.550520; // São Paulo por padrão
        let defaultLng = -46.633308;
        
        if (pontosColetaEstaticos && pontosColetaEstaticos.length > 0) {
            let totalLat = 0;
            let totalLng = 0;
            
            pontosColetaEstaticos.forEach(ponto => {
                totalLat += ponto.lat;
                totalLng += ponto.lng;
            });
            
            defaultLat = totalLat / pontosColetaEstaticos.length;
            defaultLng = totalLng / pontosColetaEstaticos.length;
        }
        
        // Inicializar mapa
        function initMap() {
            // Criar mapa com Leaflet
            map = L.map('mapa');
            
            // Adicionar camada do OpenStreetMap
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Inicializar com a visão padrão
            map.setView([defaultLat, defaultLng], 11);
            
            // Configurar eventos
            setupEvents();
            
            console.log("Mapa inicializado. Aguardando localização do usuário para buscar pontos de coleta.");
        }
        
        // Função para limpar todos os marcadores do mapa
        function limparTodosMarcadores() {
            console.log("Limpando todos os marcadores");
            
            // Remover marcador do usuário, se existir
            if (userMarker && map.hasLayer(userMarker)) {
                map.removeLayer(userMarker);
                userMarker = null;
            }
            
            // Remover todos os marcadores de pontos de coleta
            if (markers.length > 0) {
                markers.forEach(marker => {
                    if (map && marker && map.hasLayer(marker)) {
                        map.removeLayer(marker);
                    }
                });
                markers = [];
            }
        }
        
        // Função para adicionar marcadores para pontos de coleta
        function adicionarMarcadoresPontos(pontosProximos) {
            console.log(`Adicionando ${pontosProximos.length} pontos ao mapa`);
            
            // Ícone personalizado para pontos de coleta
            const greenIcon = L.icon({
                iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41],
                className: 'leaflet-marker-green'
            });
            
            // Adicionar estilo para tornar o ícone verde
            const styleElement = document.createElement('style');
            styleElement.textContent = `
                .leaflet-marker-green img {
                    filter: hue-rotate(120deg); /* Transforma ícone azul em verde */
                }
            `;
            document.head.appendChild(styleElement);
            
            // Verificar se pontosProximos é um array
            if (!Array.isArray(pontosProximos)) {
                console.error("pontosProximos não é um array:", pontosProximos);
                pontosProximos = gerarPontosSimulados({lat: defaultLat, lng: defaultLng});
            }

            pontosProximos.forEach((ponto, index) => {
                console.log(`Adicionando ponto: ${ponto.nome} em ${ponto.lat}, ${ponto.lng}`);
                
                // Criar conteúdo do popup
                const contentString = `
                    <div class="marker-info">
                        <div class="marker-title">${ponto.nome}</div>
                        <div class="marker-address">${ponto.endereco}</div>
                        <div class="border-t border-gray-200 my-2"></div>
                        <div class="font-medium text-sm mb-1">Preços por kg:</div>
                        <div class="marker-price">
                            <span>Alumínio:</span>
                            <span class="marker-price-value">R$ ${ponto.materiais.aluminio.toFixed(2).replace('.', ',')}</span>
                        </div>
                        <div class="marker-price">
                            <span>Papel:</span>
                            <span class="marker-price-value">R$ ${ponto.materiais.papel.toFixed(2).replace('.', ',')}</span>
                        </div>
                        <div class="marker-price">
                            <span>Plástico:</span>
                            <span class="marker-price-value">R$ ${ponto.materiais.plastico.toFixed(2).replace('.', ',')}</span>
                        </div>
                        <div class="marker-price">
                            <span>Vidro:</span>
                            <span class="marker-price-value">R$ ${ponto.materiais.vidro.toFixed(2).replace('.', ',')}</span>
                        </div>
                        <div class="mt-2 text-center">
                            <button onclick="alert('Funcionalidade em desenvolvimento')" class="bg-primary text-white text-sm py-1 px-3 rounded font-medium hover:bg-primary-dark">
                                Solicitar Coleta
                            </button>
                        </div>
                    </div>
                `;
                
                try {
                    // Criar marcador e adicionar ao mapa
                    const marker = L.marker([ponto.lat, ponto.lng], {
                        title: ponto.nome,
                        icon: greenIcon
                    }).addTo(map);
                    
                    // Adicionar popup ao marcador
                    marker.bindPopup(contentString);
                    
                    // Armazenar o marcador no array
                    markers.push(marker);
                } catch (e) {
                    console.error(`Erro ao adicionar marcador ${index}:`, e);
                }
            });
            
            console.log(`${markers.length} marcadores adicionados ao mapa`);
            
            // Ajustar a visualização para mostrar todos os pontos e o usuário
            ajustarVistaMapa(pontosProximos);
        }
        
        // Configurar eventos para os botões e modais
        function setupEvents() {
            // Botão de localização automática
            document.getElementById('btnLocalizacao').addEventListener('click', localizarUsuario);
            
            // Botão para abrir modal de endereço manual
            document.getElementById('btnEnderecoManual').addEventListener('click', abrirModalEndereco);
            
            // Botão para fechar modal de endereço
            document.getElementById('btnFecharModalEndereco').addEventListener('click', fecharModalEndereco);
            
            // Botão para confirmar endereço manual
            document.getElementById('btnConfirmarEndereco').addEventListener('click', confirmarEnderecoManual);
            
            // Botão para buscar CEP
            document.getElementById('btnBuscarCEP').addEventListener('click', buscarCEP);
            
            // Formatação automática do campo CEP
            document.getElementById('cep').addEventListener('input', function() {
                let cep = this.value.replace(/\D/g, ''); // Remove caracteres não numéricos
                
                if (cep.length > 5) {
                    cep = cep.substring(0, 5) + '-' + cep.substring(5);
                }
                
                this.value = cep;
            });
            
            // Permitir buscar CEP com a tecla Enter
            document.getElementById('cep').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    buscarCEP();
                }
            });
        }

        // Função para localizar o usuário
        function localizarUsuario() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const pos = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                        };
                        
                        // Definir a localização do usuário
                        definirLocalizacaoUsuario(pos);
                    },
                    (error) => {
                        console.error("Erro de geolocalização:", error);
                        let mensagemErro = "Não foi possível obter sua localização. ";
                        
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                mensagemErro += "Você precisa permitir o acesso à sua localização.";
                                break;
                            case error.POSITION_UNAVAILABLE:
                                mensagemErro += "Informação de localização indisponível.";
                                break;
                            case error.TIMEOUT:
                                mensagemErro += "Tempo limite excedido ao obter localização.";
                                break;
                            case error.UNKNOWN_ERROR:
                                mensagemErro += "Erro desconhecido ao obter localização.";
                                break;
                        }
                        
                        alert(mensagemErro + "\n\nVocê pode inserir sua localização manualmente.");
                        
                        // Abrir modal para inserção manual de endereço
                        abrirModalEndereco();
                    },
                    { 
                        enableHighAccuracy: true,  // Solicitar a melhor precisão possível
                        timeout: 10000,            // Tempo limite em milissegundos
                        maximumAge: 0              // Não usar cache de localização
                    }
                );
            } else {
                alert("Seu navegador não suporta geolocalização. Por favor, insira sua localização manualmente.");
                abrirModalEndereco();
            }
        }

        // Definir a localização do usuário
        function definirLocalizacaoUsuario(pos) {
            console.log("Definindo localização do usuário para:", pos);
            
            // Remover todos os marcadores existentes do mapa
            limparTodosMarcadores();
            
            // Ícone personalizado para o usuário
            const blueIcon = L.icon({
                iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            });
            
            // Criar novo marcador para o usuário
            userMarker = L.marker([pos.lat, pos.lng], {
                title: "Sua localização",
                icon: blueIcon
            }).addTo(map);
            
            userMarker.bindPopup("<b>Sua localização</b>").openPopup();
            
            // Centralizar o mapa na posição do usuário
            map.setView([pos.lat, pos.lng], 13);
            
            // Mostrar carregando nos cards enquanto busca os pontos
            document.querySelector('.grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3.gap-6.mb-8').innerHTML = `
                <div class="col-span-full p-6 bg-white rounded-xl shadow-md text-center">
                    <p class="text-text-light mb-2">Buscando pontos de coleta próximos...</p>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="bg-primary h-2.5 rounded-full w-3/4 animate-pulse"></div>
                    </div>
                </div>
            `;
            
            // Gerar pontos simulados ao redor da localização do usuário
            const pontosProximos = gerarPontosSimulados(pos);
                
            // Armazenar os pontos encontrados na variável global para uso posterior
            pontosColetaAtuais = pontosProximos;
            
            // Agora adicionar os marcadores para esses pontos próximos
            adicionarMarcadoresPontos(pontosProximos);
            
            // Calcular distâncias para cada ponto de coleta
            calcularDistancias(pos, pontosProximos);
        }

        // Função para gerar pontos simulados para demonstração
        function gerarPontosSimulados(posicaoUsuario) {
            // Valores médios de preços para usar nos pontos simulados
            const precosMedias = {
                aluminio: 18.75,
                papel: 2.35,
                plastico: 3.75,
                vidro: 1.25
            };
            
            // Gerar alguns pontos simulados
            const pontosSimulados = [];
            const nomesPontos = [
                "EcoPonto Central", 
                "Reciclagem Verde", 
                "Cooperativa Recicle Já", 
                "Reciclagem Sustentável", 
                "EcoCoop do Bairro", 
                "Ponto Verde Reciclagem"
            ];
            
            // Gerar pontos dentro de um raio aleatório
            for (let i = 0; i < 6; i++) {
                // Criar uma variação aleatória em torno da localização do usuário
                // para simular pontos de coleta espalhados ao redor
                const latOffset = (Math.random() - 0.5) * 0.05; // ±0.025 graus (~2-3km)
                const lngOffset = (Math.random() - 0.5) * 0.05;
                
                // Criar uma variação de preço para cada material
                // Simular variação de até 10% para mais ou para menos
                const variacao = () => 1 + (Math.random() * 0.2 - 0.1); // -10% a +10%
                
                // Para simular alguns preços não disponíveis que usarão a média
                // Vamos aleatoriamente marcar alguns preços como "médios"
                const statusPrecos = {
                    aluminio: Math.random() > 0.3, // 70% chance de ter preço real
                    papel: Math.random() > 0.3,
                    plastico: Math.random() > 0.3,
                    vidro: Math.random() > 0.3
                };
                
                // Se o status do preço for false, usamos o preço médio
                const materiais = {
                    aluminio: statusPrecos.aluminio ? precosMedias.aluminio * variacao() : precosMedias.aluminio,
                    papel: statusPrecos.papel ? precosMedias.papel * variacao() : precosMedias.papel,
                    plastico: statusPrecos.plastico ? precosMedias.plastico * variacao() : precosMedias.plastico,
                    vidro: statusPrecos.vidro ? precosMedias.vidro * variacao() : precosMedias.vidro
                };
                
                // Registrar quais preços são médios
                const precosMedios = {
                    aluminio: !statusPrecos.aluminio,
                    papel: !statusPrecos.papel,
                    plastico: !statusPrecos.plastico,
                    vidro: !statusPrecos.vidro
                };
                
                // Calcular a distância real para este ponto
                const lat = posicaoUsuario.lat + latOffset;
                const lng = posicaoUsuario.lng + lngOffset;
                const distancia = calcularDistanciaHaversine(
                    posicaoUsuario.lat, 
                    posicaoUsuario.lng, 
                    lat, 
                    lng
                );
                
                pontosSimulados.push({
                    id: i + 1,
                    nome: nomesPontos[i],
                    endereco: `Localidade próxima ${i + 1} (${distancia.toFixed(2)} km)`,
                    lat: lat,
                    lng: lng,
                    materiais: materiais,
                    precosMedios: precosMedios,
                    distancia: distancia
                });
            }
            
            // Ordenar por distância
            pontosSimulados.sort((a, b) => a.distancia - b.distancia);
            
            return pontosSimulados;
        }

        // Calcular distâncias entre a posição do usuário e os pontos de coleta
        function calcularDistancias(posicaoUsuario, pontosProximos) {
            // Array para armazenar distâncias
            const distancias = [];
            
            // Calcular distância para cada ponto usando a fórmula de Haversine
            pontosProximos.forEach((ponto, index) => {
                const distancia = calcularDistanciaHaversine(
                    posicaoUsuario.lat,
                    posicaoUsuario.lng,
                    ponto.lat,
                    ponto.lng
                );
                
                distancias.push({
                    index: index,
                    distancia: distancia,
                    ponto: ponto
                });
            });
            
            // Ordenar por distância
            distancias.sort((a, b) => a.distancia - b.distancia);
            
            // Atualizar as cards com informações de distância
            atualizarCardsComDistancia(distancias);
        }

        // Lógica para inicializar o mapa quando estiver visível
        document.addEventListener('DOMContentLoaded', function() {
            // Adicionar código do mapa aqui
            if (document.getElementById('mapa')) {
                initMap();
            }
        });
    </script>

    <!-- Seção do Mapa de Coleta com Registro de Vendas -->
    <div class="container mx-auto border-t-2 border-gray-200 pt-8 px-4 mb-8">
        <h2 class="text-2xl font-bold text-text-dark mb-6 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Registro e Vendas de Materiais Recicláveis
        </h2>
        <p class="text-text-light mb-6">Registre a venda de materiais recicláveis.</p>
    </div>

    <!-- Sistema de Registro de Venda de Materiais -->
    <div class="container mx-auto px-4 py-4 mb-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Formulário de Registro de Materiais -->
            <div class="bg-white rounded-xl shadow-md p-6 lg:col-span-1">
                <h3 class="text-lg font-semibold text-text-dark mb-4">Registrar Venda de Material</h3>
                <form id="formRegistroMaterial" class="space-y-4">
                    <!-- Tipo de Material -->
                    <div>
                        <label for="tipoMaterial" class="block text-sm font-medium text-text-dark mb-1">Tipo de Material</label>
                        <select id="tipoMaterial" name="tipoMaterial" class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:outline-none focus:ring-primary focus:border-primary">
                            <option value="">Selecione o material</option>
                            <option value="aluminio" data-preco="<?= $mediaPrecos['aluminio'] ?? '18,75' ?>">Alumínio</option>
                            <option value="papel" data-preco="<?= $mediaPrecos['papel'] ?? '2,35' ?>">Papel</option>
                            <option value="plastico" data-preco="<?= $mediaPrecos['plastico'] ?? '3,75' ?>">Plástico</option>
                            <option value="vidro" data-preco="<?= $mediaPrecos['vidro'] ?? '1,25' ?>">Vidro</option>
                        </select>
                    </div>
                    
                    <!-- Quantidade -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="quantidade" class="block text-sm font-medium text-text-dark mb-1">Quantidade</label>
                            <input type="number" id="quantidade" name="quantidade" min="0" step="0.01" placeholder="0,00" class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:outline-none focus:ring-primary focus:border-primary">
                        </div>
                        <div>
                            <label for="unidade" class="block text-sm font-medium text-text-dark mb-1">Unidade</label>
                            <select id="unidade" name="unidade" class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:outline-none focus:ring-primary focus:border-primary">
                                <option value="kg">Quilogramas (kg)</option>
                                <option value="g">Gramas (g)</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Preço -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="precoUnitario" class="block text-sm font-medium text-text-dark mb-1">Preço por kg (R$)</label>
                            <input type="text" id="precoUnitario" name="precoUnitario" placeholder="0,00" class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:outline-none focus:ring-primary focus:border-primary">
                        </div>
                        <div>
                            <label for="valorTotal" class="block text-sm font-medium text-text-dark mb-1">Valor Total (R$)</label>
                            <input type="text" id="valorTotal" name="valorTotal" placeholder="0,00" readonly class="w-full bg-gray-50 border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:outline-none focus:ring-primary focus:border-primary">
                        </div>
                    </div>
                    
                    <!-- Método de Pagamento -->
                    <div>
                        <label for="metodoPagamento" class="block text-sm font-medium text-text-dark mb-1">Método de Pagamento</label>
                        <select id="metodoPagamento" name="metodoPagamento" class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:outline-none focus:ring-primary focus:border-primary">
                            <option value="dinheiro">Dinheiro</option>
                            <option value="cartao_credito">Cartão de Crédito</option>
                            <option value="cartao_debito">Cartão de Débito</option>
                            <option value="pix">PIX</option>
                        </select>
                    </div>
                    
                    <!-- Observações -->
                    <div>
                        <label for="observacao" class="block text-sm font-medium text-text-dark mb-1">Observações</label>
                        <textarea id="observacao" name="observacao" rows="2" placeholder="Informações adicionais sobre a venda" class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:outline-none focus:ring-primary focus:border-primary"></textarea>
                    </div>
                    
                    <!-- Botão para registrar -->
                    <div class="pt-2">
                        <button type="submit" id="btnRegistrarVenda" class="w-full bg-primary hover:bg-primary-dark text-white font-medium py-2 px-4 rounded-md shadow transition-colors duration-200 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Registrar Venda
                        </button>
                    </div>
                </form>
                
                <!-- Feedback do registro -->
                <div id="feedbackRegistro" class="mt-4 hidden">
                    <div class="p-3 rounded-md"></div>
                </div>
            </div>
            
            <!-- Mapa -->
            <div class="lg:col-span-2">
                <!-- Lista de Vendas Recentes -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-text-dark mb-4">Vendas de Material Recentes</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data/Hora</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Material</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantidade</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pagamento</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="tabelaVendasMaterial">
                                <?php
                                // Buscar vendas de material no banco de dados
                                $vendasMaterial = [];
                                try {
                                    $vendasMaterial = $db->read(
                                        "tb_movimentos_caixa", 
                                        ["*"], 
                                        "tipo = 'venda' AND observacao LIKE '%Material reciclável%'", 
                                        "data_hora DESC",
                                    );
                                } catch (Exception $e) {
                                    error_log("Erro ao buscar vendas de material: " . $e->getMessage());
                                }
                                
                                if (empty($vendasMaterial)): 
                                ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        Nenhuma venda de material registrada ainda
                                    </td>
                                </tr>
                                <?php else:
                                    foreach ($vendasMaterial as $venda):
                                        // Extrair o tipo de material da observação
                                        $tipoMaterial = "";
                                        $observacao = $venda['observacao'];
                                        
                                        if (strpos($observacao, 'Alumínio') !== false) {
                                            $tipoMaterial = "Alumínio";
                                        } elseif (strpos($observacao, 'Papel') !== false) {
                                            $tipoMaterial = "Papel";
                                        } elseif (strpos($observacao, 'Plástico') !== false) {
                                            $tipoMaterial = "Plástico";
                                        } elseif (strpos($observacao, 'Vidro') !== false) {
                                            $tipoMaterial = "Vidro";
                                        } else {
                                            $tipoMaterial = "Material reciclável";
                                        }
                                        
                                        // Extrair a quantidade da observação
                                        preg_match('/(\d+\.?\d*)\s*(kg|g)/i', $observacao, $matches);
                                        $quantidade = !empty($matches) ? $matches[1] . ' ' . $matches[2] : "N/A";
                                ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('d/m/Y H:i', strtotime($venda['data_hora'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= htmlspecialchars($tipoMaterial) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($quantidade) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                        R$ <?= number_format($venda['valor'], 2, ',', '.') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php
                                        $metodos = [
                                            'dinheiro' => 'Dinheiro',
                                            'cartao_credito' => 'Cartão de Crédito',
                                            'cartao_debito' => 'Cartão de Débito',
                                            'pix' => 'PIX'
                                        ];
                                        echo $metodos[$venda['metodo_pagamento']] ?? $venda['metodo_pagamento'];
                                        ?>
                                    </td>
                                </tr>
                                <?php 
                                    endforeach;
                                endif; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmação de registro -->
    <div id="registroConfirmadoModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[1000] p-4">
        <div class="bg-white rounded-lg p-6 sm:p-8 max-w-md w-full mx-auto relative z-[1001]">
            <div class="text-center mb-6">
                <svg class="h-16 w-16 text-success mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <h3 class="text-xl font-bold text-text-dark">Venda Registrada com Sucesso!</h3>
                <p class="text-text-light mt-2" id="registroConfirmadoMensagem"></p>
            </div>
            <div class="mt-6">
                <button id="btnFecharConfirmacao" class="w-full bg-primary hover:bg-primary-dark text-white font-medium py-2 px-4 rounded-md shadow transition-colors duration-200">
                    Fechar
                </button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar o sistema de registro de material
        inicializarRegistroMaterial();
    });

    function inicializarRegistroMaterial() {
        const formRegistro = document.getElementById('formRegistroMaterial');
        const tipoMaterialSelect = document.getElementById('tipoMaterial');
        const quantidadeInput = document.getElementById('quantidade');
        const unidadeSelect = document.getElementById('unidade');
        const precoUnitarioInput = document.getElementById('precoUnitario');
        const valorTotalInput = document.getElementById('valorTotal');
        const metodoPagamentoSelect = document.getElementById('metodoPagamento');
        const feedbackRegistro = document.getElementById('feedbackRegistro');
        
        // Preencher preço unitário ao selecionar material
        tipoMaterialSelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            let preco = option.getAttribute('data-preco');
            
            if (preco) {
                // Substituir vírgula por ponto para cálculos
                preco = preco.replace(',', '.');
                precoUnitarioInput.value = preco;
                
                // Atualizar valor total se quantidade já estiver preenchida
                calcularValorTotal();
            } else {
                precoUnitarioInput.value = '';
                valorTotalInput.value = '';
            }
        });
        
        // Atualizar valor total ao alterar quantidade, unidade ou preço
        quantidadeInput.addEventListener('input', calcularValorTotal);
        unidadeSelect.addEventListener('change', calcularValorTotal);
        precoUnitarioInput.addEventListener('input', calcularValorTotal);
        
        // Formatar o campo de preço para usar vírgula como separador decimal
        precoUnitarioInput.addEventListener('blur', function() {
            if (this.value) {
                // Substituir ponto por vírgula para exibição
                this.value = parseFloat(this.value.replace(',', '.')).toFixed(2).replace('.', ',');
            }
        });
        
        // Submeter o formulário
        formRegistro.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validar formulário
            if (!validarFormulario()) {
                return;
            }
            
            // Preparar dados para envio
            const tipoMaterial = tipoMaterialSelect.options[tipoMaterialSelect.selectedIndex].text;
            const quantidade = parseFloat(quantidadeInput.value.replace(',', '.'));
            const unidade = unidadeSelect.value;
            const precoUnitario = parseFloat(precoUnitarioInput.value.replace(',', '.'));
            const valorTotal = parseFloat(valorTotalInput.value.replace(',', '.'));
            const metodoPagamento = metodoPagamentoSelect.value;
            const observacao = document.getElementById('observacao').value;
            
            // Converter para kg se a unidade for g
            const quantidadeKg = unidade === 'g' ? quantidade / 1000 : quantidade;
            
            // Construir objeto de dados para envio
            const dadosVenda = {
                tipo_material: tipoMaterialSelect.value,
                quantidade: quantidadeKg,
                unidade_original: unidade,
                preco_unitario: precoUnitario,
                valor_total: valorTotal,
                metodo_pagamento: metodoPagamento,
                observacao: observacao
            };
            
            // Registrar venda no sistema
            registrarVendaMaterial(dadosVenda, tipoMaterial);
        });
        
        function calcularValorTotal() {
            const quantidade = parseFloat(quantidadeInput.value.replace(',', '.')) || 0;
            const unidade = unidadeSelect.value;
            const precoUnitario = parseFloat(precoUnitarioInput.value.replace(',', '.')) || 0;
            
            // Converter para kg se a unidade for g
            const quantidadeKg = unidade === 'g' ? quantidade / 1000 : quantidade;
            
            // Calcular valor total
            const valorTotal = quantidadeKg * precoUnitario;
            
            // Atualizar campo de valor total
            valorTotalInput.value = valorTotal.toFixed(2).replace('.', ',');
        }
        
        function validarFormulario() {
            const tipoMaterial = tipoMaterialSelect.value;
            const quantidade = quantidadeInput.value;
            const precoUnitario = precoUnitarioInput.value;
            
            // Verificar se os campos obrigatórios estão preenchidos
            if (!tipoMaterial) {
                mostrarFeedback('Selecione o tipo de material', 'erro');
                return false;
            }
            
            if (!quantidade || parseFloat(quantidade.replace(',', '.')) <= 0) {
                mostrarFeedback('Informe uma quantidade válida', 'erro');
                return false;
            }
            
            if (!precoUnitario || parseFloat(precoUnitario.replace(',', '.')) <= 0) {
                mostrarFeedback('Informe um preço unitário válido', 'erro');
                return false;
            }
            
            return true;
        }
        
        function mostrarFeedback(mensagem, tipo) {
            feedbackRegistro.classList.remove('hidden');
            const feedbackDiv = feedbackRegistro.querySelector('div');
            
            // Definir cores com base no tipo de feedback
            if (tipo === 'erro') {
                feedbackDiv.className = 'p-3 rounded-md bg-danger/10 text-danger-dark';
            } else if (tipo === 'sucesso') {
                feedbackDiv.className = 'p-3 rounded-md bg-success/10 text-success-dark';
            } else {
                feedbackDiv.className = 'p-3 rounded-md bg-blue-50 text-blue-700';
            }
            
            feedbackDiv.textContent = mensagem;
            
            // Esconder o feedback após 5 segundos
            setTimeout(() => {
                feedbackRegistro.classList.add('hidden');
            }, 5000);
        }
        
        function registrarVendaMaterial(dadosVenda, tipoMaterial) {
            // Mostrar indicador de carregamento
            const btnRegistrar = document.getElementById('btnRegistrarVenda');
            const btnTextoOriginal = btnRegistrar.innerHTML;
            btnRegistrar.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processando...';
            btnRegistrar.disabled = true;
            
            // Registrar evento para debug
            console.log('Iniciando registro de venda de material:', dadosVenda);
            
            // Preparar observação para o registro no caixa
            const observacaoCompleta = `Venda de Material reciclável: ${tipoMaterial} - ${dadosVenda.quantidade.toFixed(3)} kg ${dadosVenda.observacao ? '- ' + dadosVenda.observacao : ''}`;
            
            // Preparar dados para o endpoint
            const dadosParaAPI = {
                tipo: 'venda',
                valor: dadosVenda.valor_total,
                metodo_pagamento: dadosVenda.metodo_pagamento,
                observacao: observacaoCompleta
            };
            
            console.log('Enviando dados para API:', dadosParaAPI);
            
            // Fazer a requisição para registrar a venda no caixa
            fetch('/api/registrar-venda-material', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(dadosParaAPI)
            })
            .then(response => {
                console.log('Resposta recebida:', response);
                // Verificar se a resposta está ok antes de tentar parsear como JSON
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Resposta de erro:', text);
                        throw new Error('Erro na requisição: ' + response.status + ' - ' + text);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Dados recebidos:', data);
                if (data.success) {
                    // Limpar formulário
                    formRegistro.reset();
                    valorTotalInput.value = '';
                    
                    // Mostrar feedback de sucesso
                    mostrarFeedback('Venda registrada com sucesso!', 'sucesso');
                    
                    // Mostrar modal de confirmação
                    const modalConfirmacao = document.getElementById('registroConfirmadoModal');
                    const mensagemConfirmacao = document.getElementById('registroConfirmadoMensagem');
                    
                    mensagemConfirmacao.textContent = `Venda de ${tipoMaterial} registrada no valor de R$ ${dadosVenda.valor_total.toFixed(2).replace('.', ',')}`;
                    
                    modalConfirmacao.classList.remove('hidden');
                    modalConfirmacao.classList.add('flex');
                    
                    // Configurar botão de fechar
                    document.getElementById('btnFecharConfirmacao').onclick = function() {
                        modalConfirmacao.classList.remove('flex');
                        modalConfirmacao.classList.add('hidden');
                        
                        // Recarregar a página para atualizar os dados de caixa
                        window.location.reload();
                    };
                } else {
                    mostrarFeedback(data.message || 'Erro ao registrar a venda', 'erro');
                }
            })
            .catch(error => {
                console.error('Erro durante a requisição:', error);
                mostrarFeedback('Erro ao comunicar com o servidor: ' + error.message, 'erro');
            })
            .finally(() => {
                // Restaurar botão
                btnRegistrar.innerHTML = btnTextoOriginal;
                btnRegistrar.disabled = false;
            });
        }
    }
    </script>
</body>

</html> 