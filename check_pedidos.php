<?php
// Este script verifica a tabela tb_pedidos para diagnosticar problemas com o gráfico de distribuição horária

// Ajustar caminhos de inclusão
$basePath = __DIR__;
require_once $basePath . '/vendor/autoload.php';

use Config\Database\Database;
use Config\env;

try {
    // Inicializar conexão com o banco de dados
    $db = new Database(new env());
    
    // Obter todos os pedidos
    $pedidos = $db->read("tb_pedidos", ["*"]);
    $total = count($pedidos);
    
    echo "Total de pedidos: $total\n\n";
    
    // Verificar estrutura e valores de data_pedido
    echo "Analisando datas e horas dos pedidos:\n";
    echo "------------------------------------\n";
    
    $horasContagem = array_fill(0, 24, 0);
    $problemasEncontrados = 0;
    
    foreach ($pedidos as $index => $pedido) {
        $id = $pedido['id'] ?? 'desconhecido';
        $dataPedido = $pedido['data_pedido'] ?? null;
        
        if (empty($dataPedido)) {
            echo "Pedido #$id: DATA NULA\n";
            $problemasEncontrados++;
            continue;
        }
        
        $timestamp = strtotime($dataPedido);
        if ($timestamp === false) {
            echo "Pedido #$id: DATA INVÁLIDA - '$dataPedido'\n";
            $problemasEncontrados++;
            continue;
        }
        
        $hora = (int)date('H', $timestamp);
        $dataFormatada = date('Y-m-d H:i:s', $timestamp);
        
        // Contar ocorrências por hora
        if ($hora >= 0 && $hora < 24) {
            $horasContagem[$hora]++;
        } else {
            echo "Pedido #$id: HORA INVÁLIDA - $hora\n";
            $problemasEncontrados++;
        }
        
        // Mostrar os primeiros 5 pedidos para análise
        if ($index < 5) {
            echo "Pedido #$id: Data original = '$dataPedido', Formatada = '$dataFormatada', Hora = $hora\n";
        }
    }
    
    echo "\nDistribuição de pedidos por hora:\n";
    echo "--------------------------------\n";
    for ($hora = 0; $hora < 24; $hora++) {
        $horaFormatada = str_pad($hora, 2, '0', STR_PAD_LEFT) . 'h';
        echo "$horaFormatada: {$horasContagem[$hora]} pedidos\n";
    }
    
    $totalContagem = array_sum($horasContagem);
    echo "\nTotal de pedidos com hora válida: $totalContagem\n";
    echo "Problemas encontrados: $problemasEncontrados\n";
    
    // Sugerir solução
    echo "\nSolução recomendada:\n";
    echo "------------------\n";
    if ($totalContagem > 0) {
        echo "Há pedidos com horas válidas. O problema pode estar na exibição do gráfico. Verifique:\n";
        echo "1. Se os dados estão sendo corretamente convertidos para o formato do gráfico\n";
        echo "2. Se o JavaScript está usando corretamente os dados fornecidos\n";
    } else {
        echo "Nenhum pedido tem hora válida. Problemas possíveis:\n";
        echo "1. Os dados no banco podem estar corrompidos ou em formato inválido\n";
        echo "2. Pode ser necessário adicionar valores padrão para data_pedido\n";
        
        // Sugerir correção dos dados
        echo "\nConsidere executar a seguinte SQL para corrigir as datas:\n";
        echo "UPDATE tb_pedidos SET data_pedido = NOW() WHERE data_pedido IS NULL OR data_pedido = '';\n";
    }
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . " (linha " . $e->getLine() . ")\n";
} 