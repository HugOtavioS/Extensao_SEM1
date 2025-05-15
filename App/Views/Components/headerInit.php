<header class="bg-white shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">
            <div class="flex-shrink-0">
                <a href="/" class="text-xl font-bold text-[#333F48] flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mr-2 text-[#22c55e]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span>Serve</span>
                </a>
            </div>
            
            <!-- Mobile menu button -->
            <div class="md:hidden">
                <button type="button" onclick="toggleMobileMenu('navMenuInit')" class="inline-flex items-center justify-center p-2 rounded-md text-[#333F48] hover:text-[#22c55e] hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#22c55e]">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
            
            <!-- Desktop menu -->
            <div class="hidden md:flex space-x-4">
                <a href="/" class="text-[#333F48] hover:text-[#22c55e] px-3 py-2 rounded-md text-sm font-medium">Início</a>
                <a href="/pedidos" class="text-[#333F48] hover:text-[#22c55e] px-3 py-2 rounded-md text-sm font-medium">Lista de Pedidos</a>
                <a href="/mapa-coleta" class="text-[#333F48] hover:text-[#22c55e] px-3 py-2 rounded-md text-sm font-medium">Pontos de Coleta</a>
                <a href="/login" class="bg-[#22c55e] hover:bg-[#E85A2A] text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">Login</a>
            </div>
        </div>
    </div>
    
    <!-- Mobile menu, show/hide based on menu state -->
    <div id="navMenuInit" class="hidden md:hidden">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3 border-t">
            <a href="/" class="block text-[#333F48] hover:text-[#22c55e] hover:bg-gray-50 px-3 py-2 rounded-md text-base font-medium">Início</a>
            <a href="/pedidos" class="block text-[#333F48] hover:text-[#22c55e] px-3 py-2 rounded-md text-base font-medium">Lista de Pedidos</a>
            <a href="/mapa-coleta" class="block text-[#333F48] hover:text-[#22c55e] px-3 py-2 rounded-md text-base font-medium">Pontos de Coleta</a>
            <a href="/login" class="block text-white bg-[#22c55e] hover:bg-[#E85A2A] px-3 py-2 rounded-md text-base font-medium mt-2 transition-colors">Login</a>
        </div>
    </div>
</header>
