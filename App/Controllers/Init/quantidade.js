function aumentarQuantidade(id) {
    const quantidadeElement = document.getElementById(`quantidade-${id}`);
    let quantidade = parseInt(quantidadeElement.innerText);
    quantidade++;
    quantidadeElement.innerText = quantidade;
}

function diminuirQuantidade(id) {
    const quantidadeElement = document.getElementById(`quantidade-${id}`);
    let quantidade = parseInt(quantidadeElement.innerText);
    if (quantidade > 0) {
        quantidade--;
        quantidadeElement.innerText = quantidade;
    }
}

function showSuccessModal() {
    const modal = document.getElementById('successModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function showErrorModal(message) {
    const modal = document.getElementById('errorModal');
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = message;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeErrorModal() {
    const modal = document.getElementById('errorModal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
}

function finalizarPedido() {
    // Objeto para armazenar os produtos e suas quantidades
    const pedido = {};
    
    // Busca todos os elementos que começam com 'quantidade-'
    document.querySelectorAll('[id^="quantidade-"]').forEach(element => {
        const id = element.id.replace('quantidade-', '');
        const quantidade = parseInt(element.innerText);
        
        // Só inclui no pedido se a quantidade for maior que 0
        if (quantidade > 0) {
            pedido[id] = quantidade;
        }
    });
    console.log(pedido);
    // Verifica se há itens no pedido
    if (Object.keys(pedido).length === 0) {
        showErrorModal('Adicione pelo menos um item ao pedido!');
        return;
    }

    // Envia o pedido via POST
    fetch('/finalizar-pedido', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            produtos: pedido,
            data: new Date().toISOString()
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Limpa as quantidades
            document.querySelectorAll('[id^="quantidade-"]').forEach(element => {
                element.innerText = '0';
            });
            showSuccessModal();
        } else {
            showErrorModal('Erro ao finalizar pedido: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showErrorModal('Erro ao enviar pedido. Tente novamente.');
    });
}