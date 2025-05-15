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
        $valorInicial = $_POST['valor_inicial'] ?? 0;
        
        $dados = [
            'data_abertura' => date('Y-m-d H:i:s'),
            'usuario_abertura' => $usuario,
            'valor_inicial' => $valorInicial,
            'status' => 'aberto'
        ];
        
        $idFechamento = $this->db->create("tb_fechamentos_caixa", $dados);
        
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
            
            $this->db->create("tb_movimentos_caixa", $dadosMovimento);
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
        $valor = $_POST['valor'] ?? 0;
        $motivo = $_POST['motivo'] ?? '';
        
        $dadosMovimento = [
            'id_fechamento' => $caixaAtual['id'],
            'tipo' => 'sangria',
            'valor' => $valor,
            'metodo_pagamento' => 'dinheiro',
            'observacao' => $motivo,
            'data_hora' => date('Y-m-d H:i:s'),
            'usuario' => $usuario
        ];
        
        $this->db->create("tb_movimentos_caixa", $dadosMovimento);
        
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
        $valor = $_POST['valor'] ?? 0;
        $descricao = $_POST['descricao'] ?? '';
        
        $dadosMovimento = [
            'id_fechamento' => $caixaAtual['id'],
            'tipo' => 'suprimento',
            'valor' => $valor,
            'metodo_pagamento' => 'dinheiro',
            'observacao' => $descricao,
            'data_hora' => date('Y-m-d H:i:s'),
            'usuario' => $usuario
        ];
        
        $this->db->create("tb_movimentos_caixa", $dadosMovimento);
        
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
        $valorDinheiro = $_POST['valorDinheiro'] ?? 0;
        $valorCartoes = $_POST['valorCartoes'] ?? 0;
        $observacao = $_POST['observacao'] ?? '';
        
        // Calcular valor esperado
        $movimentos = $this->db->read(
            "tb_movimentos_caixa", 
            ["*"], 
            "id_fechamento = " . $caixaAtual['id']
        );
        
        $valorEsperado = $caixaAtual['valor_inicial'];
        
        foreach ($movimentos as $movimento) {
            if ($movimento['tipo'] == 'venda' && $movimento['metodo_pagamento'] == 'dinheiro') {
                $valorEsperado += $movimento['valor'];
            } else if ($movimento['tipo'] == 'sangria') {
                $valorEsperado -= $movimento['valor'];
            } else if ($movimento['tipo'] == 'suprimento') {
                $valorEsperado += $movimento['valor'];
            } else if ($movimento['tipo'] == 'cancelamento' && $movimento['metodo_pagamento'] == 'dinheiro') {
                $valorEsperado -= $movimento['valor'];
            }
        }
        
        $valorFinal = $valorDinheiro;
        $diferenca = $valorFinal - $valorEsperado;
        
        // Atualizar o registro de fechamento
        $dadosAtualizacao = [
            'data_fechamento' => date('Y-m-d H:i:s'),
            'usuario_fechamento' => $usuario,
            'valor_final' => $valorFinal,
            'valor_esperado' => $valorEsperado,
            'diferenca' => $diferenca,
            'observacao' => $observacao,
            'status' => 'fechado'
        ];
        
        $this->db->update(
            "tb_fechamentos_caixa", 
            $dadosAtualizacao, 
            "id = " . $caixaAtual['id']
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Caixa fechado com sucesso!',
            'id_fechamento' => $caixaAtual['id']
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