<?php
namespace Controllers;

use Models\Session\Session;
use Config\Database\Database;
use Config\env;

class CaixaController {
    private $db;
    
    public function __construct() {
        $this->db = new Database(new env());
        Session::init();
        
        // Verificar se o usuário está logado
        if (!Session::get("user")) {
            header('Location: /login');
            exit;
        }
    }
    
    // Verificar se há um caixa aberto para o usuário atual
    private function verificarCaixaAberto() {
        $usuario = Session::get("user");
        
        $caixa = $this->db->read(
            "tb_fechamentos_caixa", 
            ["*"], 
            "status = 'aberto' AND usuario_abertura = '$usuario'"
        );
        
        return !empty($caixa) ? $caixa[0] : null;
    }
    
    // Método para retornar o status do caixa
    public function getStatus() {
        $caixaAtual = $this->verificarCaixaAberto();
        
        if (!$caixaAtual) {
            // Não há caixa aberto
            echo json_encode([
                'success' => true,
                'caixa_aberto' => false
            ]);
            return;
        }
        
        // Buscar apenas pedidos com status "entregue" e "pago" que não estão associados a um fechamento
        $pedidos = $this->db->read(
            "tb_pedidos", 
            ["valor_total"], 
            "status = 'entregue' AND pago = 1 AND (fechamento_caixa_id IS NULL OR fechamento_caixa_id = 0)"
        );
        
        // Calcular valor total de vendas
        $valorTotalVendas = 0;
        foreach ($pedidos as $pedido) {
            $valorTotalVendas += floatval($pedido['valor_total']);
        }
        
        // Calcular resumo do caixa
        $movimentos = $this->db->read(
            "tb_movimentos_caixa", 
            ["*"], 
            "id_fechamento = " . $caixaAtual['id']
        );
        
        // Inicializar resumo
        $resumo = [
            'vendas' => [
                'dinheiro' => $valorTotalVendas, // Usar o total calculado dos pedidos
                'cartao_credito' => 0,
                'cartao_debito' => 0,
                'pix' => 0,
                'total' => $valorTotalVendas // Usar o total calculado dos pedidos
            ],
            'movimentos' => [
                'suprimentos' => 0,
                'sangrias' => 0,
                'cancelamentos' => 0,
                'vendas_materiais' => 0 // Novo campo para vendas de materiais
            ]
        ];
        
        // Processar movimentos
        foreach ($movimentos as $movimento) {
            if ($movimento['tipo'] == 'suprimento') {
                $resumo['movimentos']['suprimentos'] += floatval($movimento['valor']);
            } else if ($movimento['tipo'] == 'sangria') {
                $resumo['movimentos']['sangrias'] += floatval($movimento['valor']);
            } else if ($movimento['tipo'] == 'cancelamento') {
                $resumo['movimentos']['cancelamentos'] += floatval($movimento['valor']);
            } else if ($movimento['tipo'] == 'venda' && strpos($movimento['observacao'], 'Material reciclável') !== false) {
                // Somar vendas de materiais recicláveis
                $resumo['movimentos']['vendas_materiais'] += floatval($movimento['valor']);
            }
        }
        
        // Retornar resposta com dados do caixa e resumo
        echo json_encode([
            'success' => true,
            'caixa_aberto' => true,
            'dados' => [
                'id' => $caixaAtual['id'],
                'data_abertura' => $caixaAtual['data_abertura'],
                'usuario_abertura' => $caixaAtual['usuario_abertura'],
                'valor_inicial' => $caixaAtual['valor_inicial'],
                'resumo' => $resumo
            ]
        ]);
    }
    
    // Método para retornar os dados detalhados do caixa
    public function getDados() {
        $caixaAtual = $this->verificarCaixaAberto();
        
        if (!$caixaAtual) {
            // Não há caixa aberto
            echo json_encode([
                'success' => false,
                'message' => 'Não há caixa aberto para este usuário.'
            ]);
            return;
        }
        
        // Buscar apenas pedidos com status "entregue" e "pago" que não estão associados a um fechamento
        $pedidos = $this->db->read(
            "tb_pedidos", 
            ["*"], 
            "status = 'entregue' AND pago = 1 AND (fechamento_caixa_id IS NULL OR fechamento_caixa_id = 0)"
        );
        
        // Calcular valor total de vendas
        $valorTotalVendas = 0;
        foreach ($pedidos as $pedido) {
            $valorTotalVendas += floatval($pedido['valor_total']);
        }
        
        // Calcular resumo do caixa
        $movimentos = $this->db->read(
            "tb_movimentos_caixa", 
            ["*"], 
            "id_fechamento = " . $caixaAtual['id']
        );
        
        // Inicializar resumo
        $resumo = [
            'vendas' => [
                'dinheiro' => $valorTotalVendas, // Usar o total calculado dos pedidos
                'cartao_credito' => 0,
                'cartao_debito' => 0,
                'pix' => 0,
                'total' => $valorTotalVendas // Usar o total calculado dos pedidos
            ],
            'movimentos' => [
                'suprimentos' => 0,
                'sangrias' => 0,
                'cancelamentos' => 0,
                'vendas_materiais' => 0 // Novo campo para vendas de materiais
            ]
        ];
        
        // Processar movimentos
        foreach ($movimentos as $movimento) {
            if ($movimento['tipo'] == 'suprimento') {
                $resumo['movimentos']['suprimentos'] += floatval($movimento['valor']);
            } else if ($movimento['tipo'] == 'sangria') {
                $resumo['movimentos']['sangrias'] += floatval($movimento['valor']);
            } else if ($movimento['tipo'] == 'cancelamento') {
                $resumo['movimentos']['cancelamentos'] += floatval($movimento['valor']);
            } else if ($movimento['tipo'] == 'venda' && strpos($movimento['observacao'], 'Material reciclável') !== false) {
                // Somar vendas de materiais recicláveis
                $resumo['movimentos']['vendas_materiais'] += floatval($movimento['valor']);
            }
        }
        
        // Agrupar pedidos por dia
        $pedidosAgrupados = [];
        foreach ($pedidos as $pedido) {
            $dataPedido = date('Y-m-d', strtotime($pedido['data_pedido']));
            
            if (!isset($pedidosAgrupados[$dataPedido])) {
                $pedidosAgrupados[$dataPedido] = [
                    'data' => $dataPedido,
                    'formatted_date' => date('d/m/Y', strtotime($dataPedido)),
                    'pedidos' => []
                ];
            }
            
            $pedidosAgrupados[$dataPedido]['pedidos'][] = $pedido;
        }
        
        // Converter para array indexado para JSON
        $pedidosFormatados = array_values($pedidosAgrupados);
        
        echo json_encode([
            'success' => true,
            'dados' => [
                'id' => $caixaAtual['id'],
                'data_abertura' => $caixaAtual['data_abertura'],
                'usuario_abertura' => $caixaAtual['usuario_abertura'],
                'valor_inicial' => $caixaAtual['valor_inicial'],
                'resumo' => $resumo,
                'pedidos_agrupados' => $pedidosFormatados
            ]
        ]);
    }
    
    // Abrir caixa
    public function abrirCaixa() {
        // Verificar se já existe um caixa aberto
        $caixaAtual = $this->verificarCaixaAberto();
        
        if ($caixaAtual) {
            echo json_encode([
                'success' => false,
                'message' => 'Já existe um caixa aberto para este usuário.'
            ]);
            return;
        }
        
        $usuario = Session::get("user");
        
        // Obter dados do POST como JSON
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, TRUE);
        $valorInicial = $input['valor_inicial'] ?? 0;
        
        $dados = [
            'data_abertura' => date('Y-m-d H:i:s'),
            'usuario_abertura' => $usuario,
            'valor_inicial' => $valorInicial,
            'status' => 'aberto'
        ];
        
        $idFechamento = $this->db->create($dados, "tb_fechamentos_caixa");
        
        // Registrar movimento de suprimento inicial
        if ($valorInicial > 0) {
            $dadosMovimento = [
                'id_fechamento' => $idFechamento,
                'tipo' => 'suprimento',
                'valor' => $valorInicial,
                'metodo_pagamento' => 'dinheiro',
                'observacao' => 'Valor inicial do caixa',
                'data_hora' => date('Y-m-d H:i:s'),
                'usuario' => $usuario
            ];
            
            $this->db->create($dadosMovimento, "tb_movimentos_caixa");
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Caixa aberto com sucesso!',
            'id_fechamento' => $idFechamento
        ]);
    }
    
    // Registrar sangria
    public function registrarSangria() {
        $caixaAtual = $this->verificarCaixaAberto();
        
        if (!$caixaAtual) {
            echo json_encode([
                'success' => false,
                'message' => 'Não há caixa aberto para este usuário.'
            ]);
            return;
        }
        
        $usuario = Session::get("user");
        
        // Obter dados do POST como JSON
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, TRUE);
        $valor = $input['valor'] ?? 0;
        $motivo = $input['motivo'] ?? '';
        
        $dadosMovimento = [
            'id_fechamento' => $caixaAtual['id'],
            'tipo' => 'sangria',
            'valor' => $valor,
            'metodo_pagamento' => 'dinheiro',
            'observacao' => $motivo,
            'data_hora' => date('Y-m-d H:i:s'),
            'usuario' => $usuario
        ];
        
        $this->db->create($dadosMovimento, "tb_movimentos_caixa");
        
        echo json_encode([
            'success' => true,
            'message' => 'Sangria registrada com sucesso!'
        ]);
    }
    
    // Registrar suprimento
    public function registrarSuprimento() {
        $caixaAtual = $this->verificarCaixaAberto();
        
        if (!$caixaAtual) {
            echo json_encode([
                'success' => false,
                'message' => 'Não há caixa aberto para este usuário.'
            ]);
            return;
        }
        
        $usuario = Session::get("user");
        
        // Obter dados do POST como JSON
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, TRUE);
        $valor = $input['valor'] ?? 0;
        $descricao = $input['descricao'] ?? '';
        
        $dadosMovimento = [
            'id_fechamento' => $caixaAtual['id'],
            'tipo' => 'suprimento',
            'valor' => $valor,
            'metodo_pagamento' => 'dinheiro',
            'observacao' => $descricao,
            'data_hora' => date('Y-m-d H:i:s'),
            'usuario' => $usuario
        ];
        
        $this->db->create($dadosMovimento, "tb_movimentos_caixa");
        
        echo json_encode([
            'success' => true,
            'message' => 'Suprimento registrado com sucesso!'
        ]);
    }
    
    // Fechar caixa
    public function fecharCaixa() {
        $caixaAtual = $this->verificarCaixaAberto();
        
        if (!$caixaAtual) {
            echo json_encode([
                'success' => false,
                'message' => 'Não há caixa aberto para este usuário.'
            ]);
            return;
        }
        
        $usuario = Session::get("user");
        
        // Obter dados do POST como JSON
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, TRUE);
        
        $valoresConferencia = $input['valores_conferencia'] ?? [];
        $valorDinheiro = $valoresConferencia['dinheiro'] ?? 0;
        
        $observacoes = $input['observacoes'] ?? '';
        
        // Atualizar movimentos se necessário
        if (isset($input['movimentos'])) {
            $movimentos = $input['movimentos'];
            
            // Atualizar os valores de suprimentos, sangrias e cancelamentos se necessário
            // Esta função poderia atualizar os valores na tabela de movimentos se necessário
        }
        
        // Calcular valor esperado
        $movimentos = $this->db->read(
            "tb_movimentos_caixa", 
            ["*"], 
            "id_fechamento = " . $caixaAtual['id']
        );
        
        $valorEsperado = $caixaAtual['valor_inicial'];
        
        foreach ($movimentos as $movimento) {
            if ($movimento['tipo'] == 'venda') {
                if ($movimento['metodo_pagamento'] == 'dinheiro') {
                    // Incluir todas as vendas em dinheiro (pedidos e materiais)
                    $valorEsperado += floatval($movimento['valor']);
                }
            } else if ($movimento['tipo'] == 'sangria') {
                $valorEsperado -= floatval($movimento['valor']);
            } else if ($movimento['tipo'] == 'suprimento') {
                $valorEsperado += floatval($movimento['valor']);
            } else if ($movimento['tipo'] == 'cancelamento' && $movimento['metodo_pagamento'] == 'dinheiro') {
                $valorEsperado -= floatval($movimento['valor']);
            }
        }
        
        $valorFinal = $valorDinheiro;
        $diferenca = $valorFinal - $valorEsperado;
        
        // Obter pedidos não entregues ou não pagos
        $pedidosPendentes = $this->db->read(
            "tb_pedidos", 
            ["*"], 
            "status != 'entregue' OR pago = 0"
        );
        
        // Agrupar pedidos pendentes por dia
        $pedidosPendentesAgrupados = [];
        foreach ($pedidosPendentes as $pedido) {
            // Extrair data do pedido
            $dataPedido = isset($pedido['data_hora']) ? $pedido['data_hora'] : $pedido['created_at'];
            $dia = date('Y-m-d', strtotime($dataPedido));
            
            if (!isset($pedidosPendentesAgrupados[$dia])) {
                $pedidosPendentesAgrupados[$dia] = [
                    'data' => $dia,
                    'formatted_date' => date('d/m/Y', strtotime($dataPedido)),
                    'pedidos' => []
                ];
            }
            
            $pedidosPendentesAgrupados[$dia]['pedidos'][] = $pedido;
        }
        
        // Converter para array indexado para JSON
        $pedidosPendentesFormatados = array_values($pedidosPendentesAgrupados);
        
        // Atualizar o registro de fechamento
        $dadosAtualizacao = [
            'data_fechamento' => date('Y-m-d H:i:s'),
            'usuario_fechamento' => $usuario,
            'valor_final' => $valorFinal,
            'valor_esperado' => $valorEsperado,
            'diferenca' => $diferenca,
            'observacao' => $observacoes,
            'status' => 'fechado'
        ];
        
        $this->db->update(
            $dadosAtualizacao,
            "tb_fechamentos_caixa", 
            "id = " . $caixaAtual['id']
        );
        
        // NOVO: Marcar todos os pedidos entregues e pagos com o ID do fechamento atual
        // Isso permitirá escondê-los da visualização principal após o fechamento
        $this->db->update(
            ["fechamento_caixa_id" => $caixaAtual['id']],
            "tb_pedidos",
            "status = 'entregue' AND pago = 1 AND (fechamento_caixa_id IS NULL OR fechamento_caixa_id = 0)"
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Caixa fechado com sucesso!',
            'id_fechamento' => $caixaAtual['id'],
            'pedidos_pendentes' => $pedidosPendentesFormatados
        ]);
    }
    
    // Obter resumo do caixa atual
    public function resumoCaixaAtual() {
        $caixaAtual = $this->verificarCaixaAberto();
        
        if (!$caixaAtual) {
            echo json_encode([
                'success' => false,
                'message' => 'Não há caixa aberto para este usuário.'
            ]);
            return;
        }
        
        // Buscar movimentos do caixa
        $movimentos = $this->db->read(
            "tb_movimentos_caixa", 
            ["*"], 
            "id_fechamento = " . $caixaAtual['id']
        );
        
        // Calcular totais por tipo e método de pagamento
        $resumo = [
            'dinheiro' => 0,
            'cartao_credito' => 0,
            'cartao_debito' => 0,
            'pix' => 0,
            'suprimentos' => 0,
            'sangrias' => 0,
            'cancelamentos' => 0
        ];
        
        foreach ($movimentos as $movimento) {
            if ($movimento['tipo'] == 'venda') {
                $metodo = $movimento['metodo_pagamento'];
                if ($metodo == 'dinheiro') {
                    $resumo['dinheiro'] += $movimento['valor'];
                } else if ($metodo == 'cartao_credito') {
                    $resumo['cartao_credito'] += $movimento['valor'];
                } else if ($metodo == 'cartao_debito') {
                    $resumo['cartao_debito'] += $movimento['valor'];
                } else if ($metodo == 'pix') {
                    $resumo['pix'] += $movimento['valor'];
                }
            } else if ($movimento['tipo'] == 'suprimento') {
                $resumo['suprimentos'] += $movimento['valor'];
            } else if ($movimento['tipo'] == 'sangria') {
                $resumo['sangrias'] += $movimento['valor'];
            } else if ($movimento['tipo'] == 'cancelamento') {
                $resumo['cancelamentos'] += $movimento['valor'];
            }
        }
        
        // Calcular valor atual em caixa
        $valorEmCaixa = $caixaAtual['valor_inicial'] + 
                        $resumo['dinheiro'] + 
                        $resumo['suprimentos'] - 
                        $resumo['sangrias'];
        
        // Calcular total em cartões e pix
        $totalCartoes = $resumo['cartao_credito'] + $resumo['cartao_debito'];
        $totalPix = $resumo['pix'];
        
        echo json_encode([
            'success' => true,
            'data' => [
                'caixa' => $caixaAtual,
                'resumo' => $resumo,
                'valor_em_caixa' => $valorEmCaixa,
                'total_cartoes' => $totalCartoes,
                'total_pix' => $totalPix,
                'total_geral' => $valorEmCaixa + $totalCartoes + $totalPix
            ]
        ]);
    }
}