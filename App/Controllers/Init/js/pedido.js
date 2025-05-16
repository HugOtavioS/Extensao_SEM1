// Variáveis globais para controle
const produtoQuantidades = {};
let precoProdutos = {};
let pedidoEmEdicao = null; // Armazenar o pedido em edição
let isAdmin = false; // Flag para controlar se o usuário é admin

// Elementos DOM
document.addEventListener('DOMContentLoaded', () => {
    // Verificar se o usuário é administrador
    isAdmin = document.querySelectorAll('.admin-edit-btn').length > 0 && 
              !document.querySelector('.admin-edit-btn').classList.contains('hidden');
    
    // Carregar preços dos produtos
    carregarPrecosProdutos();
    
    // Inicializar quantidades
    inicializarQuantidades();
    
    // Configurar listeners para botões
    setupEventListeners();
    
    // Verificar se há um pedido recém-criado
    verificarPedidoRecemCriado();
});

// Função para inicializar quantidades
function inicializarQuantidades() {
    const quantidadeElements = document.querySelectorAll('[id^="quantidade-"]');
    quantidadeElements.forEach(element => {
        const id = element.id.replace('quantidade-', '');
        produtoQuantidades[id] = 0;
        element.innerText = '0';
});
}

// Função para carregar preços dos produtos
function carregarPrecosProdutos() {
    const produtosCards = document.querySelectorAll('.bg-white.border.border-gray-200');
    
    produtosCards.forEach(card => {
        // Encontrar o ID do produto a partir do seletor de quantidade
        const quantidadeElement = card.querySelector('[id^="quantidade-"]');
        if (!quantidadeElement) return;
        
        const id = quantidadeElement.id.replace('quantidade-', '');
        
        // Encontrar o elemento de preço
        const precoElement = card.querySelector('.text-primary.font-bold');
        if (precoElement) {
            // Extrair e formatar o preço: "R$ 10,50" -> 10.50
            const precoTexto = precoElement.textContent.trim()
                .replace('R$ ', '')
                .replace('.', '')  // Remove pontos de milhar
                .replace(',', '.'); // Converte vírgula decimal para ponto
            
            precoProdutos[id] = parseFloat(precoTexto);
            console.log(`Produto ID ${id} - Preço: ${precoProdutos[id]}`);
        }
    });
}

// Configurar todos os event listeners
function setupEventListeners() {
    // Botões para abrir modal
    const btnNovoPedido = document.getElementById('btnNovoPedido');
    const btnSemPedidos = document.getElementById('btnSemPedidos');
    
    if (btnNovoPedido) btnNovoPedido.addEventListener('click', abrirModalNovoPedido);
    if (btnSemPedidos) btnSemPedidos.addEventListener('click', abrirModalNovoPedido);
    
    // Botões do modal
    const btnFecharModal = document.getElementById('btnFecharModal');
    const btnCancelarPedido = document.getElementById('btnCancelarPedido');
    const btnFinalizarPedido = document.getElementById('btnFinalizarPedido');
    
    if (btnFecharModal) btnFecharModal.addEventListener('click', fecharModalNovoPedido);
    if (btnCancelarPedido) btnCancelarPedido.addEventListener('click', fecharModalNovoPedido);
    if (btnFinalizarPedido) btnFinalizarPedido.addEventListener('click', finalizarPedido);
    
    // Configurar listeners para botões de detalhes
    const botoesDetalhes = document.querySelectorAll('button[data-id]');
    botoesDetalhes.forEach(botao => {
        botao.addEventListener('click', () => {
            const pedidoId = botao.getAttribute('data-id');
            carregarDetalhesPedido(pedidoId);
        });
    });
    
    // Configurar listeners para edição se for administrador
    if (isAdmin) {
        setupAdminEventListeners();
    }
}

// Configurar event listeners específicos para administradores
function setupAdminEventListeners() {
    // Botões de edição
    const btnEditMesa = document.getElementById('btnEditMesa');
    const btnEditStatus = document.getElementById('btnEditStatus');
    const btnEditPago = document.getElementById('btnEditPago');
    const btnAdicionarProduto = document.getElementById('btnAdicionarProduto');
    const btnSalvarAlteracoes = document.getElementById('btnSalvarAlteracoes');
    
    // Botões para adicionar produtos
    const btnFecharAdicionarProduto = document.getElementById('btnFecharAdicionarProduto');
    const btnFecharAdicionarProdutoModal = document.getElementById('btnFecharAdicionarProdutoModal');
    const btnConfirmarAdicionarProdutos = document.getElementById('btnConfirmarAdicionarProdutos');
    
    // Botões de edição de campos
    if (btnEditMesa) btnEditMesa.addEventListener('click', () => toggleEditField('Mesa'));
    if (btnEditStatus) btnEditStatus.addEventListener('click', () => toggleEditField('Status'));
    if (btnEditPago) btnEditPago.addEventListener('click', () => toggleEditField('Pago'));
    
    // Adicionar produtos
    if (btnAdicionarProduto) btnAdicionarProduto.addEventListener('click', abrirModalAdicionarProduto);
    if (btnFecharAdicionarProduto) btnFecharAdicionarProduto.addEventListener('click', fecharModalAdicionarProduto);
    if (btnFecharAdicionarProdutoModal) btnFecharAdicionarProdutoModal.addEventListener('click', fecharModalAdicionarProduto);
    
    // Confirmar adição de produtos
    if (btnConfirmarAdicionarProdutos) btnConfirmarAdicionarProdutos.addEventListener('click', confirmarAdicionarProdutos);
    
    // Salvar alterações
    if (btnSalvarAlteracoes) btnSalvarAlteracoes.addEventListener('click', salvarAlteracoesPedido);
}

// Alternar entre exibição e edição de campos
function toggleEditField(field) {
    const container = document.getElementById(`edit${field}Container`);
    
    if (container) {
        const isVisible = !container.classList.contains('hidden');
        
        if (isVisible) {
            // Ocultar campo de edição
            container.classList.add('hidden');
        } else {
            // Mostrar campo de edição e definir valor atual
            container.classList.remove('hidden');
            
            // Definir o valor atual no campo de edição
            if (field === 'Mesa') {
                document.getElementById('editMesa').value = document.getElementById('detalhesMesa').textContent;
            } else if (field === 'Status') {
                const statusAtual = document.getElementById('detalhesStatus').textContent.toLowerCase();
                document.getElementById('editStatus').value = statusAtual;
                // Atualizar também o texto exibido no dropdown personalizado
                if (document.getElementById('currentStatusText')) {
                    document.getElementById('currentStatusText').textContent = document.getElementById('detalhesStatus').textContent;
                }
            } else if (field === 'Pago') {
                const pagoAtual = document.getElementById('detalhesPago').textContent === 'Pago' ? '1' : '0';
                document.getElementById('editPago').value = pagoAtual;
                // Atualizar também o texto exibido no dropdown personalizado
                if (document.getElementById('currentPagoText')) {
                    document.getElementById('currentPagoText').textContent = document.getElementById('detalhesPago').textContent;
                }
            }
        }
    }
}

// Abrir modal para adicionar produtos
function abrirModalAdicionarProduto() {
    // Verificar se o pedido em edição existe
    if (!pedidoEmEdicao) return;
    
    // Carregar produtos disponíveis
    const containerElement = document.getElementById('adicionarProdutosLista');
    if (containerElement) {
        carregarProdutosDisponiveis(containerElement);
    }
    
    // Abrir o modal
    const modal = document.getElementById('adicionarProdutoModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Destacar produtos que já estão no pedido
        destacarProdutosNoPedido();
    }
}

// Função para destacar produtos já incluídos no pedido
function destacarProdutosNoPedido() {
    if (!pedidoEmEdicao || !pedidoEmEdicao.produtos) return;
    
    // Aguardar o DOM estar pronto (pequeno delay para garantir que os elementos foram renderizados)
    setTimeout(() => {
        const produtosAtuais = pedidoEmEdicao.produtos || {};
        
        // Percorrer todos os produtos no modal de adicionar
        const cards = document.querySelectorAll('.produto-adicionar-card');
        cards.forEach(card => {
            const produtoId = card.getAttribute('data-id');
            const quantidadeAtual = produtosAtuais[produtoId] || 0;
            
            // Se o produto já está no pedido, destacar o card
            if (quantidadeAtual > 0) {
                card.classList.add('border-primary', 'bg-primary/5');
                
                // Adicionar uma indicação visual de produto já adicionado
                if (!card.querySelector('.produto-ja-adicionado')) {
                    const indicador = document.createElement('div');
                    indicador.className = 'produto-ja-adicionado absolute -top-2 -right-2 w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-xs font-bold shadow-md';
                    indicador.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>';
                    card.style.position = 'relative';
                    card.appendChild(indicador);
                }
            }
        });
    }, 100);
}

// Fechar modal de adicionar produtos
function fecharModalAdicionarProduto() {
    const modal = document.getElementById('adicionarProdutoModal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
}

// Carregar produtos disponíveis para adicionar ao pedido
function carregarProdutosDisponiveis(containerElement) {
    // Criar uma mensagem de carregamento
    containerElement.innerHTML = '<p class="text-text-light text-center py-4">Carregando produtos...</p>';
    
    // Verificar se o pedido em edição existe
    if (!pedidoEmEdicao || !pedidoEmEdicao.produtos) {
        containerElement.innerHTML = '<p class="text-text-light text-center py-4">Erro ao carregar produtos. Dados do pedido não encontrados.</p>';
        return;
    }
    
    // Recuperar os produtos do DOM (do modal de novo pedido)
    const produtosCards = document.querySelectorAll('.produto-card');
    const produtosAtuais = pedidoEmEdicao.produtos || {};
    
    if (produtosCards.length === 0) {
        containerElement.innerHTML = '<p class="text-text-light text-center py-4">Nenhum produto encontrado</p>';
        return;
    }
    
    // Adicionar campo de busca ao topo do modal
    let html = `
        <div class="mb-4 sticky top-0 bg-white p-2 rounded-lg shadow-sm">
            <div class="relative">
                <input type="text" id="buscaProdutoAdicionar" placeholder="Buscar produto..." 
                    class="w-full border border-gray-300 rounded-md pl-10 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <button id="limparBuscaAdicionar" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    `;
    
    // Criar cards para cada produto
    produtosCards.forEach(card => {
        const produtoId = card.getAttribute('data-id');
        if (!produtoId) return;
        
        const nome = card.getAttribute('data-nome') || `Produto ${produtoId}`;
        const preco = card.getAttribute('data-preco') || 'R$ 0,00';
        const categoria = card.getAttribute('data-categoria') || '';
        
        // Verificar se o produto já está no pedido
        const quantidadeAtual = produtosAtuais[produtoId] || 0;
        
        html += `
            <div class="produto-adicionar-card mb-3 bg-white border border-gray-200 rounded-lg p-4 flex justify-between items-center transition-all duration-200 hover:shadow-md" 
                 data-nome="${nome}" 
                 data-categoria="${categoria}" 
                 data-id="${produtoId}">
                <div>
                    <h4 class="font-medium text-text-dark">${nome}</h4>
                    <span class="text-xs text-text-light bg-gray-100 px-2 py-0.5 rounded-full">${categoria}</span>
                    <p class="text-sm text-primary mt-1 font-medium">${preco}</p>
                </div>
                <div class="flex items-center space-x-2">
                    <button 
                        onclick="diminuirQuantidadeAdicionar('${produtoId}')" 
                        class="bg-danger hover:bg-danger-dark text-white rounded-full w-8 h-8 flex items-center justify-center text-lg focus:outline-none focus:ring-2 focus:ring-danger/50 active:scale-95 transition-all duration-200 touch-manipulation">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                        </svg>
                    </button>
                    <div id="adicionar-quantidade-${produtoId}" class="w-10 h-10 flex items-center justify-center bg-white rounded-lg shadow-inner text-lg font-bold border border-gray-200">
                        ${quantidadeAtual}
                    </div>
                    <button 
                        onclick="aumentarQuantidadeAdicionar('${produtoId}')" 
                        class="bg-success hover:bg-success-dark text-white rounded-full w-8 h-8 flex items-center justify-center text-lg focus:outline-none focus:ring-2 focus:ring-success/50 active:scale-95 transition-all duration-200 touch-manipulation">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>
                </div>
            </div>
        `;
    });
    
    // Verificar se algum produto foi adicionado ao HTML
    if (html === '') {
        containerElement.innerHTML = '<p class="text-text-light text-center py-4">Nenhum produto disponível para adicionar</p>';
    } else {
        containerElement.innerHTML = html;
        
        // Configurar a funcionalidade de busca
        const searchInput = document.getElementById('buscaProdutoAdicionar');
        const clearButton = document.getElementById('limparBuscaAdicionar');
        
        if (searchInput && clearButton) {
            // Evento para busca em tempo real
            searchInput.addEventListener('input', function() {
                const termo = this.value.toLowerCase().trim();
                
                // Mostrar/ocultar botão de limpar
                if (termo.length > 0) {
                    clearButton.classList.remove('hidden');
                    clearButton.classList.add('flex');
                } else {
                    clearButton.classList.add('hidden');
                    clearButton.classList.remove('flex');
                }
                
                // Filtrar produtos pelo termo de busca
                const cards = document.querySelectorAll('.produto-adicionar-card');
                cards.forEach(card => {
                    const nome = card.getAttribute('data-nome').toLowerCase();
                    const categoria = card.getAttribute('data-categoria').toLowerCase();
                    
                    if (nome.includes(termo) || categoria.includes(termo)) {
                        card.classList.remove('hidden');
                    } else {
                        card.classList.add('hidden');
                    }
                });
            });
            
            // Evento para limpar busca
            clearButton.addEventListener('click', function() {
                searchInput.value = '';
                this.classList.add('hidden');
                
                // Mostrar todos os produtos
                const cards = document.querySelectorAll('.produto-adicionar-card');
                cards.forEach(card => card.classList.remove('hidden'));
                
                searchInput.focus();
            });
        }
    }
}

// Aumentar quantidade ao adicionar produto
function aumentarQuantidadeAdicionar(id) {
    const quantidadeElement = document.getElementById(`adicionar-quantidade-${id}`);
    if (!quantidadeElement) return;
    
    let quantidade = parseInt(quantidadeElement.innerText);
    quantidade++;
    quantidadeElement.innerText = quantidade;
}

// Diminuir quantidade ao adicionar produto
function diminuirQuantidadeAdicionar(id) {
    const quantidadeElement = document.getElementById(`adicionar-quantidade-${id}`);
    if (!quantidadeElement) return;
    
    let quantidade = parseInt(quantidadeElement.innerText);
    if (quantidade > 0) {
        quantidade--;
        quantidadeElement.innerText = quantidade;
    }
}

// Confirmar adição de produtos ao pedido
function confirmarAdicionarProdutos() {
    if (!pedidoEmEdicao) return;
    
    // Recuperar quantidades adicionadas
    const produtosCards = document.querySelectorAll('#adicionarProdutosLista .bg-white.border');
    const novosProdutos = { ...pedidoEmEdicao.produtos };
    let alteracoesFeitas = false;
    
    produtosCards.forEach(card => {
        const quantidadeElement = card.querySelector('[id^="adicionar-quantidade-"]');
        
        // Verificar se o elemento existe antes de acessar suas propriedades
        if (!quantidadeElement) return;
        
        const id = quantidadeElement.id.replace('adicionar-quantidade-', '');
        const quantidade = parseInt(quantidadeElement.innerText);
        
        // Adicionar apenas se a quantidade for maior que zero
        if (quantidade > 0) {
            novosProdutos[id] = quantidade;
            alteracoesFeitas = true;
        } else if (quantidade === 0 && novosProdutos[id]) {
            // Remover o produto se a quantidade for zero
            delete novosProdutos[id];
            alteracoesFeitas = true;
        }
    });
    
    if (alteracoesFeitas) {
        // Atualizar o pedido em edição
        pedidoEmEdicao.produtos = novosProdutos;
        
        // Recalcular valor total e total de itens
        recalcularPedido();
        
        // Atualizar a visualização
        atualizarVisualizacaoProdutos();
    }
    
    // Fechar o modal
    fecharModalAdicionarProduto();
}

// Recalcular valores do pedido após alterações
function recalcularPedido() {
    if (!pedidoEmEdicao) return;
    
    // Verificar se estamos em uma página com os produtos carregados
    if (Object.keys(precoProdutos).length === 0) {
        // Se não temos preços carregados, tentamos carregá-los novamente
        carregarPrecosProdutos();
        
        // Se mesmo assim não tiver preços, vamos alertar no console
        if (Object.keys(precoProdutos).length === 0) {
            console.warn('Não foi possível carregar os preços dos produtos');
        }
    }
    
    // Calcular total de itens
    let totalItens = 0;
    let valorTotal = 0;
    
    for (const id in pedidoEmEdicao.produtos) {
        const quantidade = pedidoEmEdicao.produtos[id];
        totalItens += quantidade;
        
        // Calcular valor total
        const preco = precoProdutos[id] || 0;
        valorTotal += quantidade * preco;
        
        if (!preco) {
            console.warn(`Preço não encontrado para o produto ID ${id}`);
        }
    }
    
    // Atualizar os valores no pedido
    pedidoEmEdicao.itens = totalItens;
    pedidoEmEdicao.valor_total = valorTotal;
    
    // Atualizar visualização
    const totalItensElement = document.getElementById('detalhesTotalItens');
    const valorTotalElement = document.getElementById('detalhesValorTotal');
    
    if (totalItensElement) {
        totalItensElement.textContent = totalItens;
    }
    
    if (valorTotalElement) {
        valorTotalElement.textContent = `R$ ${valorTotal.toFixed(2).replace('.', ',')}`;
    }
}

// Atualizar a visualização da lista de produtos
function atualizarVisualizacaoProdutos() {
    if (!pedidoEmEdicao) return;
    
    const produtosListaElement = document.getElementById('detalhesProdutosLista');
    buscarInfoProdutos(pedidoEmEdicao.produtos, produtosListaElement);
}

// Salvar alterações no pedido
function salvarAlteracoesPedido() {
    if (!pedidoEmEdicao) return;
    
    // Verificar e obter valores dos campos editáveis
    const mesaContainer = document.getElementById('editMesaContainer');
    const statusContainer = document.getElementById('editStatusContainer');
    const pagoContainer = document.getElementById('editPagoContainer');
    
    // Garantir que os tipos de dados correspondam aos da tabela
    const dadosAtualizados = {
        id: parseInt(pedidoEmEdicao.id),
        produtos: JSON.stringify(pedidoEmEdicao.produtos), // Converter para string JSON
        valor_total: parseFloat(pedidoEmEdicao.valor_total || 0).toFixed(2), // Garantir formato decimal(10,2)
        itens: parseInt(pedidoEmEdicao.itens || 0)
    };
    
    // Verificar se os campos de edição estão visíveis e obter seus valores
    if (mesaContainer && !mesaContainer.classList.contains('hidden')) {
        const editMesa = document.getElementById('editMesa');
        if (editMesa) {
            dadosAtualizados.mesa = editMesa.value.trim();
        }
    }
    
    if (statusContainer && !statusContainer.classList.contains('hidden')) {
        const editStatus = document.getElementById('editStatus');
        if (editStatus) {
            dadosAtualizados.status = editStatus.value;
        }
    }
    
    if (pagoContainer && !pagoContainer.classList.contains('hidden')) {
        const editPago = document.getElementById('editPago');
        if (editPago) {
            dadosAtualizados.pago = parseInt(editPago.value);
        }
    }
    
    console.log('Salvando alterações:', dadosAtualizados);
    
    // Enviar alterações para o servidor
    fetch('/pedidos/update', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(dadosAtualizados)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Alterações salvas com sucesso!');
            
            // Recarregar a página para mostrar as alterações
            window.location.reload();
        } else {
            alert(`Erro ao salvar alterações: ${data.message}`);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar alterações. Tente novamente.');
    });
}

// Abrir modal de novo pedido
function abrirModalNovoPedido() {
    const modal = document.getElementById('novoPedidoModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Resetar quantidades
    resetarQuantidades();
    atualizarTotal();
}

// Fechar modal de novo pedido
function fecharModalNovoPedido() {
    const modal = document.getElementById('novoPedidoModal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
}

// Aumentar quantidade de um produto
function aumentarQuantidade(id) {
    const quantidadeElement = document.getElementById(`quantidade-${id}`);
    let quantidade = parseInt(quantidadeElement.innerText);
    quantidade++;
    quantidadeElement.innerText = quantidade;
    
    // Armazenar a quantidade
    produtoQuantidades[id] = quantidade;
    
    // Atualizar o total
    atualizarTotal();
}

// Diminuir quantidade de um produto
function diminuirQuantidade(id) {
    const quantidadeElement = document.getElementById(`quantidade-${id}`);
    let quantidade = parseInt(quantidadeElement.innerText);
    if (quantidade > 0) {
        quantidade--;
        quantidadeElement.innerText = quantidade;
        
        // Armazenar a quantidade
        produtoQuantidades[id] = quantidade;
        
        // Atualizar o total
        atualizarTotal();
    }
}

// Resetar todas as quantidades
function resetarQuantidades() {
    // Limpar objeto de quantidades
    Object.keys(produtoQuantidades).forEach(key => {
        delete produtoQuantidades[key];
    });
    
    // Zerar todos os contadores na interface
    document.querySelectorAll('[id^="quantidade-"]').forEach(element => {
        element.innerText = '0';
    });
    
    // Inicializar quantidades novamente
    inicializarQuantidades();
    
    // Atualizar contador de itens
    atualizarContadorItens();
}

// Atualizar o total do pedido
function atualizarTotal() {
    let total = 0;
    
    Object.keys(produtoQuantidades).forEach(id => {
        if (produtoQuantidades[id] > 0) {
        const quantidade = produtoQuantidades[id];
        const preco = precoProdutos[id] || 0;
            
            // Adicionar ao total
            const subtotal = quantidade * preco;
            total += subtotal;
            
            console.log(`Item ${id}: ${quantidade} x R$${preco} = R$${subtotal}`);
        }
    });
    
    console.log(`Total do pedido: R$${total}`);
    
    // Atualizar o valor na interface
    const totalElement = document.getElementById('totalPedido');
    if (totalElement) {
        totalElement.textContent = `R$ ${total.toFixed(2).replace('.', ',')}`;
    }
    
    // Atualizar contador de itens no modal
    atualizarContadorItens();
}

// Atualizar contador de itens no modal
function atualizarContadorItens() {
    const totalItens = calcularTotalItens();
    
    // Adiciona um elemento para exibir o total de itens caso não exista
    let contadorItensElement = document.getElementById('totalItensModal');
    
    if (!contadorItensElement) {
        const divTotal = document.querySelector('.border-t.border-gray-200 .font-medium.text-text-dark').parentNode;
        
        if (divTotal) {
            const contadorSpan = document.createElement('span');
            contadorSpan.id = 'totalItensModal';
            contadorSpan.className = 'text-sm text-text-light ml-2';
            divTotal.appendChild(contadorSpan);
            contadorItensElement = contadorSpan;
        }
    }
    
    if (contadorItensElement) {
        contadorItensElement.textContent = totalItens > 0 ? `(${totalItens} ${totalItens === 1 ? 'item' : 'itens'})` : '';
    }
}

// Calcular quantidade total de itens no pedido
function calcularTotalItens() {
    let totalItens = 0;
    
    Object.keys(produtoQuantidades).forEach(id => {
        if (produtoQuantidades[id] > 0) {
            totalItens += produtoQuantidades[id];
        }
    });
    
    return totalItens;
}

// Finalizar o pedido
function finalizarPedido() {
    // Validar se há produtos selecionados
    const temProdutos = Object.values(produtoQuantidades).some(quantidade => quantidade > 0);
    if (!temProdutos) {
        alert('Adicione pelo menos um item ao pedido!');
        return;
    }
    
    // Validar se a mesa foi informada
    const mesa = document.getElementById('mesa').value;
    if (!mesa) {
        alert('Informe o número da mesa!');
        return;
    }
    
    // Preparar dados do pedido
    const produtosSelecionados = {};
    Object.keys(produtoQuantidades).forEach(id => {
        if (produtoQuantidades[id] > 0) {
            produtosSelecionados[id] = produtoQuantidades[id];
        }
    });
    
    // Calcular total do valor
    let total = 0;
    Object.keys(produtosSelecionados).forEach(id => {
        total += produtosSelecionados[id] * (precoProdutos[id] || 0);
    });
    
    // Calcular total de itens
    const totalItens = calcularTotalItens();
    
    // Preparar objeto para envio
    const pedido = {
        mesa: mesa,
        produtos: produtosSelecionados,
        data: new Date().toISOString(),
        valor_total: total,
        status: 'preparando',
        pago: 0,
        itens: totalItens // Garantindo que o total de itens seja enviado
    };
    
    // Enviar pedido via fetch (simulado)
    console.log('Enviando pedido:', pedido);
    console.log(`Total de itens: ${totalItens}`);
    
    // Em um ambiente real, enviaríamos via fetch
    fetch('/pedidos/create', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(pedido)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Armazenar temporariamente o total de itens para garantir que apareça após o reload
            sessionStorage.setItem('ultimoPedidoItens', totalItens);
            fecharModalNovoPedido();
            mostrarModalSucesso();
        } else {
            alert('Erro ao criar pedido: ' + data.message);
        }
        console.log(data)
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao criar pedido. Tente novamente.');
    });
    
    
    // Simulação (para demonstração)
    // Armazenar temporariamente o total de itens para garantir que apareça após o reload
    sessionStorage.setItem('ultimoPedidoItens', totalItens);
    
    setTimeout(() => {
        fecharModalNovoPedido();
        mostrarModalSucesso();
    }, 500);
}

// Mostrar modal de sucesso
function mostrarModalSucesso() {
    const modal = document.getElementById('sucessoModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

// Fechar modal de sucesso
function fecharModalSucesso() {
    const modal = document.getElementById('sucessoModal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
    
    // Recarregar a página para mostrar o novo pedido
    window.location.reload();
}

// Função para verificar se há um pedido recém-criado
function verificarPedidoRecemCriado() {
    const ultimoPedidoItens = sessionStorage.getItem('ultimoPedidoItens');
    
    if (ultimoPedidoItens) {
        // Encontrar o card do pedido mais recente (primeiro da lista)
        const primeiroPedidoCard = document.querySelector('.bg-white.rounded-xl.shadow-md');
        
        if (primeiroPedidoCard) {
            // Encontrar o span de itens
            const itemSpan = primeiroPedidoCard.querySelector('.text-xs.text-text-light.bg-gray-100:first-child');
            if (itemSpan) {
                // Atualizar o texto para mostrar a quantidade correta de itens
                const plural = parseInt(ultimoPedidoItens) !== 1 ? 'itens' : 'item';
                itemSpan.textContent = `${ultimoPedidoItens} ${plural}`;
                
                // Adicionar classe para destacar brevemente
                itemSpan.classList.add('animate-pulse', 'bg-accent/20');
                setTimeout(() => {
                    itemSpan.classList.remove('animate-pulse', 'bg-accent/20');
                }, 3000);
            }
        }
        
        // Limpar o sessionStorage para não afetar futuros carregamentos
        sessionStorage.removeItem('ultimoPedidoItens');
    }
}

// Função para carregar os detalhes de um pedido específico
function carregarDetalhesPedido(pedidoId) {
    console.log(`Carregando detalhes do pedido ID: ${pedidoId}`);
    
    // Fazer requisição para obter detalhes do pedido
    fetch(`/pedidos/getById`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: pedidoId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            exibirDetalhesPedido(data.data);
        } else {
            alert(`Erro ao carregar detalhes: ${data.message}`);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao carregar detalhes do pedido. Tente novamente.');
    });
}

// Função para exibir os detalhes do pedido
function exibirDetalhesPedido(pedido) {
    console.log('Detalhes do pedido:', pedido);
    
    // Armazenar o pedido em edição
    pedidoEmEdicao = pedido;
    
    // Atualizar o conteúdo do modal com os detalhes do pedido
    document.getElementById('detalhesPedidoId').textContent = `#${pedido.id}`;
    document.getElementById('detalhesMesa').textContent = pedido.mesa;
    
    // Formatar e exibir a data
    const dataFormatada = pedido.data_pedido 
        ? new Date(pedido.data_pedido).toLocaleString('pt-BR')
        : new Date().toLocaleString('pt-BR');
    document.getElementById('detalhesData').textContent = dataFormatada;
    
    // Configurar status com a classe correta
    const statusElement = document.getElementById('detalhesStatus');
    statusElement.textContent = pedido.status ? pedido.status.charAt(0).toUpperCase() + pedido.status.slice(1) : 'Preparando';
    
    // Definir a classe do status
    let statusClass = '';
    if (pedido.status === 'preparando') {
        statusClass = 'bg-amber-100 text-amber-800 border-amber-200';
    } else if (pedido.status === 'pronto') {
        statusClass = 'bg-emerald-100 text-emerald-800 border-emerald-200';
    } else if (pedido.status === 'entregue') {
        statusClass = 'bg-secondary/20 text-secondary-dark border-secondary/30';
    } else if (pedido.status === 'cancelado') {
        statusClass = 'bg-danger/20 text-danger-dark border-danger/30';
    } else {
        statusClass = 'bg-gray-100 text-gray-800 border-gray-200';
    }
    statusElement.className = `inline-block px-3 py-1 rounded-full text-xs font-medium border ${statusClass}`;
    
    // Configurar status de pagamento
    const pagamentoElement = document.getElementById('detalhesPago');
    const pago = pedido.pago == 1;
    pagamentoElement.textContent = pago ? 'Pago' : 'Não pago';
    
    const pagamentoClass = pago
        ? 'bg-success/20 text-success-dark border-success/30'
        : 'bg-danger/20 text-danger-dark border-danger/30';
    pagamentoElement.className = `inline-block px-3 py-1 rounded-full text-xs font-medium border ${pagamentoClass}`;
    
    // Exibir quantidade de itens
    document.getElementById('detalhesTotalItens').textContent = pedido.itens || '0';
    
    // Formatar e exibir valor total
    const valorTotal = parseFloat(pedido.valor_total || 0).toFixed(2).replace('.', ',');
    document.getElementById('detalhesValorTotal').textContent = `R$ ${valorTotal}`;
    
    // Exibir lista de produtos
    const produtosListaElement = document.getElementById('detalhesProdutosLista');
    
    if (pedido.produtos && Object.keys(pedido.produtos).length > 0) {
        // Buscar informações dos produtos para exibir nomes em vez de apenas IDs
        buscarInfoProdutos(pedido.produtos, produtosListaElement);
    } else {
        produtosListaElement.innerHTML = '<p class="text-text-light text-center py-4">Nenhum produto encontrado</p>';
    }
    
    // Esconder os campos de edição
    document.getElementById('editMesaContainer').classList.add('hidden');
    document.getElementById('editStatusContainer').classList.add('hidden');
    document.getElementById('editPagoContainer').classList.add('hidden');
    
    // Exibir o modal
    const modal = document.getElementById('detalhesPedidoModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Configurar evento para fechar o modal
    const btnFecharDetalhes = document.getElementById('btnFecharDetalhes');
    const btnFecharModalDetalhes = document.getElementById('btnFecharModalDetalhes');
    
    btnFecharDetalhes.onclick = fecharModalDetalhes;
    btnFecharModalDetalhes.onclick = fecharModalDetalhes;
}

// Fechar modal de detalhes
function fecharModalDetalhes() {
    const modal = document.getElementById('detalhesPedidoModal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
}

// Função para buscar informações dos produtos
function buscarInfoProdutos(produtos, containerElement) {
    // Criar uma mensagem de carregamento
    containerElement.innerHTML = '<p class="text-text-light text-center py-4">Carregando produtos...</p>';
    
    // Inicializar o array para armazenar as informações de produtos
    const produtosInfo = [];
    
    // Buscar informações dos produtos usando os dados que já temos no DOM
    // Extraindo os produtos da tela modal de "Novo Pedido"
    const produtosCards = document.querySelectorAll('.produto-card');
    
    // Mapear os produtos do DOM para um objeto indexado por ID
    const produtosMap = {};
    produtosCards.forEach(card => {
        const id = card.getAttribute('data-id');
        const nome = card.getAttribute('data-nome') || `Produto ${id}`;
        const preco = card.getAttribute('data-preco') || 'R$ 0,00';
        
        produtosMap[id] = { id, nome, preco };
    });
    
    // Criar a tabela de produtos
    let html = `
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-2 py-3 text-left text-xs font-medium text-text-light uppercase tracking-wider">Produto</th>
                    <th class="px-2 py-3 text-center text-xs font-medium text-text-light uppercase tracking-wider">Preço Unit.</th>
                    <th class="px-2 py-3 text-center text-xs font-medium text-text-light uppercase tracking-wider">Qtd</th>
                    <th class="px-2 py-3 text-right text-xs font-medium text-text-light uppercase tracking-wider">Subtotal</th>
                    ${isAdmin ? '<th class="px-2 py-3 text-center text-xs font-medium text-text-light uppercase tracking-wider">Ações</th>' : ''}
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
    `;
    
    // Variável para armazenar o total
    let total = 0;
    
    // Adicionar cada produto à tabela
    for (const produtoId in produtos) {
        const quantidade = produtos[produtoId];
        const produtoInfo = produtosMap[produtoId] || { 
            id: produtoId, 
            nome: `Produto ${produtoId}`, 
            preco: 'R$ 0,00' 
        };
        
        // Calcular subtotal (preço unitário * quantidade)
        const precoNumerico = parseFloat(produtoInfo.preco.replace('R$ ', '').replace('.', '').replace(',', '.')) || 0;
        const subtotal = precoNumerico * quantidade;
        total += subtotal;
        
        // Formatar o preço e o subtotal
        const subtotalFormatado = subtotal.toFixed(2).replace('.', ',');
        
        html += `
            <tr>
                <td class="px-2 py-3 text-sm text-text-dark">${produtoInfo.nome}</td>
                <td class="px-2 py-3 text-sm text-text-dark text-center">${produtoInfo.preco}</td>
                <td class="px-2 py-3 text-sm text-text-dark text-center">${quantidade}</td>
                <td class="px-2 py-3 text-sm text-primary font-medium text-right">R$ ${subtotalFormatado}</td>
                ${isAdmin ? `
                <td class="px-2 py-3 text-sm text-center">
                    <button 
                        onclick="removerProdutoDoPedido('${produtoId}')" 
                        class="text-danger hover:text-danger-dark transition-colors duration-200"
                        title="Remover produto">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </td>
                ` : ''}
            </tr>
        `;
    }
    
    // Adicionar linha de total
    html += `
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="${isAdmin ? '4' : '3'}" class="px-2 py-3 text-sm text-text-dark font-medium text-right">Total:</td>
                    <td class="px-2 py-3 text-sm text-primary font-bold text-right">R$ ${total.toFixed(2).replace('.', ',')}</td>
                </tr>
            </tfoot>
        </table>
    `;
    
    containerElement.innerHTML = html;
}

// Função para remover um produto do pedido
function removerProdutoDoPedido(produtoId) {
    if (!pedidoEmEdicao || !pedidoEmEdicao.produtos) return;
    
    // Confirmar remoção
    if (!confirm(`Deseja realmente remover este produto do pedido?`)) {
        return;
    }
    
    // Remover o produto do pedido
    if (pedidoEmEdicao.produtos[produtoId]) {
        delete pedidoEmEdicao.produtos[produtoId];
        
        // Recalcular valor total e total de itens
        recalcularPedido();
        
        // Atualizar a visualização
        atualizarVisualizacaoProdutos();
    }
} 