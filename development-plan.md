# Plano de Desenvolvimento do Back-end

Este documento define o plano inicial para implementação do back-end em **PHP 8.x puro**, usando Composer Autoload, arquitetura simples em camadas e isolamento multi-tenant por `tenant_id`.

O banco já foi criado no MySQL usando a estratégia **Single Database** com constraints compostas para reforçar a integridade entre tenants.

## 1. Estrutura de Pastas e PSR-4

O projeto deve usar o Composer apenas para autoload e, futuramente, para dependências pontuais. A aplicação continuará sem framework.

### Estrutura sugerida

```text
clinic-scheduler/
    app/
        Core/
        Controllers/
        Middleware/
        Models/
        Repositories/
        Services/
        Support/

    bootstrap/
        app.php

    config/
        app.php
        database.php

    database/
        schema.sql
        architecture-decisions.md

    public/
        index.php

    routes/
        web.php

    views/

    composer.json
```

### Mapeamento PSR-4

O namespace raiz da aplicação será:

```text
App\
```

Mapeado para:

```text
app/
```

Configuração esperada no `composer.json`:

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    }
}
```

Após criar ou alterar o `composer.json`, executar:

```bash
composer dump-autoload
```

### Namespaces por pasta

```text
app/Core/          -> App\Core
app/Controllers/   -> App\Controllers
app/Middleware/    -> App\Middleware
app/Models/        -> App\Models
app/Repositories/  -> App\Repositories
app/Services/      -> App\Services
app/Support/       -> App\Support
```

### Convenções de nomes

Classes devem usar `PascalCase` e o arquivo deve ter o mesmo nome da classe:

```text
app/Core/Database.php              -> App\Core\Database
app/Core/TenantContext.php         -> App\Core\TenantContext
app/Repositories/UserRepository.php -> App\Repositories\UserRepository
```

Interfaces, quando existirem, devem usar o sufixo `Interface`:

```text
App\Repositories\Contracts\UserRepositoryInterface
```

No início do projeto, não é obrigatório criar interfaces para todos os repositórios. Elas devem ser introduzidas quando houver necessidade real de troca de implementação, testes com mocks ou contratos compartilhados.

## 2. Ciclo de Vida da Requisição

O ponto de entrada HTTP será sempre:

```text
public/index.php
```

Nenhum arquivo fora de `public/` deve ser acessível diretamente pelo servidor web.

### Fluxo esperado

```text
1. Requisição HTTP chega em public/index.php
2. index.php carrega vendor/autoload.php
3. index.php carrega bootstrap/app.php
4. bootstrap/app.php carrega configurações
5. bootstrap/app.php cria a conexão PDO
6. bootstrap/app.php cria objetos centrais da aplicação
7. TenantMiddleware resolve e valida o tenant atual
8. AuthMiddleware valida a sessão do usuário, quando a rota exigir autenticação
9. Router encontra a rota correspondente
10. Controller é instanciado com suas dependências
11. Controller chama Services
12. Services chamam Repositories
13. Repositories executam queries filtrando por tenant_id
14. Controller retorna uma Response
15. public/index.php envia a Response ao navegador
```

### Papel do `public/index.php`

O `index.php` deve ser pequeno. Ele não deve conter regras de negócio, SQL, HTML complexo ou decisões de tenant.

Responsabilidades:

- Definir constantes básicas, se necessário.
- Carregar o Autoload do Composer.
- Carregar o bootstrap da aplicação.
- Receber o objeto `Request`.
- Executar o roteador ou kernel HTTP.
- Enviar a resposta final.

### Papel do `bootstrap/app.php`

O bootstrap deve montar a aplicação.

Responsabilidades:

- Carregar arquivos de configuração.
- Criar o `Database`.
- Criar a conexão `PDO`.
- Registrar rotas.
- Configurar middlewares.
- Criar um container simples ou factories explícitas.
- Retornar um objeto principal da aplicação, como `App\Core\Application`.

### Papel das rotas

O arquivo:

```text
routes/web.php
```

Deve declarar as rotas HTTP e apontar para controllers.

Exemplo conceitual:

```text
GET  /customers          -> CustomerController@index
GET  /customers/create   -> CustomerController@create
POST /customers          -> CustomerController@store
GET  /appointments       -> AppointmentController@index
POST /appointments       -> AppointmentController@store
```

As rotas não devem conter SQL nem regra de negócio.

## 3. Lista de Tarefas Atômicas

A implementação deve começar pelo Core antes de criar telas ou regras de negócio específicas.

### 1. `composer.json`

Define o autoload PSR-4:

```text
App\ -> app/
```

Sem isso, a aplicação dependeria de `require` manual e ficaria difícil de crescer.

### 2. `config/database.php`

Arquivo de configuração do banco.

Deve conter:

- Host.
- Database.
- Usuário.
- Senha.
- Charset.
- Opções do PDO.

As credenciais podem vir inicialmente de constantes ou variáveis de ambiente.

### 3. `config/app.php`

Arquivo de configuração geral.

Deve conter:

- Nome da aplicação.
- Ambiente.
- URL base.
- Estratégia de resolução do tenant.
- Configurações de sessão.

### 4. `app/Core/Config.php`

Responsável por carregar configurações de `config/`.

Papel:

- Centralizar leitura de configurações.
- Evitar `require` espalhado pela aplicação.
- Permitir acessar valores por chave, por exemplo `database.host`.

### 5. `app/Core/Database.php`

Responsável por criar e fornecer a conexão `PDO`.

Papel:

- Montar DSN.
- Aplicar opções seguras do PDO.
- Configurar `ERRMODE_EXCEPTION`.
- Configurar charset.
- Retornar uma instância de `PDO`.

Importante: essa classe não deve ser um Singleton. A instância deve ser criada no bootstrap e passada para quem precisa.

### 6. `app/Core/Request.php`

Representa a requisição HTTP atual.

Papel:

- Expor método HTTP.
- Expor URI/path.
- Expor query params.
- Expor dados de POST.
- Expor headers necessários.
- Normalizar entrada da requisição.

### 7. `app/Core/Response.php`

Representa a resposta HTTP.

Papel:

- Definir status code.
- Definir headers.
- Enviar conteúdo.
- Permitir redirects.

### 8. `app/Core/Router.php`

Responsável por mapear rotas para actions de controllers.

Papel:

- Registrar rotas por método HTTP.
- Resolver rota pela URI.
- Extrair parâmetros simples.
- Executar middlewares associados.
- Despachar para controller/action.

### 9. `app/Core/TenantContext.php`

Objeto imutável que representa o tenant da requisição atual.

Deve carregar:

- `tenantId`.
- `tenantSlug`.
- `tenantStatus`.
- Opcionalmente `tenantName`.

Papel:

- Ser a fonte única do tenant atual durante a requisição.
- Ser injetado em Services e Repositories.
- Impedir que `tenant_id` venha do usuário.

### 10. `app/Core/TenantResolver.php`

Responsável por descobrir o tenant atual.

Estratégias possíveis:

- Subdomínio.
- Primeiro segmento da URL.
- Sessão autenticada.

Papel:

- Buscar o tenant na tabela `tenants`.
- Validar se existe.
- Validar status `active`.
- Criar o `TenantContext`.

### 11. `app/Core/Session.php`

Camada pequena para lidar com sessão.

Papel:

- Iniciar sessão.
- Ler e escrever valores.
- Regenerar ID após login.
- Destruir sessão no logout.

### 12. `app/Core/Auth.php`

Responsável pela autenticação.

Papel:

- Validar login.
- Armazenar usuário autenticado na sessão.
- Retornar usuário atual.
- Validar se o usuário pertence ao tenant atual.
- Validar role quando necessário.

### 13. `app/Core/Application.php`

Objeto principal da aplicação.

Papel:

- Receber `Request`.
- Rodar middlewares globais.
- Acionar o `Router`.
- Retornar `Response`.

Essa classe evita que o `public/index.php` cresça demais.

### 14. `app/Middleware/TenantMiddleware.php`

Middleware obrigatório para rotas tenant-aware.

Papel:

- Chamar o `TenantResolver`.
- Bloquear tenants inexistentes, inativos ou suspensos.
- Disponibilizar o `TenantContext` para o restante do fluxo.

### 15. `app/Middleware/AuthMiddleware.php`

Middleware para rotas autenticadas.

Papel:

- Verificar se existe usuário logado.
- Confirmar que o usuário pertence ao `TenantContext`.
- Redirecionar ou retornar erro quando não autenticado.

### 16. `app/Middleware/RoleMiddleware.php`

Middleware para autorização por papel.

Papel:

- Permitir ações apenas para roles específicas.
- Exemplo: somente `admin` pode gerenciar usuários.

### 17. `app/Repositories/BaseRepository.php`

Classe base para repositórios tenant-aware.

Deve receber:

- `PDO`.
- `TenantContext`.

Papel:

- Expor o `tenantId` atual para classes filhas.
- Padronizar filtros por tenant.
- Forçar inserts com `tenant_id` vindo do contexto.
- Impedir operações sem tenant.

### 18. Repositories específicos

Criar depois do Core:

```text
app/Repositories/TenantRepository.php
app/Repositories/UserRepository.php
app/Repositories/CustomerRepository.php
app/Repositories/ProfessionalRepository.php
app/Repositories/ServiceRepository.php
app/Repositories/AppointmentRepository.php
```

Observação: `TenantRepository` pode não receber `TenantContext`, pois ele será usado justamente para encontrar o tenant antes do contexto existir.

### 19. Services específicos

Criar após os repositories:

```text
app/Services/AuthService.php
app/Services/CustomerService.php
app/Services/ProfessionalService.php
app/Services/ServiceService.php
app/Services/AppointmentService.php
```

O `AppointmentService` deve ser o primeiro service de negócio mais robusto, pois concentra regras críticas:

- Verificar disponibilidade do profissional.
- Impedir conflito de horários.
- Validar customer/professional/service dentro do tenant.
- Validar transição de status.

### 20. Controllers iniciais

Criar depois dos services:

```text
app/Controllers/AuthController.php
app/Controllers/DashboardController.php
app/Controllers/CustomerController.php
app/Controllers/ServiceController.php
app/Controllers/ProfessionalController.php
app/Controllers/AppointmentController.php
```

Controllers devem apenas coordenar entrada, services e resposta.

## 4. Padrão de Injeção de Dependência

A aplicação deve usar dependências explícitas por construtor.

Evitar:

- Variáveis globais.
- Singletons escondendo dependências.
- Repositories criando seu próprio PDO.
- Services criando seus próprios repositories manualmente em vários lugares.
- Uso direto de `$_SESSION`, `$_POST` e `$_GET` fora das camadas apropriadas.

### Regra base

Quem precisa de uma dependência deve recebê-la no construtor.

Exemplo conceitual:

```text
CustomerRepository
    recebe PDO
    recebe TenantContext

CustomerService
    recebe CustomerRepository

CustomerController
    recebe CustomerService
    recebe Request
```

### Criação das dependências

No início, não é necessário usar uma biblioteca de container. Um container simples ou factories no bootstrap são suficientes.

O `bootstrap/app.php` pode montar explicitamente:

```text
Config
Database
PDO
Request
Session
TenantRepository
TenantResolver
Router
Application
```

Depois que o `TenantContext` for resolvido pelo middleware, as factories podem criar repositories tenant-aware:

```text
CustomerRepository(PDO, TenantContext)
ServiceRepository(PDO, TenantContext)
ProfessionalRepository(PDO, TenantContext)
AppointmentRepository(PDO, TenantContext)
```

### Por que não usar Singleton para PDO

Singleton parece simples no começo, mas cria problemas:

- Esconde dependências reais.
- Dificulta testes.
- Dificulta trocar configuração por ambiente.
- Estimula acesso global ao banco.
- Torna o fluxo multi-tenant mais arriscado.

A conexão `PDO` pode ser uma única instância por requisição, mas deve ser passada explicitamente.

### Como passar `PDO` e `TenantContext` aos Repositories

Padrão recomendado:

```text
1. bootstrap/app.php cria PDO
2. TenantMiddleware resolve TenantContext
3. Uma factory cria o Repository necessário com PDO + TenantContext
4. Service recebe o Repository
5. Controller recebe o Service
```

O ponto crítico é que o `TenantContext` só existe depois que o tenant foi resolvido. Portanto, repositórios tenant-aware não devem ser criados antes dessa etapa.

### Exceção: repositórios globais

Alguns repositórios não são tenant-aware:

```text
TenantRepository
```

Ele precisa consultar `tenants` antes da criação do `TenantContext`. Por isso, recebe apenas `PDO`.

### Factories recomendadas

Para manter o código limpo sem adotar framework, criar uma camada simples de factories:

```text
app/Core/RepositoryFactory.php
app/Core/ServiceFactory.php
app/Core/ControllerFactory.php
```

Essas factories devem receber as dependências centrais e montar objetos conforme necessário.

Exemplo conceitual:

```text
RepositoryFactory
    recebe PDO
    recebe TenantContext
    cria CustomerRepository
    cria AppointmentRepository

ServiceFactory
    recebe RepositoryFactory
    cria CustomerService
    cria AppointmentService

ControllerFactory
    recebe ServiceFactory
    cria CustomerController
    cria AppointmentController
```

### Escopo das dependências

```text
Por aplicação:
    Config

Por requisição:
    Request
    Response
    PDO
    Session
    TenantContext
    Auth
    Router
    Controllers
    Services
    Repositories
```

Em PHP tradicional com Apache/Nginx + PHP-FPM, cada request já tem um ciclo de vida curto. Mesmo assim, manter dependências explícitas evita acoplamento e reduz risco de vazamento entre tenants.

## Ordem recomendada de implementação

```text
1. Composer autoload
2. Config
3. Database
4. Request
5. Response
6. Router
7. Session
8. TenantRepository
9. TenantContext
10. TenantResolver
11. TenantMiddleware
12. Auth
13. AuthMiddleware
14. RoleMiddleware
15. Application
16. BaseRepository
17. RepositoryFactory
18. Services
19. Controllers
20. Views e rotas reais
```

Essa ordem reduz retrabalho porque primeiro estabelece o esqueleto da aplicação, depois o isolamento multi-tenant, e só então entra nas regras específicas de clientes, serviços, profissionais e agendamentos.
