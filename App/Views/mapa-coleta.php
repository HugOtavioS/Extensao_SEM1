<?php
use Models\Session\Session;
use Config\Database\Database;
use Config\env;

$db = new Database(new env());
Session::init();

// Buscar pontos de coleta do banco de dados (simulado por enquanto)
$pontosColeta = [
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
    ],
    [
        'id' => 4,
        'nome' => 'Reciclagem Sustentável',
        'endereco' => 'Rua Alameda Santos, 700 - Jardim Paulista',
        'lat' => -23.569416,
        'lng' => -46.649778,
        'materiais' => [
            'aluminio' => 19.00,
            'papel' => 2.40,
            'plastico' => 3.80,
            'vidro' => 1.30
        ]
    ],
    [
        'id' => 5,
        'nome' => 'EcoCoop do Bairro',
        'endereco' => 'Av. Paulista, 1500 - Bela Vista',
        'lat' => -23.564755,
        'lng' => -46.652481,
        'materiais' => [
            'aluminio' => 18.90,
            'papel' => 2.25,
            'plastico' => 3.70,
            'vidro' => 1.25
        ]
    ],
    [
        'id' => 6,
        'nome' => 'Ponto Verde Reciclagem',
        'endereco' => 'Rua Augusta, 890 - Consolação',
        'lat' => -23.551270,
        'lng' => -46.644759,
        'materiais' => [
            'aluminio' => 19.50,
            'papel' => 2.35,
            'plastico' => 3.90,
            'vidro' => 1.40
        ]
    ]
];

// Calcular a média dos preços para exibir no painel informativo
$mediaPrecos = [
    'aluminio' => 0,
    'papel' => 0,
    'plastico' => 0,
    'vidro' => 0
];

foreach ($pontosColeta as $ponto) {
    $mediaPrecos['aluminio'] += $ponto['materiais']['aluminio'];
    $mediaPrecos['papel'] += $ponto['materiais']['papel'];
    $mediaPrecos['plastico'] += $ponto['materiais']['plastico'];
    $mediaPrecos['vidro'] += $ponto['materiais']['vidro'];
}

$count = count($pontosColeta);
$mediaPrecos['aluminio'] = number_format($mediaPrecos['aluminio'] / $count, 2, ',', '.');
$mediaPrecos['papel'] = number_format($mediaPrecos['papel'] / $count, 2, ',', '.');
$mediaPrecos['plastico'] = number_format($mediaPrecos['plastico'] / $count, 2, ',', '.');
$mediaPrecos['vidro'] = number_format($mediaPrecos['vidro'] / $count, 2, ',', '.');

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de Pontos de Coleta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Substituir Google Maps pelo Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
          crossorigin="" />
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
        
        #mapa {
            width: 100%;
            height: 500px;
            border-radius: 0.75rem;
            position: relative;
            z-index: 1; /* Reduzir z-index do mapa */
        }
        
        .leaflet-container {
            z-index: 1; /* Garantir que elementos do Leaflet tenham um z-index menor que os modais */
        }
        
        /* Modal e seus elementos */
        #enderecoManualModal {
            z-index: 1000 !important;
        }
        
        #enderecoManualModal > div {
            z-index: 1001 !important;
        }
        
        .marker-info {
            min-width: 200px;
            max-width: 300px;
        }
        
        .marker-title {
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #333F48;
        }
        
        .marker-address {
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
            color: #6B7280;
        }
        
        .marker-price {
            font-size: 0.875rem;
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.25rem;
        }
        
        .marker-price-value {
            font-weight: bold;
            color: #22c55e;
        }
        
        .material-icon {
            width: 24px;
            height: 24px;
            margin-right: 8px;
        }
        
        .price-badge {
            @apply text-xs font-medium py-1 px-2 rounded-full mr-1;
        }
        
        .price-badge.aluminio {
            @apply bg-amber-100 text-amber-800 border border-amber-200;
        }
        
        .price-badge.papel {
            @apply bg-blue-100 text-blue-800 border border-blue-200;
        }
        
        .price-badge.plastico {
            @apply bg-emerald-100 text-emerald-800 border border-emerald-200;
        }
        
        .price-badge.vidro {
            @apply bg-indigo-100 text-indigo-800 border border-indigo-200;
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
            <h1 class="text-3xl font-bold text-text-dark">Mapa de Pontos de Coleta</h1>
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
        </div>

        <!-- Painel de Preços Médios -->
        <div class="mb-6 p-4 bg-white rounded-xl shadow-md">
            <h2 class="font-bold text-lg text-text-dark mb-3">Preços Médios (R$/kg)</h2>
            <div class="flex flex-wrap gap-3">
                <div class="flex items-center">
                    <div class="price-badge aluminio">Alumínio</div>
                    <span class="font-bold text-primary">R$ <?= $mediaPrecos['aluminio'] ?></span>
                </div>
                <div class="flex items-center">
                    <div class="price-badge papel">Papel</div>
                    <span class="font-bold text-primary">R$ <?= $mediaPrecos['papel'] ?></span>
                </div>
                <div class="flex items-center">
                    <div class="price-badge plastico">Plástico</div>
                    <span class="font-bold text-primary">R$ <?= $mediaPrecos['plastico'] ?></span>
                </div>
                <div class="flex items-center">
                    <div class="price-badge vidro">Vidro</div>
                    <span class="font-bold text-primary">R$ <?= $mediaPrecos['vidro'] ?></span>
                </div>
            </div>
        </div>

        <!-- Mapa -->
        <div class="card mb-8">
            <div class="card-header">Pontos de Coleta Próximos</div>
            <p class="mb-4 text-text-light">Encontre pontos de coleta de materiais recicláveis mais próximos de você e confira os preços por quilo.</p>
            <div id="mapa" class="shadow-lg"></div>
        </div>

        <!-- Lista de Pontos de Coleta -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <?php foreach ($pontosColeta as $ponto): ?>
                <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300">
                    <div class="p-5 border-b border-gray-100">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-bold text-text-dark flex items-center">
                                    <?= $ponto['nome'] ?>
                                </h3>
                                <p class="text-text-light text-sm mt-1">
                                    <?= $ponto['endereco'] ?>
                                </p>
                            </div>
                            <button 
                                data-lat="<?= $ponto['lat'] ?>"
                                data-lng="<?= $ponto['lng'] ?>"
                                class="btnVerNoMapa text-secondary hover:text-secondary-dark text-sm font-medium transition-colors duration-200 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                </svg>
                                Ver no Mapa
                            </button>
                        </div>
                    </div>
                    <div class="px-5 py-4">
                        <div class="mb-2 text-text-dark font-medium text-sm">Materiais aceitos e preços:</div>
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <div class="flex items-center">
                                <div class="price-badge aluminio">Alumínio</div>
                                <span class="ml-1 text-primary text-sm font-medium">R$ <?= number_format($ponto['materiais']['aluminio'], 2, ',', '.') ?></span>
                            </div>
                            <div class="flex items-center">
                                <div class="price-badge papel">Papel</div>
                                <span class="ml-1 text-primary text-sm font-medium">R$ <?= number_format($ponto['materiais']['papel'], 2, ',', '.') ?></span>
                            </div>
                            <div class="flex items-center">
                                <div class="price-badge plastico">Plástico</div>
                                <span class="ml-1 text-primary text-sm font-medium">R$ <?= number_format($ponto['materiais']['plastico'], 2, ',', '.') ?></span>
                            </div>
                            <div class="flex items-center">
                                <div class="price-badge vidro">Vidro</div>
                                <span class="ml-1 text-primary text-sm font-medium">R$ <?= number_format($ponto['materiais']['vidro'], 2, ',', '.') ?></span>
                            </div>
                        </div>
                        <button 
                            class="mt-2 w-full bg-primary/10 hover:bg-primary/20 text-primary font-medium p-2 rounded transition-colors duration-200 text-sm">
                            Solicitar Coleta
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
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

    <!-- Script do mapa usando Google Maps -->
    <script>
        // Variáveis globais
        let map;
        let markers = [];
        let userMarker;
        let popup = null;
        
        // Pontos de coleta do PHP - usado apenas como fallback se tudo mais falhar
        const pontosColetaEstaticos = <?= json_encode($pontosColeta) ?>;
        let pontosColetaAtuais = []; // Variável para armazenar os pontos atuais carregados
        
        // Calcular a posição central como média dos pontos (posição padrão)
        let defaultLat = 0;
        let defaultLng = 0;
        
        pontosColetaEstaticos.forEach(ponto => {
            defaultLat += ponto.lat;
            defaultLng += ponto.lng;
        });
        
        defaultLat /= pontosColetaEstaticos.length;
        defaultLng /= pontosColetaEstaticos.length;
        
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
            
            // Não adicionamos pontos iniciais aqui, esperamos pela localização do usuário
            
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
            
            // Ícone personalizado para pontos de coleta - ÍCONE LOCAL
            const greenIcon = L.icon({
                iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41],
                className: 'leaflet-marker-green' // Adicionamos uma classe para estilizar via CSS
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
        
        // Definir a localização do usuário (função comum para ambos os métodos)
        function definirLocalizacaoUsuario(pos) {
            console.log("Definindo localização do usuário para:", pos);
            
            // Remover todos os marcadores existentes do mapa
            limparTodosMarcadores();
            
            // Ícone personalizado para o usuário - ÍCONE LOCAL
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
            
            // CORREÇÃO: Agora vamos usar diretamente buscarPontosReais que tentará múltiplas fontes de dados
            buscarPontosReais(pos).then(pontosProximos => {
                // Armazenar os pontos encontrados na variável global para uso posterior
                pontosColetaAtuais = pontosProximos;
                
                // Agora adicionar os marcadores para esses pontos próximos
                adicionarMarcadoresPontos(pontosProximos);
                
                // Calcular distâncias para cada ponto de coleta
                calcularDistancias(pos, pontosProximos);
            }).catch(error => {
                console.error("Erro ao buscar pontos de coleta:", error);
                // Em caso de erro, mostrar mensagem ao usuário
                document.querySelector('.grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3.gap-6.mb-8').innerHTML = `
                    <div class="col-span-full p-6 bg-white rounded-xl shadow-md text-center">
                        <p class="text-text-light">Ocorreu um erro ao buscar pontos de coleta. Por favor, tente novamente.</p>
                        <button id="btnTentarNovamente" class="mt-4 bg-primary text-white px-4 py-2 rounded-md">Tentar novamente</button>
                    </div>
                `;
                
                // Adicionar evento ao botão de tentar novamente
                document.getElementById('btnTentarNovamente').addEventListener('click', function() {
                    definirLocalizacaoUsuario(pos);
                });
            });
        }
        
        // Função para simular pontos de coleta próximos à localização do usuário
        function simularPontosColetaProximos(posicaoUsuario) {
            // Redirecionar para a busca real
            console.log("Chamando função buscarPontosReais diretamente");
            return buscarPontosReais(posicaoUsuario);
        }
        
        // Função para buscar pontos de coleta reais usando a API Overpass
        function buscarPontosReais(posicaoUsuario) {
            console.log("Buscando pontos de coleta próximos com API Overpass diretamente");
            
            return new Promise((resolve) => {
                // Valor padrão para o raio de busca em metros
                const raio = 10000; // 10km para aumentar as chances de encontrar pontos
                
                // Coordenadas
                const lat = posicaoUsuario.lat;
                const lng = posicaoUsuario.lng;
                
                // Construir a consulta Overpass para encontrar pontos de reciclagem, coleta e resíduos
                const overpassQuery = `
                    [out:json][timeout:30];
                    (
                        // Pontos de reciclagem
                        node["amenity"="recycling"](around:${raio},${lat},${lng});
                        way["amenity"="recycling"](around:${raio},${lat},${lng});
                        relation["amenity"="recycling"](around:${raio},${lat},${lng});
                        
                        // Centros de resíduos
                        node["landuse"="landfill"](around:${raio},${lat},${lng});
                        way["landuse"="landfill"](around:${raio},${lat},${lng});
                        
                        // Centros de reciclagem
                        node["recycling_type"="centre"](around:${raio},${lat},${lng});
                        way["recycling_type"="centre"](around:${raio},${lat},${lng});
                        
                        // Containers de reciclagem
                        node["recycling_type"="container"](around:${raio},${lat},${lng});
                        
                        // Lojas e negócios de reciclagem
                        node["shop"="second_hand"](around:${raio},${lat},${lng});
                        way["shop"="second_hand"](around:${raio},${lat},${lng});
                        
                        // Buscando com palavras-chave em nomes
                        node[name~"recicl|coleta|resíduo|lixo|eco"](around:${raio},${lat},${lng});
                        way[name~"recicl|coleta|resíduo|lixo|eco"](around:${raio},${lat},${lng});
                    );
                    out body;
                    >;
                    out skel qt;
                `;
                
                // URL da API Overpass
                const overpassUrl = "https://overpass-api.de/api/interpreter";
                
                console.log("Enviando consulta para Overpass API...");
                
                // Fazer a requisição para a API
                fetch(overpassUrl, {
                    method: 'POST',
                    body: 'data=' + encodeURIComponent(overpassQuery),
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Erro na resposta da API Overpass: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("Resposta da API Overpass:", data);
                    
                    // Processar os resultados
                    const pontosReais = processarResultadosOverpass(data, posicaoUsuario);
                    console.log(`Encontrados ${pontosReais.length} pontos de coleta pela API Overpass`);
                    
                    if (pontosReais.length > 0) {
                        resolve(pontosReais);
                    } else {
                        console.log("Nenhum ponto encontrado via Overpass, tentando Nominatim");
                        return buscarPontosViaNominatim(posicaoUsuario);
                    }
                })
                .then(pontosReais => {
                    if (pontosReais && pontosReais.length > 0) {
                        resolve(pontosReais);
                    }
                })
                .catch(error => {
                    console.error("Erro ao buscar pontos de coleta via Overpass:", error);
                    console.log("Tentando com Nominatim como alternativa...");
                    
                    // Tentar alternativa com Nominatim
                    buscarPontosViaNominatim(posicaoUsuario)
                        .then(pontos => {
                            if (pontos && pontos.length > 0) {
                                resolve(pontos);
                            } else {
                                // Se Nominatim também falhar, tentar com GeoJSON
                                console.log("Nominatim falhou, tentando GeoJSON...");
                                return buscarPontosViaGeojsonio(posicaoUsuario);
                            }
                        })
                        .then(pontos => {
                            if (pontos && pontos.length > 0) {
                                resolve(pontos);
                            } else {
                                // Última alternativa: pontos simulados
                                console.log("Gerando pontos simulados como último recurso");
                                resolve(gerarPontosSimulados(posicaoUsuario));
                            }
                        })
                        .catch(() => {
                            console.log("Todas as alternativas falharam, usando pontos simulados");
                            resolve(gerarPontosSimulados(posicaoUsuario));
                        });
                });
            });
        }
        
        // Função para buscar pontos de uma fonte de dados GeoJSON pública
        function buscarPontosViaGeojsonio(posicaoUsuario) {
            return new Promise((resolve, reject) => {
                console.log("Buscando pontos de ecopontos via fonte alternativa (geojson.io)");
                
                // Esta URL pode ser substituída por qualquer API pública ou arquivo GeoJSON contendo pontos de coleta
                // Exemplo de uma fonte pública com alguns pontos de coleta no Brasil
                const geojsonUrl = "https://gist.githubusercontent.com/anonymous/4593ec263cdce3eaf65a9a940b7a0f8c/raw/pontos_coleta_brasil.geojson";
                
                fetch(geojsonUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Erro ao buscar dados GeoJSON: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.features && data.features.length > 0) {
                        const pontosProcessados = processarResultadosGeoJSON(data, posicaoUsuario);
                        console.log(`Encontrados ${pontosProcessados.length} pontos da fonte GeoJSON`);
                        resolve(pontosProcessados);
                    } else {
                        throw new Error("Nenhum ponto encontrado no GeoJSON");
                    }
                })
                .catch(error => {
                    console.error("Erro ao buscar dados da fonte GeoJSON:", error);
                    reject(error);
                });
            });
        }
        
        // Processar resultados de uma fonte GeoJSON
        function processarResultadosGeoJSON(data, posicaoUsuario) {
            const pontosProcessados = [];
            
            // Valores médios de preços
            const precosMedias = {
                aluminio: parseFloat('<?= str_replace(",", ".", $mediaPrecos["aluminio"]) ?>'),
                papel: parseFloat('<?= str_replace(",", ".", $mediaPrecos["papel"]) ?>'),
                plastico: parseFloat('<?= str_replace(",", ".", $mediaPrecos["plastico"]) ?>'),
                vidro: parseFloat('<?= str_replace(",", ".", $mediaPrecos["vidro"]) ?>')
            };
            
            // Variação aleatória para preços simulados
            const variacao = () => 1 + (Math.random() * 0.2 - 0.1); // -10% a +10%
            
            // Processar cada feature do GeoJSON
            if (data.features) {
                data.features.forEach((feature, index) => {
                    if (feature.geometry && feature.geometry.type === "Point" && 
                        feature.geometry.coordinates && feature.geometry.coordinates.length >= 2) {
                        
                        // GeoJSON usa [longitude, latitude]
                        const lng = feature.geometry.coordinates[0];
                        const lat = feature.geometry.coordinates[1];
                        
                        // Propriedades do ponto
                        const props = feature.properties || {};
                        
                        // Calcular distância
                        const distancia = calcularDistanciaHaversine(
                            posicaoUsuario.lat, 
                            posicaoUsuario.lng, 
                            lat, 
                            lng
                        );
                        
                        // Só considerar pontos em um raio razoável
                        if (distancia <= 50) { // 50km max
                            // Nome e endereço
                            const nome = props.name || props.nome || `Ponto de Coleta ${index + 1}`;
                            const endereco = props.address || props.endereco || 
                                            props.description || props.descricao || 
                                            `Localizado a ${distancia.toFixed(2)} km`;
                            
                            const materiais = {
                                aluminio: precosMedias.aluminio * variacao(),
                                papel: precosMedias.papel * variacao(),
                                plastico: precosMedias.plastico * variacao(),
                                vidro: precosMedias.vidro * variacao()
                            };
                            
                            pontosProcessados.push({
                                id: props.id || index,
                                nome: nome,
                                endereco: endereco,
                                lat: lat,
                                lng: lng,
                                materiais: materiais,
                                precosMedios: {
                                    aluminio: true,
                                    papel: true,
                                    plastico: true,
                                    vidro: true
                                },
                                distancia: distancia
                            });
                        }
                    }
                });
            }
            
            // Ordenar por distância
            pontosProcessados.sort((a, b) => a.distancia - b.distancia);
            
            // Limitar a 10 pontos
            return pontosProcessados.slice(0, 10);
        }
        
        // Processar os resultados da API Overpass
        function processarResultadosOverpass(data, posicaoUsuario) {
            const pontosProcessados = [];
            
            // Valores médios de preços para usar quando não houver informações específicas
            const precosMedias = {
                aluminio: parseFloat('<?= str_replace(",", ".", $mediaPrecos["aluminio"]) ?>'),
                papel: parseFloat('<?= str_replace(",", ".", $mediaPrecos["papel"]) ?>'),
                plastico: parseFloat('<?= str_replace(",", ".", $mediaPrecos["plastico"]) ?>'),
                vidro: parseFloat('<?= str_replace(",", ".", $mediaPrecos["vidro"]) ?>')
            };
            
            // Processar cada elemento retornado
            if (data.elements && data.elements.length > 0) {
                data.elements.forEach((element, index) => {
                    // Verificar se é um nó (point) e tem coordenadas
                    if ((element.type === 'node' || (element.type === 'way' && element.center)) && 
                        (element.lat || (element.center && element.center.lat))) {
                        
                        // Extrair as tags
                        const tags = element.tags || {};
                        
                        // Definir coordenadas
                        const lat = element.lat || element.center.lat;
                        const lng = element.lon || element.center.lon;
                        
                        // Obter nome ou gerar um nome se não existir
                        const nome = tags.name || 
                                    (tags.operator ? `Ponto de Reciclagem - ${tags.operator}` : 
                                    `Ponto de Reciclagem ${index + 1}`);
                        
                        // Obter endereço
                        let endereco = tags['addr:street'] || '';
                        if (tags['addr:housenumber']) {
                            endereco += `, ${tags['addr:housenumber']}`;
                        }
                        if (tags['addr:city']) {
                            endereco += ` - ${tags['addr:city']}`;
                        }
                        if (!endereco) {
                            endereco = 'Endereço não disponível';
                        }
                        
                        // Determinar quais materiais são aceitos
                        const aceitaAluminio = tags['recycling:aluminium'] === 'yes' || tags['recycling:cans'] === 'yes';
                        const aceitaPapel = tags['recycling:paper'] === 'yes' || tags['recycling:cardboard'] === 'yes';
                        const aceitaPlastico = tags['recycling:plastic'] === 'yes' || tags['recycling:plastic_bottles'] === 'yes';
                        const aceitaVidro = tags['recycling:glass'] === 'yes' || tags['recycling:glass_bottles'] === 'yes';
                        
                        // Criar um objeto com a variação de preços para cada material
                        // Simular variação de até 10% para mais ou para menos
                        const variacao = () => 1 + (Math.random() * 0.2 - 0.1); // -10% a +10%
                        
                        const materiais = {
                            aluminio: precosMedias.aluminio * variacao(),
                            papel: precosMedias.papel * variacao(),
                            plastico: precosMedias.plastico * variacao(),
                            vidro: precosMedias.vidro * variacao()
                        };
                        
                        // Marcar quais preços são médios (todos neste caso, já que não temos preços reais)
                        const precosMedios = {
                            aluminio: !aceitaAluminio,
                            papel: !aceitaPapel,
                            plastico: !aceitaPlastico,
                            vidro: !aceitaVidro
                        };
                        
                        // Adicionar o ponto processado
                        pontosProcessados.push({
                            id: element.id,
                            nome: nome,
                            endereco: endereco,
                            lat: lat,
                            lng: lng,
                            materiais: materiais,
                            precosMedios: precosMedios,
                            distancia: calcularDistanciaHaversine(
                                posicaoUsuario.lat, 
                                posicaoUsuario.lng, 
                                lat, 
                                lng
                            )
                        });
                    }
                });
            }
            
            // Ordenar por distância
            pontosProcessados.sort((a, b) => a.distancia - b.distancia);
            
            // Limitar a no máximo 10 pontos para manter a performance
            return pontosProcessados.slice(0, 10);
        }
        
        // Função para gerar pontos simulados para demonstração quando não houver dados reais
        function gerarPontosSimulados(posicaoUsuario) {
            // Valores médios de preços para usar nos pontos simulados
            const precosMedias = {
                aluminio: parseFloat('<?= str_replace(",", ".", $mediaPrecos["aluminio"]) ?>'),
                papel: parseFloat('<?= str_replace(",", ".", $mediaPrecos["papel"]) ?>'),
                plastico: parseFloat('<?= str_replace(",", ".", $mediaPrecos["plastico"]) ?>'),
                vidro: parseFloat('<?= str_replace(",", ".", $mediaPrecos["vidro"]) ?>')
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
                        <div class="price-badge ${material}">${material.charAt(0).toUpperCase() + material.slice(1)}</div>
                        <span class="ml-1 text-primary text-sm font-medium">
                            R$ ${valor.toFixed(2).replace('.', ',')}${mediaTag}
                        </span>
                    </div>
                    `;
                };
                
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
            
            // Botões "Ver no Mapa"
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
        
        // Inicializar o mapa quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar o mapa
            initMap();
        });
    </script>
</body>

</html> 