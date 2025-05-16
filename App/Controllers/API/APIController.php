<?php
namespace Controllers\API;

use Models\Session\Session;
use Config\Database\Database;
use Config\env;

class APIController {
    private $db;

    public function __construct()
    {
        $this->db = new Database(new env());
    }

    // Método para registrar venda de material reciclável
    public function registrarVendaMaterial()
    {
        Session::init();
        // Verificar se o usuário está autenticado como administrador
        if (!Session::get("user") || strpos(Session::get("user"), "admin") === false) {
            echo json_encode([
                'success' => false,
                'message' => 'Acesso não autorizado. Apenas administradores podem registrar vendas de material.'
            ]);
            return;
        }

        // Obter dados do POST como JSON
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, TRUE);

        // Validar dados
        if (!isset($input['valor']) || !isset($input['metodo_pagamento']) || !isset($input['observacao'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Dados incompletos. Por favor, preencha todos os campos obrigatórios.'
            ]);
            return;
        }

        $valor = floatval($input['valor']);
        $metodoPagamento = $input['metodo_pagamento'];
        $observacao = $input['observacao'];
        $usuario = Session::get("user");

        // Verificar se há um caixa aberto
        $caixaAtual = $this->verificarCaixaAberto();
        
        if (!$caixaAtual) {
            echo json_encode([
                'success' => false,
                'message' => 'Não há caixa aberto para registrar a venda. Por favor, abra o caixa primeiro.'
            ]);
            return;
        }

        // Registrar movimento de venda no caixa
        $dadosMovimento = [
            'id_fechamento' => $caixaAtual['id'],
            'tipo' => 'venda',
            'valor' => $valor,
            'metodo_pagamento' => $metodoPagamento,
            'observacao' => $observacao,
            'data_hora' => date('Y-m-d H:i:s'),
            'usuario' => $usuario
        ];
        
        $idMovimento = $this->db->create($dadosMovimento, "tb_movimentos_caixa");
        
        if ($idMovimento) {
            echo json_encode([
                'success' => true,
                'message' => 'Venda de material registrada com sucesso!',
                'id_movimento' => $idMovimento
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao registrar a venda. Tente novamente.'
            ]);
        }
    }

    // Verificar caixa aberto
    private function verificarCaixaAberto()
    {
        // Buscar o caixa aberto no momento para o usuário atual
        $caixaAtual = $this->db->read(
            "tb_fechamentos_caixa", 
            ["*"], 
            "status = 'aberto'", 
            "id DESC", 
            1
        );
        
        return $caixaAtual ? $caixaAtual[0] : null;
    }
} 