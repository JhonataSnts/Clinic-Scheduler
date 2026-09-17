# Decisões de Arquitetura - Sistema de Agendamento Multi-Tenant

Este documento registra as decisões de engenharia tomadas durante a modelagem do banco de dados.

## 1. Estratégia de Multi-Tenancy
* **Decisão:** Banco de dados único (Single Database) com isolamento por coluna `tenant_id`.
* **Justificativa:** Melhor custo-benefício de infraestrutura para o cenário de clínicas de estética, mantendo a facilidade de manutenção e updates de schema.

## 2. Integridade e Segurança (Constraints Compostas)
* **Problema:** Chaves estrangeiras simples (`customer_id -> id`) permitiam que um Tenant acessasse dados de outro em caso de bugs no código PHP (Vazamento de Dados Cruzados).
* **Solução:** Implementação de Foreign Keys Compostas na tabela `appointments` amarrando o `tenant_id` junto com a entidade pai (ex: `FOREIGN KEY (tenant_id, customer_id) REFERENCES customers(tenant_id, id)`).
* **Resultado:** O próprio motor InnoDB do MySQL barra qualquer tentativa de vínculo inter-tenant.

## 3. Ordem de Índices Compostos
* **Decisão:** Todo índice composto começa estritamente com a coluna `tenant_id` (ex: `idx_customers_tenant_name (tenant_id, name)`).
* **Justificativa:** O MySQL lê índices da esquerda para a direita. Colocar o `tenant_id` primeiro garante que o banco isole rapidamente a fatia da clínica antes de buscar o dado, garantindo performance e reaproveitamento do índice.
