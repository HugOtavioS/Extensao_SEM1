<?php

namespace Controllers;

use Models\Session\Session;
use Config\Database\Database;
use Config\env;
use Controllers\ViewController;

class MapaController {
    private $view;
    private $db;
    
    public function __construct() {
        $this->view = new ViewController();
        $this->db = new Database(new env());
        Session::init();
    }
    
    /**
     * Renderiza a página do mapa de pontos de coleta
     */
    public function index() {
        // Carrega a view do mapa de pontos de coleta
        $this->view->load("mapa-coleta", ["title" => "Mapa de Pontos de Coleta"]);
    }
    
    /**
     * API para obter todos os pontos de coleta
     */
    public function getPontosColeta() {
        // No futuro, buscar do banco de dados
        $pontosColeta = $this->buscarPontosColeta();
        
        // Retornar como JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $pontosColeta
        ]);
    }
    
    /**
     * API para obter pontos de coleta próximos a uma coordenada
     */
    public function getPontosColetaProximos() {
        // Verificar se as coordenadas foram fornecidas
        if (!isset($_POST['lat']) || !isset($_POST['lng'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Coordenadas não fornecidas'
            ]);
            return;
        }
        
        $lat = $_POST['lat'];
        $lng = $_POST['lng'];
        $raio = isset($_POST['raio']) ? $_POST['raio'] : 10; // Raio em km
        
        // Buscar pontos próximos (simulado por enquanto)
        $pontosColeta = $this->buscarPontosColeta();
        $pontosProximos = $this->filtrarPontosProximos($pontosColeta, $lat, $lng, $raio);
        
        // Retornar como JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $pontosProximos
        ]);
    }
    
    /**
     * Busca pontos de coleta do banco de dados (simulado por enquanto)
     */
    private function buscarPontosColeta() {
        // Em uma implementação real, buscar do banco de dados
        // Por enquanto, retorna dados simulados
        return [
            [
                'id' => 1,
                'nome' => 'EcoPonto Central',
                'endereco' => 'Av. Principal, 100 - Centro',
                'lat' => -23.550520,
                'lng' => -46.633308,
                'materiais' => [
                    'aluminio' => 18.50,
                    'papel' => 2.30,
                    'plastico' => 3.75,
                    'vidro' => 1.20
                ]
            ],
            [
                'id' => 2,
                'nome' => 'Reciclagem Verde',
                'endereco' => 'Rua das Flores, 250 - Jardins',
                'lat' => -23.562320,
                'lng' => -46.654680,
                'materiais' => [
                    'aluminio' => 19.20,
                    'papel' => 2.10,
                    'plastico' => 3.50,
                    'vidro' => 1.00
                ]
            ],
            [
                'id' => 3,
                'nome' => 'Cooperativa Recicle Já',
                'endereco' => 'Av. dos Estados, 450 - Vila Industrial',
                'lat' => -23.542600,
                'lng' => -46.614500,
                'materiais' => [
                    'aluminio' => 19.80,
                    'papel' => 2.50,
                    'plastico' => 4.00,
                    'vidro' => 1.50
                ]
            ]
        ];
    }
    
    /**
     * Filtra pontos de coleta próximos a uma coordenada
     */
    private function filtrarPontosProximos($pontos, $lat, $lng, $raio) {
        $pontosProximos = [];
        
        foreach ($pontos as $ponto) {
            $distancia = $this->calcularDistanciaHaversine(
                $lat, 
                $lng, 
                $ponto['lat'], 
                $ponto['lng']
            );
            
            // Se estiver dentro do raio, adicionar aos pontos próximos
            if ($distancia <= $raio) {
                $ponto['distancia'] = $distancia;
                $pontosProximos[] = $ponto;
            }
        }
        
        // Ordenar por distância
        usort($pontosProximos, function($a, $b) {
            return $a['distancia'] <=> $b['distancia'];
        });
        
        return $pontosProximos;
    }
    
    /**
     * Calcula a distância entre dois pontos usando a fórmula de Haversine
     * (considera a curvatura da Terra)
     */
    private function calcularDistanciaHaversine($lat1, $lon1, $lat2, $lon2) {
        $raioTerra = 6371; // Raio médio da Terra em km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLon/2) * sin($dLon/2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distancia = $raioTerra * $c;
        
        return $distancia;
    }
} 