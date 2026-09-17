# Arquitetura do Sistema de Agendamento Multi-Tenant

Este documento descreve uma proposta inicial de arquitetura para um sistema de agendamento de clínicas em PHP puro, usando a estratégia **Single Database** com isolamento lógico por coluna `tenant_id`.

## 1. Schema do Banco de Dados

Banco sugerido: **MySQL 8+**

Padrões gerais:

- Todas as tabelas de dados pertencentes a uma clínica devem possuir `tenant_id`.
- A tabela `tenants` não possui `tenant_id`, pois representa os próprios tenants.
- Usar `InnoDB` para suporte a chaves estrangeiras e transações.
- Usar `utf8mb4` para compatibilidade completa com caracteres especiais.
- Usar `created_at` e `updated_at` para rastreabilidade.
- Usar índices compostos começando por `tenant_id` para performance e isolamento.

```sql
CREATE TABLE tenants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_tenants_slug (slug),
    KEY idx_tenants_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(180) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'profissional', 'recepcionista') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_users_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    UNIQUE KEY uq_users_tenant_email (tenant_id, email),
    KEY idx_users_tenant_role (tenant_id, role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE professionals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    specialty VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_professionals_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT fk_professionals_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    UNIQUE KEY uq_professionals_tenant_user (tenant_id, user_id),
    KEY idx_professionals_tenant_specialty (tenant_id, specialty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(180) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_customers_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    KEY idx_customers_tenant_name (tenant_id, name),
    KEY idx_customers_tenant_phone (tenant_id, phone),
    KEY idx_customers_tenant_email (tenant_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_services_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    UNIQUE KEY uq_services_tenant_name (tenant_id, name),
    KEY idx_services_tenant_duration (tenant_id, duration_minutes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    professional_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    date_time DATETIME NOT NULL,
    status ENUM('scheduled', 'confirmed', 'cancelled', 'completed', 'no_show') NOT NULL DEFAULT 'scheduled',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_appointments_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT fk_appointments_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT fk_appointments_professional
        FOREIGN KEY (professional_id) REFERENCES professionals(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT fk_appointments_service
        FOREIGN KEY (service_id) REFERENCES services(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    KEY idx_appointments_tenant_date (tenant_id, date_time),
    KEY idx_appointments_tenant_professional_date (tenant_id, professional_id, date_time),
    KEY idx_appointments_tenant_customer_date (tenant_id, customer_id, date_time),
    KEY idx_appointments_tenant_status (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Observações importantes sobre integridade

As chaves estrangeiras acima garantem que os registros relacionados existem, mas não garantem sozinhas que todos pertencem ao mesmo `tenant_id`. Em aplicações multi-tenant com banco único, essa validação deve ser feita em duas camadas:

- Na aplicação, todo acesso deve filtrar obrigatoriamente por `tenant_id`.
- No banco, quando necessário, podem ser adicionadas chaves únicas compostas para permitir FKs compostas envolvendo `tenant_id`.

Exemplo de reforço possível:

```sql
ALTER TABLE customers
    ADD UNIQUE KEY uq_customers_tenant_id_id (tenant_id, id);

ALTER TABLE professionals
    ADD UNIQUE KEY uq_professionals_tenant_id_id (tenant_id, id);

ALTER TABLE services
    ADD UNIQUE KEY uq_services_tenant_id_id (tenant_id, id);

ALTER TABLE appointments
    ADD CONSTRAINT fk_appointments_customer_tenant
        FOREIGN KEY (tenant_id, customer_id)
        REFERENCES customers(tenant_id, id);
```

Esse padrão pode ser repetido para `professional_id` e `service_id`. Ele aumenta a proteção contra vínculos acidentais entre tenants diferentes.

## 2. Estratégia de Isolamento por Tenant em PHP Puro

O isolamento deve ser tratado como uma regra central da aplicação, não como uma responsabilidade opcional de cada tela.

### Resolução do tenant atual

O tenant atual pode ser identificado por:

- Subdomínio: `clinica-a.sistema.com`
- Slug na URL: `sistema.com/clinica-a`
- Sessão do usuário autenticado: `$_SESSION['tenant_id']`

Após resolver o tenant, a aplicação deve criar um objeto de contexto imutável para a requisição:

```text
TenantContext
- tenantId
- tenantSlug
- tenantStatus
```

Esse contexto deve ser injetado nos repositórios e serviços da aplicação.

### Regra principal

Toda consulta em tabelas tenant-aware deve conter:

```sql
WHERE tenant_id = :tenant_id
```

Toda inserção em tabelas tenant-aware deve preencher o `tenant_id` a partir do contexto atual, nunca a partir de input vindo do usuário.

Toda atualização ou exclusão deve filtrar por `id` e `tenant_id`:

```sql
UPDATE customers
SET name = :name
WHERE id = :id
  AND tenant_id = :tenant_id;
```

```sql
DELETE FROM services
WHERE id = :id
  AND tenant_id = :tenant_id;
```

### Classe base de repositório

Sugestão conceitual:

```text
BaseRepository
- Recebe PDO
- Recebe TenantContext
- Expõe getTenantId()
- Fornece helpers para aplicar tenant_id em SELECT, INSERT, UPDATE e DELETE
```

Cada repositório específico deve herdar ou compor essa base:

```text
CustomerRepository extends BaseRepository
AppointmentRepository extends BaseRepository
ServiceRepository extends BaseRepository
```

Responsabilidades da `BaseRepository`:

- Impedir operações sem `tenant_id`.
- Centralizar o acesso ao `tenant_id`.
- Padronizar filtros por tenant.
- Evitar que controllers montem SQL diretamente.

### Cuidados obrigatórios

- Nunca aceitar `tenant_id` via formulário, query string ou JSON da requisição.
- Nunca confiar apenas no `id` do registro para buscar, atualizar ou excluir dados.
- Nunca fazer joins sem validar o tenant das tabelas envolvidas.
- Validar se o usuário autenticado pertence ao mesmo tenant do contexto.
- Bloquear acesso se o tenant estiver `inactive` ou `suspended`.
- Usar prepared statements com PDO para evitar SQL injection.

### Exemplo conceitual de fluxo

```text
Request HTTP
    -> TenantResolver identifica tenant pelo subdomínio ou slug
    -> Auth valida usuário e tenant_id da sessão
    -> TenantContext é criado
    -> Controller recebe a requisição
    -> Service aplica regras de negócio
    -> Repository executa queries sempre filtrando por tenant_id
    -> Response é retornada
```

## 3. Padrão de Arquitetura Sugerido

Para PHP puro, a recomendação é uma arquitetura simples baseada em **MVC + Service Layer + Repository Pattern**.

Essa composição mantém o projeto organizado sem exigir um framework.

### Camadas

```text
public/
    index.php

app/
    Controllers/
    Services/
    Repositories/
    Models/
    Core/
    Middleware/

config/
    database.php
    app.php

views/
    layouts/
    appointments/
    customers/
    professionals/
    services/

routes/
    web.php

database/
    migrations/
```

### Responsabilidades

#### Controllers

Recebem a requisição, validam dados básicos de entrada e retornam uma resposta.

Não devem conter SQL nem regras complexas de negócio.

Exemplos:

```text
AppointmentController
CustomerController
ServiceController
ProfessionalController
```

#### Services

Concentram regras de negócio.

Exemplos:

- Verificar se o profissional está disponível.
- Impedir dois agendamentos no mesmo horário.
- Calcular horário final com base na duração do serviço.
- Validar transições de status do agendamento.

```text
AppointmentService
CustomerService
ProfessionalService
```

#### Repositories

Concentram acesso ao banco de dados.

Todos os repositórios de entidades com `tenant_id` devem usar o `TenantContext`.

```text
AppointmentRepository
CustomerRepository
ServiceRepository
ProfessionalRepository
UserRepository
```

#### Models

Representam entidades ou DTOs simples.

Em PHP puro, não é necessário implementar Active Record. Para este projeto, **Data Mapper + Repository** é mais adequado, pois evita misturar regra de persistência com a entidade.

#### Core

Componentes centrais da aplicação:

```text
DatabaseConnection
TenantContext
TenantResolver
Router
Request
Response
Session
Auth
```

#### Middleware

Camada para validações transversais:

```text
TenantMiddleware
AuthMiddleware
RoleMiddleware
```

### Recomendação final

Use:

- **MVC simples** para organizar entrada, saída e views.
- **Service Layer** para regras de negócio.
- **Repository Pattern** para acesso ao banco.
- **TenantContext** obrigatório em todos os repositórios tenant-aware.
- **PDO com prepared statements** para todas as queries.

Evite:

- SQL espalhado em controllers.
- `tenant_id` vindo da requisição.
- Entidades Active Record que salvam a si mesmas.
- Consultas globais sem filtro por tenant.
- Regras de agendamento dentro da camada de view.

Essa arquitetura é simples o suficiente para PHP puro, mas oferece uma base segura para crescer o sistema com isolamento multi-tenant consistente.
