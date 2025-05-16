<!-- Seção de Resumo do Caixa -->
<div class="container mx-auto px-4 py-4 pb-8">
    <?php if (isset($dadosCaixa['erro'])): ?>
        <div class="bg-danger/10 border border-danger/30 text-danger-dark rounded-lg p-4 mb-8">
            <p class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <?= $dadosCaixa['erro'] ?>
            </p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <!-- Status do Caixa -->
            <div class="bg-white rounded-xl shadow-md p-4 border-l-4 <?= $dadosCaixa['resumo']['caixa_status'] === 'aberto' ? 'border-l-success' : 'border-l-danger' ?>">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-sm font-medium text-text-light">Status do Caixa</h3>
                        <p class="text-xl font-bold mt-1 <?= $dadosCaixa['resumo']['caixa_status'] === 'aberto' ? 'text-success' : 'text-danger' ?>">
                            <?= $dadosCaixa['resumo']['caixa_status'] === 'aberto' ? 'Aberto' : 'Fechado' ?>
                        </p>
                        <?php if ($dadosCaixa['resumo']['caixa_status'] === 'aberto' && isset($dadosCaixa['resumo']['data_abertura'])): ?>
                            <p class="text-xs text-text-light mt-1">
                                Aberto em: <?= date('d/m/Y H:i', strtotime($dadosCaixa['resumo']['data_abertura'])) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="bg-<?= $dadosCaixa['resumo']['caixa_status'] === 'aberto' ? 'success' : 'danger' ?>/20 p-2 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-<?= $dadosCaixa['resumo']['caixa_status'] === 'aberto' ? 'success' : 'danger' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $dadosCaixa['resumo']['caixa_status'] === 'aberto' ? 'M5 13l4 4L19 7' : 'M6 18L18 6M6 6l12 12' ?>" />
                        </svg>
                    </div>
                </div>
            </div>
            
            <!-- Saldo em Caixa -->
            <div class="bg-white rounded-xl shadow-md p-4 border-l-4 border-l-primary">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-sm font-medium text-text-light">Saldo em Caixa (Dinheiro)</h3>
                        <p class="text-xl font-bold text-primary mt-1">
                            <?= isset($dadosCaixa['resumo']['saldo_em_caixa_formatado']) ? $dadosCaixa['resumo']['saldo_em_caixa_formatado'] : 'R$ 0,00' ?>
                        </p>
                        <p class="text-xs text-text-light mt-1">
                            Valor inicial: <?= isset($dadosCaixa['resumo']['valor_inicial_formatado']) ? $dadosCaixa['resumo']['valor_inicial_formatado'] : 'R$ 0,00' ?>
                        </p>
                    </div>
                    <div class="bg-primary/20 p-2 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
            </div>
            
            <!-- Vendas Hoje -->
            <div class="bg-white rounded-xl shadow-md p-4 border-l-4 border-l-success">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-sm font-medium text-text-light">Vendas de Hoje</h3>
                        <p class="text-xl font-bold text-success mt-1">
                            <?= isset($dadosCaixa['resumo']['total_vendas_hoje_formatado']) ? $dadosCaixa['resumo']['total_vendas_hoje_formatado'] : 'R$ 0,00' ?>
                        </p>
                        <p class="text-xs text-text-light mt-1">
                            Total do período: <?= isset($dadosCaixa['resumo']['total_vendas_periodo_formatado']) ? $dadosCaixa['resumo']['total_vendas_periodo_formatado'] : 'R$ 0,00' ?>
                        </p>
                    </div>
                    <div class="bg-success/20 p-2 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
            
            <!-- Sangrias e Suprimentos -->
            <div class="bg-white rounded-xl shadow-md p-4 border-l-4 border-l-secondary">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-sm font-medium text-text-light">Sangrias / Suprimentos</h3>
                        <div class="flex items-center mt-1">
                            <span class="text-danger font-medium mr-2">
                                -<?= isset($dadosCaixa['resumo']['total_sangrias_formatado']) ? $dadosCaixa['resumo']['total_sangrias_formatado'] : 'R$ 0,00' ?>
                            </span>
                            <span class="text-text-light">/</span>
                            <span class="text-success font-medium ml-2">
                                +<?= isset($dadosCaixa['resumo']['total_suprimentos_formatado']) ? $dadosCaixa['resumo']['total_suprimentos_formatado'] : 'R$ 0,00' ?>
                            </span>
                        </div>
                        <p class="text-xs text-text-light mt-1">
                            Saldo: <?= 'R$ ' . number_format((isset($dadosCaixa['resumo']['total_suprimentos']) ? $dadosCaixa['resumo']['total_suprimentos'] : 0) - (isset($dadosCaixa['resumo']['total_sangrias']) ? $dadosCaixa['resumo']['total_sangrias'] : 0), 2, ',', '.') ?>
                        </p>
                    </div>
                    <div class="bg-secondary/20 p-2 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Detalhamento por Métodos de Pagamento -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-text-dark mb-4">Métodos de Pagamento</h3>
                <div class="space-y-4">
                    <!-- Dinheiro -->
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <div class="flex justify-between mb-2">
                            <span class="text-text-dark font-medium">Dinheiro</span>
                            <span class="text-primary font-bold"><?= isset($dadosCaixa['resumo']['total_dinheiro_formatado']) ? $dadosCaixa['resumo']['total_dinheiro_formatado'] : 'R$ 0,00' ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <?php 
                            $totalPagamentos = 
                                (isset($dadosCaixa['resumo']['total_dinheiro']) ? $dadosCaixa['resumo']['total_dinheiro'] : 0) + 
                                (isset($dadosCaixa['resumo']['total_cartao_credito']) ? $dadosCaixa['resumo']['total_cartao_credito'] : 0) + 
                                (isset($dadosCaixa['resumo']['total_cartao_debito']) ? $dadosCaixa['resumo']['total_cartao_debito'] : 0) + 
                                (isset($dadosCaixa['resumo']['total_pix']) ? $dadosCaixa['resumo']['total_pix'] : 0);
                            
                            $percentualDinheiro = $totalPagamentos > 0 
                                ? ((isset($dadosCaixa['resumo']['total_dinheiro']) ? $dadosCaixa['resumo']['total_dinheiro'] : 0) / $totalPagamentos) * 100 
                                : 0;
                            ?>
                            <div class="bg-blue-500 h-2 rounded-full" style="width: <?= $percentualDinheiro ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- Cartão de Crédito -->
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <div class="flex justify-between mb-2">
                            <span class="text-text-dark font-medium">Cartão de Crédito</span>
                            <span class="text-primary font-bold"><?= isset($dadosCaixa['resumo']['total_cartao_credito_formatado']) ? $dadosCaixa['resumo']['total_cartao_credito_formatado'] : 'R$ 0,00' ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <?php 
                            $percentualCredito = $totalPagamentos > 0 
                                ? ((isset($dadosCaixa['resumo']['total_cartao_credito']) ? $dadosCaixa['resumo']['total_cartao_credito'] : 0) / $totalPagamentos) * 100 
                                : 0;
                            ?>
                            <div class="bg-green-500 h-2 rounded-full" style="width: <?= $percentualCredito ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- Cartão de Débito -->
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <div class="flex justify-between mb-2">
                            <span class="text-text-dark font-medium">Cartão de Débito</span>
                            <span class="text-primary font-bold"><?= isset($dadosCaixa['resumo']['total_cartao_debito_formatado']) ? $dadosCaixa['resumo']['total_cartao_debito_formatado'] : 'R$ 0,00' ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <?php 
                            $percentualDebito = $totalPagamentos > 0 
                                ? ((isset($dadosCaixa['resumo']['total_cartao_debito']) ? $dadosCaixa['resumo']['total_cartao_debito'] : 0) / $totalPagamentos) * 100 
                                : 0;
                            ?>
                            <div class="bg-purple-500 h-2 rounded-full" style="width: <?= $percentualDebito ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- PIX -->
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <div class="flex justify-between mb-2">
                            <span class="text-text-dark font-medium">PIX</span>
                            <span class="text-primary font-bold"><?= isset($dadosCaixa['resumo']['total_pix_formatado']) ? $dadosCaixa['resumo']['total_pix_formatado'] : 'R$ 0,00' ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <?php 
                            $percentualPix = $totalPagamentos > 0 
                                ? ((isset($dadosCaixa['resumo']['total_pix']) ? $dadosCaixa['resumo']['total_pix'] : 0) / $totalPagamentos) * 100 
                                : 0;
                            ?>
                            <div class="bg-yellow-500 h-2 rounded-full" style="width: <?= $percentualPix ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Últimos Fechamentos de Caixa -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-text-dark mb-4">Últimos Fechamentos</h3>
                <?php if (isset($dadosCaixa['fechamentos']) && !empty($dadosCaixa['fechamentos'])): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-text-light uppercase tracking-wider">ID</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-text-light uppercase tracking-wider">Data</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-text-light uppercase tracking-wider">Inicial</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-text-light uppercase tracking-wider">Final</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-text-light uppercase tracking-wider">Diferença</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php foreach ($dadosCaixa['fechamentos'] as $fechamento): ?>
                                    <?php if (isset($fechamento['status']) && $fechamento['status'] === 'fechado'): ?>
                                        <tr>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-text-dark">#<?= $fechamento['id'] ?></td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-text-dark">
                                                <?= isset($fechamento['data_fechamento']) ? date('d/m/Y', strtotime($fechamento['data_fechamento'])) : '-' ?>
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-text-dark text-right">
                                                R$ <?= number_format($fechamento['valor_inicial'], 2, ',', '.') ?>
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-text-dark text-right">
                                                <?php if (isset($fechamento['valor_final'])): ?>
                                                    R$ <?= number_format($fechamento['valor_final'], 2, ',', '.') ?>
                                                <?php else: ?>
                                                    <span class="text-text-light">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-right">
                                                <?php if (isset($fechamento['diferenca'])): ?>
                                                    <span class="<?= floatval($fechamento['diferenca']) >= 0 ? 'text-success' : 'text-danger' ?> font-medium">
                                                        R$ <?= number_format($fechamento['diferenca'], 2, ',', '.') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-text-light">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="bg-gray-50 p-4 rounded-lg text-center">
                        <p class="text-text-light">Nenhum fechamento de caixa encontrado</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div> 