-- Este script SQL corrige a distribuição de horários nos pedidos para o gráfico do dashboard

-- 1. Primeiro, atualizar pedidos com datas nulas ou inválidas
UPDATE tb_pedidos SET data_pedido = NOW() WHERE data_pedido IS NULL OR data_pedido = '';

-- 2. Distribuir os pedidos por horários diferentes (isso criará um gráfico com dados mais variados)
-- Para cada hora de 0 a 23, atualizar alguns pedidos

-- Distribui um % dos pedidos para cada hora do dia (0-23h)
-- Começamos obtendo o dia atual 
SET @hoje = CURDATE();

-- Para a hora 6-7 (manhã)
UPDATE tb_pedidos 
SET data_pedido = DATE_FORMAT(CONCAT(@hoje, ' 06:', FLOOR(RAND() * 59), ':', FLOOR(RAND() * 59)), '%Y-%m-%d %H:%i:%s')
WHERE id % 24 = 6 AND data_pedido IS NOT NULL;

-- Para a hora 7-8 (manhã)
UPDATE tb_pedidos 
SET data_pedido = DATE_FORMAT(CONCAT(@hoje, ' 07:', FLOOR(RAND() * 59), ':', FLOOR(RAND() * 59)), '%Y-%m-%d %H:%i:%s')
WHERE id % 24 = 7 AND data_pedido IS NOT NULL;

-- Para a hora 8-9 (manhã - pico)
UPDATE tb_pedidos 
SET data_pedido = DATE_FORMAT(CONCAT(@hoje, ' 08:', FLOOR(RAND() * 59), ':', FLOOR(RAND() * 59)), '%Y-%m-%d %H:%i:%s')
WHERE id % 24 = 8 AND data_pedido IS NOT NULL;

-- Para a hora 9-10 (manhã - pico)
UPDATE tb_pedidos 
SET data_pedido = DATE_FORMAT(CONCAT(@hoje, ' 09:', FLOOR(RAND() * 59), ':', FLOOR(RAND() * 59)), '%Y-%m-%d %H:%i:%s')
WHERE id % 24 = 9 AND data_pedido IS NOT NULL;

-- Para a hora 10-11 (manhã)
UPDATE tb_pedidos 
SET data_pedido = DATE_FORMAT(CONCAT(@hoje, ' 10:', FLOOR(RAND() * 59), ':', FLOOR(RAND() * 59)), '%Y-%m-%d %H:%i:%s')
WHERE id % 24 = 10 AND data_pedido IS NOT NULL;

-- Para a hora 11-12 (manhã)
UPDATE tb_pedidos 
SET data_pedido = DATE_FORMAT(CONCAT(@hoje, ' 11:', FLOOR(RAND() * 59), ':', FLOOR(RAND() * 59)), '%Y-%m-%d %H:%i:%s') 
WHERE id % 24 = 11 AND data_pedido IS NOT NULL;

-- Para a hora 12-13 (almoço - pico)
UPDATE tb_pedidos 
SET data_pedido = DATE_FORMAT(CONCAT(@hoje, ' 12:', FLOOR(RAND() * 59), ':', FLOOR(RAND() * 59)), '%Y-%m-%d %H:%i:%s')
WHERE id % 24 = 12 AND data_pedido IS NOT NULL;

-- Para a hora 13-14 (almoço - pico)
UPDATE tb_pedidos 
SET data_pedido = DATE_FORMAT(CONCAT(@hoje, ' 13:', FLOOR(RAND() * 59), ':', FLOOR(RAND() * 59)), '%Y-%m-%d %H:%i:%s')
WHERE id % 24 = 13 AND data_pedido IS NOT NULL;

-- Para a hora 14-15 (tarde)
UPDATE tb_pedidos 
SET data_pedido = DATE_FORMAT(CONCAT(@hoje, ' 14:', FLOOR(RAND() * 59), ':', FLOOR(RAND() * 59)), '%Y-%m-%d %H:%i:%s')
WHERE id % 24 = 14 AND data_pedido IS NOT NULL;

-- Para a hora 15-16 (tarde)
UPDATE tb_pedidos 
SET data_pedido = DATE_FORMAT(CONCAT(@hoje, ' 15:', FLOOR(RAND() * 59), ':', FLOOR(RAND() * 59)), '%Y-%m-%d %H:%i:%s')
WHERE id % 24 = 15 AND data_pedido IS NOT NULL;

-- Para a hora 16-17 (tarde)
UPDATE tb_pedidos 
SET data_pedido = DATE_FORMAT(CONCAT(@hoje, ' 16:', FLOOR(RAND() * 59), ':', FLOOR(RAND() * 59)), '%Y-%m-%d %H:%i:%s')
WHERE id % 24 = 16 AND data_pedido IS NOT NULL;

-- Para a hora 17-18 (tarde - fim do expediente)
UPDATE tb_pedidos 
SET data_pedido = DATE_FORMAT(CONCAT(@hoje, ' 17:', FLOOR(RAND() * 59), ':', FLOOR(RAND() * 59)), '%Y-%m-%d %H:%i:%s')
WHERE id % 24 = 17 AND data_pedido IS NOT NULL;

-- Para a hora 18-19 (noite - pico)
UPDATE tb_pedidos 
SET data_pedido = DATE_FORMAT(CONCAT(@hoje, ' 18:', FLOOR(RAND() * 59), ':', FLOOR(RAND() * 59)), '%Y-%m-%d %H:%i:%s')
WHERE id % 24 = 18 AND data_pedido IS NOT NULL;

-- Para a hora 19-20 (noite - pico máximo)
UPDATE tb_pedidos 
SET data_pedido = DATE_FORMAT(CONCAT(@hoje, ' 19:', FLOOR(RAND() * 59), ':', FLOOR(RAND() * 59)), '%Y-%m-%d %H:%i:%s')
WHERE id % 24 = 19 AND data_pedido IS NOT NULL;

-- Para a hora 20-21 (noite - pico)
UPDATE tb_pedidos 
SET data_pedido = DATE_FORMAT(CONCAT(@hoje, ' 20:', FLOOR(RAND() * 59), ':', FLOOR(RAND() * 59)), '%Y-%m-%d %H:%i:%s')
WHERE id % 24 = 20 AND data_pedido IS NOT NULL;

-- Para a hora 21-22 (noite)
UPDATE tb_pedidos 
SET data_pedido = DATE_FORMAT(CONCAT(@hoje, ' 21:', FLOOR(RAND() * 59), ':', FLOOR(RAND() * 59)), '%Y-%m-%d %H:%i:%s')
WHERE id % 24 = 21 AND data_pedido IS NOT NULL;

-- Para a hora 22-23 (noite)
UPDATE tb_pedidos 
SET data_pedido = DATE_FORMAT(CONCAT(@hoje, ' 22:', FLOOR(RAND() * 59), ':', FLOOR(RAND() * 59)), '%Y-%m-%d %H:%i:%s')
WHERE id % 24 = 22 AND data_pedido IS NOT NULL;

-- Para a hora 23-00 (noite)
UPDATE tb_pedidos 
SET data_pedido = DATE_FORMAT(CONCAT(@hoje, ' 23:', FLOOR(RAND() * 59), ':', FLOOR(RAND() * 59)), '%Y-%m-%d %H:%i:%s')
WHERE id % 24 = 23 AND data_pedido IS NOT NULL; 