<?php
use Models\Session\Session;
use Config\Database\Database;
use Config\env;

$db = new Database(new env());
Session::init();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodOrder - Sistema de Pedidos</title>
    <script src="https://cdn.tailwindcss.com"></script>
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

    <main class="flex-grow flex items-center justify-center p-4">
        <div class="max-w-3xl mx-auto text-center px-4">
            <div class="mb-8 text-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </div>
            <h1 class="text-4xl font-bold text-text-dark mb-4">Bem-vindo ao FoodOrder</h1>
            <p class="text-xl text-text-light mb-8">Sistema de gerenciamento de pedidos para bares e restaurantes</p>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <div class="text-primary mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-text-dark text-lg mb-2">Criar Pedidos</h3>
                    <p class="text-text-light">Crie pedidos facilmente com nossa interface intuitiva</p>
                </div>
                
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <div class="text-secondary mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-text-dark text-lg mb-2">Gerenciar Comandas</h3>
                    <p class="text-text-light">Acompanhe o status de todos os pedidos em tempo real</p>
                </div>
                
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <div class="text-accent mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-text-dark text-lg mb-2">Controle de Pagamentos</h3>
                    <p class="text-text-light">Marque pedidos como pagos e monitore o fluxo de caixa</p>
                </div>
            </div>
            
            <a href="/pedidos" class="inline-flex items-center bg-primary hover:bg-primary-dark text-white font-bold py-3 px-8 rounded-lg transition-colors shadow-md">
                <span>Acessar Sistema</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                </svg>
            </a>
        </div>
    </main>

    <footer class="bg-white border-t border-gray-200 py-6">
        <div class="container mx-auto px-4 text-center">
            <p class="text-text-light">&copy; 2023 FoodOrder. Todos os direitos reservados.</p>
        </div>
    </footer>
    
    <!-- JavaScript for mobile menu toggle -->
    <script>
        function toggleMobileMenu(menuId) {
            const menu = document.getElementById(menuId);
            if (menu.classList.contains('hidden')) {
                menu.classList.remove('hidden');
                menu.classList.add('block');
            } else {
                menu.classList.remove('block');
                menu.classList.add('hidden');
            }
        }
    </script>
</body>
</html>