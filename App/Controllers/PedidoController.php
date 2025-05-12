<?php
namespace Controllers;

use Config\Database\Database;
use Config\env;
use Controllers\Interfaces\ControllerInterface;
use Controllers\ViewController;
use Models\Request\Request;
use Models\Session\Session;

class PedidoController implements ControllerInterface {
    private $view;
    private static Request $request;
    private $db;

    public function __construct () {

        $this->db = new Database(new env());
        $this->view = new ViewController();

    }
    
    public function index(...$args) {

        $this->view->load("pedido", ["title" => "Pedido"]);

    }

    public function finalizarPedido(...$args) {

        header('Content-Type: application/json');
        $json = json_encode([
            "success" => true,
            "status" => 200,
            "message" => "Pedido finalizado com sucesso",
        ]);
        echo $json;

    }
    public function pedidoFinalizado(...$args) {

        $this->view->load("pedidoFinalizado", ["title" => "Pedido"]);

    }

    public function create () {

        $data = json_decode(file_get_contents("php://input"), true);

        try {
            // Converter o array de produtos para JSON antes de salvar
            $produtosJSON = json_encode($data["produtos"]);
            
            $this->db->create([
                "mesa" => $data["mesa"],
                "pago" => $data["pago"],
                "produtos" => $produtosJSON, // Agora como string JSON
                "status" => $data["status"],
                "valor_total" => $data["valor_total"],
                "itens" => $data["itens"],
            ], "tb_pedidos");

            $json = json_encode([
                "success" => true,
                "status" => 200,
                "message" => $data,
            ]);
        } catch (\Exception $e) {
            $json = json_encode([
                "success" => false,
                "status" => 500,
                "message" => $e->getMessage(),
            ]);
        }

        header('Content-Type: application/json');
        echo $json;
    }

    /**
     * Recupera os detalhes de um pedido específico
     * Converte o campo produtos de JSON para array
     */
    public function getById() {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            $id = $data['id'] ?? null;

            // Buscar o pedido pelo ID
            $pedido = $this->db->read("tb_pedidos", ["*"], "id = {$id}");
            
            if (empty($pedido)) {
                echo json_encode([
                    "success" => false,
                    "status" => 404,
                    "message" => "Pedido não encontrado"
                ]);
                return;
            }
            
            $pedido = $pedido[0]; // Pegar o primeiro resultado
            
            // Converter o campo produtos de JSON para array
            if (isset($pedido['produtos']) && !empty($pedido['produtos'])) {
                $pedido['produtos'] = json_decode($pedido['produtos'], true);
            } else {
                $pedido['produtos'] = [];
            }
            
            echo json_encode([
                "success" => true,
                "status" => 200,
                "data" => $pedido
            ]);
            
        } catch (\Exception $e) {
            echo json_encode([
                "success" => false,
                "status" => 500,
                "message" => $e->getMessage()
            ]);
        }
    }

    /**
     * Atualiza um pedido existente
     */
    public function update() {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            $id = isset($data['id']) ? intval($data['id']) : null;
            
            if (!$id) {
                echo json_encode([
                    "success" => false,
                    "status" => 400,
                    "message" => "ID do pedido não fornecido"
                ]);
                return;
            }
            
            // Preparar dados para atualização
            $updateData = [];
            
            // Campos que podem ser atualizados - garantindo os tipos corretos
            if (isset($data['mesa'])) {
                $updateData['mesa'] = trim($data['mesa']);
            }
            
            if (isset($data['status'])) {
                // Validar se o status é um dos valores permitidos pelo enum
                $statusPermitidos = ['preparando', 'pronto', 'entregue', 'cancelado'];
                $status = strtolower(trim($data['status']));
                
                if (in_array($status, $statusPermitidos)) {
                    $updateData['status'] = $status;
                } else {
                    throw new \Exception("Status inválido: $status");
                }
            }
            
            if (isset($data['pago'])) {
                // Garantir que pago seja 0 ou 1
                $updateData['pago'] = intval($data['pago']) ? 1 : 0;
            }
            
            if (isset($data['produtos'])) {
                // Verificar se produtos já está em formato JSON
                if (is_string($data['produtos']) && $this->isValidJson($data['produtos'])) {
                    $updateData['produtos'] = $data['produtos'];
                    $produtos = json_decode($data['produtos'], true);
                } else {
                    // Converter array de produtos para JSON
                    $updateData['produtos'] = json_encode($data['produtos']);
                    $produtos = $data['produtos'];
                }
                
                // Atualizar também o número de itens
                $totalItens = 0;
                if (is_array($produtos)) {
                    foreach ($produtos as $quantidade) {
                        $totalItens += intval($quantidade);
                    }
                }
                $updateData['itens'] = $totalItens;
            }
            
            if (isset($data['valor_total'])) {
                // Garantir formato decimal(10,2)
                $updateData['valor_total'] = number_format(floatval($data['valor_total']), 2, '.', '');
            }
            
            if (isset($data['itens']) && !isset($updateData['itens'])) {
                $updateData['itens'] = intval($data['itens']);
            }
            
            // Verificar se há dados para atualizar
            if (empty($updateData)) {
                echo json_encode([
                    "success" => false,
                    "status" => 400,
                    "message" => "Nenhum dado fornecido para atualização"
                ]);
                return;
            }
            
            // Log para debug
            error_log("Atualizando pedido #$id com dados: " . json_encode($updateData));
            
            // Atualizar o pedido no banco de dados
            $result = $this->db->update($updateData, "tb_pedidos", "id = {$id}");
            
            if ($result) {
                echo json_encode([
                    "success" => true,
                    "status" => 200,
                    "message" => "Pedido atualizado com sucesso"
                ]);
            } else {
                throw new \Exception("Falha ao atualizar o pedido no banco de dados");
            }
            
        } catch (\Exception $e) {
            error_log("Erro ao atualizar pedido: " . $e->getMessage());
            echo json_encode([
                "success" => false,
                "status" => 500,
                "message" => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Verifica se uma string é um JSON válido
     */
    private function isValidJson($string) {
        if (!is_string($string)) return false;
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * Exibe o dashboard administrativo com métricas de vendas
     */
    public function dashboard() {
        // Verificar se é administrador
        if (!$this->isAdmin()) {
            header('Location: /');
            exit;
        }
        
        try {
            // Obter dados para métricas
            $metricas = $this->obterMetricas();
            
            // Garantir valores padrão
            if (!isset($metricas['resumo']) || empty($metricas['resumo'])) {
                $metricas['resumo'] = [
                    'totalPedidos' => 0,
                    'totalVendas' => '0,00',
                    'totalVendasNumerico' => 0,
                    'totalItens' => 0,
                    'mediaPorPedido' => '0,00',
                    'mediaItensPorPedido' => 0,
                    'percentualPagos' => 0,
                    'crescimentoDiario' => 0
                ];
            }
            
            // Carregar a view do dashboard
            $this->view->load("dashboard", [
                "title" => "Dashboard Administrativo",
                "metricas" => $metricas
            ]);
        } catch (\Exception $e) {
            // Em caso de erro, ainda mostrar dashboard com dados mínimos
            $this->view->load("dashboard", [
                "title" => "Dashboard Administrativo",
                "metricas" => [
                    'resumo' => [
                        'totalPedidos' => 0,
                        'totalVendas' => '0,00',
                        'totalVendasNumerico' => 0,
                        'totalItens' => 0,
                        'mediaPorPedido' => '0,00',
                        'mediaItensPorPedido' => 0,
                        'percentualPagos' => 0,
                        'crescimentoDiario' => 0
                    ],
                    'vendasPorStatus' => [
                        'preparando' => 0,
                        'pronto' => 0,
                        'entregue' => 0, 
                        'cancelado' => 0
                    ],
                    'vendasPorDia' => [],
                    'produtosMaisVendidos' => [],
                    'mesasMaisUtilizadas' => [],
                    'horariosVendas' => []
                ],
                "error" => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Retorna dados de métricas em formato JSON para o dashboard
     */
    public function getMetricas() {
        // Verificar se é administrador
        if (!$this->isAdmin()) {
            header('Content-Type: application/json');
            echo json_encode([
                "success" => false,
                "message" => "Acesso não autorizado"
            ]);
            exit;
        }
        
        try {
            $metricas = $this->obterMetricas();
            
            // Garantir valores padrão para evitar problemas nos gráficos
            $metricas['resumo'] = $metricas['resumo'] ?? [
                'totalPedidos' => 0,
                'totalVendas' => '0,00',
                'totalVendasNumerico' => 0,
                'totalItens' => 0,
                'mediaPorPedido' => '0,00',
                'mediaItensPorPedido' => 0,
                'percentualPagos' => 0,
                'crescimentoDiario' => 0
            ];
            
            $metricas['vendasPorStatus'] = $metricas['vendasPorStatus'] ?? [
                'preparando' => 0,
                'pronto' => 0,
                'entregue' => 0,
                'cancelado' => 0
            ];
            
            // Garantir que temos dados para os últimos 7 dias
            if (empty($metricas['vendasPorDia'])) {
                $metricas['vendasPorDia'] = [];
                $hoje = date('Y-m-d');
                for ($i = 6; $i >= 0; $i--) {
                    $data = date('Y-m-d', strtotime("-$i days", strtotime($hoje)));
                    $label = date('d/m', strtotime($data));
                    $metricas['vendasPorDia'][] = [
                        'data' => $data,
                        'label' => $label,
                        'valor' => 0
                    ];
                }
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                "success" => true,
                "data" => $metricas
            ]);
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            echo json_encode([
                "success" => false,
                "message" => "Erro ao obter métricas: " . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Calcula as métricas para o dashboard
     * @return array As métricas calculadas
     */
    private function obterMetricas() {
        try {
            // Adicionar logs para depuração
            error_log("Iniciando cálculo de métricas para o dashboard");
            
            // Obter todos os pedidos
            $pedidos = $this->db->read("tb_pedidos", ["*"]);
            error_log("Pedidos obtidos: " . count($pedidos));
            
            // Inicializar métricas
            $totalPedidos = count($pedidos);
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
            $mesasMaisUtilizadas = [];
            $itensPorPedido = [];
            $horariosVendas = [];
            
            // Obter produtos para usar nomes e preços
            $produtos = $this->db->read("tb_produtos", ["*"]);
            error_log("Produtos obtidos: " . count($produtos));
            
            // Mapear produtos por ID para consulta rápida
            $produtosMap = [];
            foreach ($produtos as $produto) {
                $produtosMap[$produto['id']] = $produto;
            }
            
            // Processar pedidos um por um
            foreach ($pedidos as $pedido) {
                // Processar valor total
                $valorPedido = floatval($pedido['valor_total'] ?? 0);
                $valorTotalVendas += $valorPedido;
                
                // Contar itens do pedido
                $itensPedido = intval($pedido['itens'] ?? 0);
                $totalItensVendidos += $itensPedido;
                $itensPorPedido[] = $itensPedido;
                
                // Contar pedidos pagos
                if (isset($pedido['pago']) && $pedido['pago'] == 1) {
                    $pedidosPagos++;
                }
                
                // Agrupar por status
                $status = strtolower($pedido['status'] ?? 'preparando');
                if (isset($vendasPorStatus[$status])) {
                    $vendasPorStatus[$status]++;
                } else {
                    // Em caso de status não reconhecido, colocar em 'preparando'
                    $vendasPorStatus['preparando']++;
                }
                
                // Agrupar vendas por dia
                $dataPedido = isset($pedido['data_pedido']) ? date('Y-m-d', strtotime($pedido['data_pedido'])) : date('Y-m-d');
                if (!isset($vendasPorDia[$dataPedido])) {
                    $vendasPorDia[$dataPedido] = 0;
                }
                $vendasPorDia[$dataPedido] += $valorPedido;
                
                // Agrupar por hora do dia
                $horaPedido = isset($pedido['data_pedido']) ? date('H', strtotime($pedido['data_pedido'])) : date('H');
                if (!isset($horariosVendas[$horaPedido])) {
                    $horariosVendas[$horaPedido] = 0;
                }
                $horariosVendas[$horaPedido]++;
                
                // Contabilizar uso de mesas
                if (isset($pedido['mesa']) && !empty($pedido['mesa'])) {
                    $mesa = $pedido['mesa'];
                    if (!isset($mesasMaisUtilizadas[$mesa])) {
                        $mesasMaisUtilizadas[$mesa] = 0;
                    }
                    $mesasMaisUtilizadas[$mesa]++;
                }
                
                // Processar produtos do pedido
                if (isset($pedido['produtos']) && !empty($pedido['produtos'])) {
                    // Garantir que produtos seja um array, decodificando se for string JSON
                    $produtosPedido = null;
                    
                    if (is_string($pedido['produtos'])) {
                        try {
                            $produtosPedido = json_decode($pedido['produtos'], true);
                        } catch (\Exception $e) {
                            error_log("Erro ao decodificar JSON de produtos: " . $e->getMessage());
                            continue; // Pular este pedido e continuar o loop
                        }
                    } else if (is_array($pedido['produtos'])) {
                        $produtosPedido = $pedido['produtos'];
                    }
                    
                    // Processar produtos se for um array válido
                    if (is_array($produtosPedido)) {
                        foreach ($produtosPedido as $produtoId => $quantidade) {
                            if (!isset($produtosVendidos[$produtoId])) {
                                $produtosVendidos[$produtoId] = 0;
                            }
                            // Garantir que quantidade seja inteiro
                            $produtosVendidos[$produtoId] += intval($quantidade);
                        }
                    }
                }
            }
            
            // Calcular médias e percentuais
            $mediaPorPedido = $totalPedidos > 0 ? ($valorTotalVendas / $totalPedidos) : 0;
            $mediaItensPorPedido = $totalPedidos > 0 ? ($totalItensVendidos / $totalPedidos) : 0;
            $percentualPagos = $totalPedidos > 0 ? ($pedidosPagos / $totalPedidos) * 100 : 0;
            
            // Ordenar produtos mais vendidos
            arsort($produtosVendidos);
            $top10Produtos = array_slice($produtosVendidos, 0, 10, true);
            
            // Formatar produtos mais vendidos com nomes
            $produtosMaisVendidos = [];
            foreach ($top10Produtos as $produtoId => $quantidade) {
                // Buscar informações do produto no mapa de produtos
                $nome = isset($produtosMap[$produtoId]) ? $produtosMap[$produtoId]['nome'] : "Produto #$produtoId";
                $preco = isset($produtosMap[$produtoId]) ? floatval($produtosMap[$produtoId]['preco']) : 0;
                
                $produtosMaisVendidos[] = [
                    'id' => $produtoId,
                    'nome' => $nome,
                    'quantidade' => $quantidade,
                    'valorTotal' => $quantidade * $preco
                ];
            }
            
            // Ordenar mesas mais utilizadas
            arsort($mesasMaisUtilizadas);
            $topMesas = array_slice($mesasMaisUtilizadas, 0, 5, true);
            
            // Formatar mesas para exibição
            $mesasFormatadas = [];
            foreach ($topMesas as $mesa => $quantidade) {
                $mesasFormatadas[] = [
                    'mesa' => $mesa,
                    'quantidade' => $quantidade,
                    'porcentagem' => $totalPedidos > 0 ? round(($quantidade / $totalPedidos) * 100) : 0
                ];
            }
            
            // Organizar dados de horários para o gráfico (24 horas)
            $horariosFormatados = [];
            for ($hora = 0; $hora < 24; $hora++) {
                $horaFormatada = str_pad($hora, 2, '0', STR_PAD_LEFT) . 'h';
                $horariosFormatados[] = [
                    'hora' => $horaFormatada,
                    'pedidos' => isset($horariosVendas[$hora]) ? $horariosVendas[$hora] : 0
                ];
            }
            
            // Vendas dos últimos 7 dias
            $ultimosDias = [];
            $hoje = date('Y-m-d');
            for ($i = 6; $i >= 0; $i--) {
                $data = date('Y-m-d', strtotime("-$i days", strtotime($hoje)));
                $label = date('d/m', strtotime($data));
                
                // Obter valor de vendas para este dia
                $valorDia = isset($vendasPorDia[$data]) ? $vendasPorDia[$data] : 0;
                
                $ultimosDias[$data] = [
                    'data' => $data,
                    'label' => $label,
                    'valor' => $valorDia
                ];
            }
            
            // Calcular crescimento (comparando hoje com ontem)
            $hoje = date('Y-m-d');
            $ontem = date('Y-m-d', strtotime('-1 day'));
            $valorHoje = isset($ultimosDias[$hoje]) ? $ultimosDias[$hoje]['valor'] : 0;
            $valorOntem = isset($ultimosDias[$ontem]) ? $ultimosDias[$ontem]['valor'] : 0;
            $crescimento = 0;
            
            if ($valorOntem > 0) {
                $crescimento = (($valorHoje - $valorOntem) / $valorOntem) * 100;
            } elseif ($valorHoje > 0) {
                $crescimento = 100; // Se ontem foi zero e hoje tem valor, crescimento de 100%
            }
            
            // Log para depuração
            error_log("Total de pedidos: $totalPedidos, Valor total: $valorTotalVendas");
            
            // Retornar todas as métricas calculadas
            return [
                'resumo' => [
                    'totalPedidos' => $totalPedidos,
                    'totalVendas' => number_format($valorTotalVendas, 2, ',', '.'),
                    'totalVendasNumerico' => $valorTotalVendas,
                    'totalItens' => $totalItensVendidos,
                    'mediaPorPedido' => number_format($mediaPorPedido, 2, ',', '.'),
                    'mediaItensPorPedido' => round($mediaItensPorPedido, 1),
                    'percentualPagos' => round($percentualPagos),
                    'crescimentoDiario' => round($crescimento, 1)
                ],
                'vendasPorStatus' => $vendasPorStatus,
                'vendasPorDia' => array_values($ultimosDias),
                'horariosVendas' => $horariosFormatados,
                'produtosMaisVendidos' => $produtosMaisVendidos,
                'mesasMaisUtilizadas' => $mesasFormatadas
            ];
        } catch (\Exception $e) {
            error_log("Erro ao calcular métricas: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Verifica se o usuário atual é administrador
     * @return bool True se for administrador, False caso contrário
     */
    private function isAdmin() {
        Session::init();
        return (
            Session::get("user") && 
            strpos(Session::get("user"), "admin") !== false
        );
    }

    public function logout () {

    }
}