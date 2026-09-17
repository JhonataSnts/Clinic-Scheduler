# Por que separar Config e Database

Em PHP puro, é comum começar com um arquivo `database.php` que já cria o PDO diretamente:

```php
$host = 'localhost';
$dbname = 'clinic_db';
$username = 'root';
$password = '';

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
$pdo = new PDO($dsn, $username, $password);
```

Esse modelo funciona bem para projetos pequenos, scripts simples ou estudos iniciais. Porém, para uma aplicação maior e multi-tenant, ele começa a criar problemas.

## A abordagem escolhida no projeto

Neste projeto, vamos separar responsabilidades:

```text
config/database.php
    Guarda dados de configuração do banco.

App\Core\Config
    Lê arquivos de configuração.

App\Core\Database
    Usa Config para criar o PDO.

Repositories
    Recebem o PDO e executam queries.
```

Ou seja:

```text
Config não conecta.
Database conecta.
Repository consulta.
```

## Por que isso é melhor neste projeto

### 1. Evita variável global `$pdo`

No modelo simples, vários arquivos acabam dependendo de uma variável criada por `require`:

```php
require 'database.php';
$pdo->query(...);
```

Isso esconde a dependência real. O arquivo usa banco, mas isso não fica claro pela assinatura da classe ou do método.

No nosso modelo, quem precisa do banco recebe o `PDO` explicitamente.

### 2. Facilita manutenção

Se a conexão estiver espalhada em vários arquivos, qualquer mudança vira retrabalho.

Com a separação:

- Configuração fica em `config/database.php`.
- Criação da conexão fica em `App\Core\Database`.
- Queries ficam nos Repositories.

Cada mudança tem um lugar certo.

### 3. Ajuda no isolamento multi-tenant

Este sistema usa banco único com `tenant_id`.

O risco mais perigoso é uma query esquecer:

```sql
WHERE tenant_id = :tenant_id
```

Ao centralizar o acesso ao banco em Repositories, conseguimos criar uma `BaseRepository` que sempre recebe `TenantContext` e ajuda a padronizar queries filtradas por tenant.

Se cada página fizer SQL direto com um `$pdo` global, fica muito mais fácil vazar dados entre clínicas.

### 4. Facilita testes

Quando uma classe recebe suas dependências pelo construtor, fica mais fácil testar.

Exemplo conceitual:

```text
CustomerRepository recebe PDO
CustomerService recebe CustomerRepository
CustomerController recebe CustomerService
```

Isso permite trocar implementações, simular dependências e entender melhor o fluxo.

### 5. Reduz acoplamento

No modelo simples, os arquivos ficam acoplados a detalhes de conexão.

No nosso modelo:

- Controller não sabe como conecta no banco.
- Service não sabe montar DSN.
- Repository não sabe de onde vieram as credenciais.
- Database não sabe regras de negócio.

Cada peça tem uma responsabilidade menor.

## Problemas que essa decisão resolve

```text
- SQL espalhado em páginas PHP.
- Variável $pdo solta no escopo global.
- Repetição de require.
- Dificuldade para trocar credenciais por ambiente.
- Dificuldade para testar classes isoladamente.
- Maior risco de queries sem tenant_id.
- Mistura de configuração, conexão, regra de negócio e HTML.
```

## Regra mental

Para este projeto, use a seguinte regra:

```text
Arquivo de config retorna array.
Config lê os arrays.
Database cria o PDO.
Repository usa o PDO.
Service aplica regra de negócio.
Controller coordena a requisição.
```

Essa estrutura é mais trabalhosa no começo, mas cria uma base mais segura para um sistema multi-tenant crescer.
