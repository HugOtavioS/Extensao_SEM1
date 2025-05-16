<?php
require_once 'App/Models/Config/env.php';
require_once 'App/Models/Config/Database/Database.php';

// Initialize the database connection with proper namespaces
$env = new Config\env();
$db = new Config\Database\Database($env);

// Connect to establish PDO connection first
$pdo = $db->connect();

try {
    echo "Iniciando migração de pedidos entregues e pagos para o fechamento de caixa...\n";
    
    // 1. Verificar se existem fechamentos de caixa fechados
    $fechamentosFechados = $db->read("tb_fechamentos_caixa", ["*"], "status = 'fechado'");
    
    if (empty($fechamentosFechados)) {
        echo "Não foram encontrados fechamentos de caixa para associar aos pedidos.\n";
        echo "Execute este script após fechar pelo menos um caixa.\n";
        exit;
    }
    
    // Usar o fechamento de caixa mais recente
    usort($fechamentosFechados, function($a, $b) {
        return strtotime($b['data_fechamento']) - strtotime($a['data_fechamento']);
    });
    
    $fechamentoRecente = $fechamentosFechados[0];
    $idFechamento = $fechamentoRecente['id'];
    
    echo "Usando fechamento de caixa #{$idFechamento} (fechado em {$fechamentoRecente['data_fechamento']}).\n";
    
    // 2. Atualizar pedidos entregues e pagos que ainda não estão associados a um fechamento
    $resultadoUpdate = $db->update(
        ["fechamento_caixa_id" => $idFechamento],
        "tb_pedidos",
        "status = 'entregue' AND pago = 1 AND (fechamento_caixa_id IS NULL OR fechamento_caixa_id = 0)"
    );
    
    if ($resultadoUpdate) {
        // Verificar quantos pedidos foram atualizados
        $pedidosAtualizados = $db->read("tb_pedidos", ["COUNT(*) as total"], "fechamento_caixa_id = $idFechamento");
        $totalPedidos = $pedidosAtualizados[0]['total'] ?? 0;
        
        echo "Migração concluída com sucesso! $totalPedidos pedidos foram associados ao fechamento #$idFechamento.\n";
    } else {
        echo "Não foram encontrados pedidos para atualizar ou houve um erro na atualização.\n";
    }
    
} catch (PDOException $e) {
    echo "Erro durante a migração: " . $e->getMessage() . "\n";
} 