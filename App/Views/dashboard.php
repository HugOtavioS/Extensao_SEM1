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
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: '#FF6B35',
                    'primary-dark': '#E85A2A',
                    secondary: '#2EC4B6',
                    'secondary-dark': '#20AEA1',
                    accent: '#FFBF69',
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
    </script>
</body>

</html> 