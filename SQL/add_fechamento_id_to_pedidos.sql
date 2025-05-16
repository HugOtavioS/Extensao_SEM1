-- Add fechamento_caixa_id field to tb_pedidos table
ALTER TABLE tb_pedidos 
ADD COLUMN fechamento_caixa_id INT NULL DEFAULT NULL;

-- Add index for performance
CREATE INDEX idx_pedidos_fechamento ON tb_pedidos(fechamento_caixa_id);

-- Add optional foreign key (comment out if you don't want referential integrity)
-- ALTER TABLE tb_pedidos 
-- ADD CONSTRAINT fk_pedidos_fechamento 
-- FOREIGN KEY (fechamento_caixa_id) REFERENCES tb_fechamentos_caixa(id); 