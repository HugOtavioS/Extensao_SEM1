-- Tabela para armazenar abertura e fechamento de caixa
CREATE TABLE IF NOT EXISTS `tb_fechamentos_caixa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `data_abertura` datetime NOT NULL,
  `usuario_abertura` varchar(255) NOT NULL,
  `data_fechamento` datetime DEFAULT NULL,
  `usuario_fechamento` varchar(255) DEFAULT NULL,
  `valor_inicial` decimal(10,2) NOT NULL DEFAULT 0.00,
  `valor_final` decimal(10,2) DEFAULT NULL,
  `valor_esperado` decimal(10,2) DEFAULT NULL,
  `diferenca` decimal(10,2) DEFAULT NULL,
  `observacao` text DEFAULT NULL,
  `status` enum('aberto','fechado') NOT NULL DEFAULT 'aberto',
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `usuario_abertura` (`usuario_abertura`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabela para armazenar movimentos do caixa (vendas, sangrias, suprimentos, etc)
CREATE TABLE IF NOT EXISTS `tb_movimentos_caixa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_fechamento` int(11) NOT NULL,
  `tipo` enum('venda','sangria','suprimento','cancelamento') NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `metodo_pagamento` enum('dinheiro','cartao_credito','cartao_debito','pix') NOT NULL DEFAULT 'dinheiro',
  `observacao` text DEFAULT NULL,
  `data_hora` datetime NOT NULL,
  `usuario` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_fechamento` (`id_fechamento`),
  KEY `tipo` (`tipo`),
  KEY `metodo_pagamento` (`metodo_pagamento`),
  CONSTRAINT `fk_movimento_fechamento` FOREIGN KEY (`id_fechamento`) REFERENCES `tb_fechamentos_caixa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Alteração na tabela de pedidos para adicionar referência ao fechamento de caixa
ALTER TABLE `tb_pedidos` 
ADD COLUMN `id_fechamento` int(11) DEFAULT NULL,
ADD KEY `id_fechamento` (`id_fechamento`),
ADD CONSTRAINT `fk_pedido_fechamento` FOREIGN KEY (`id_fechamento`) REFERENCES `tb_fechamentos_caixa` (`id`) ON DELETE SET NULL ON UPDATE CASCADE; 