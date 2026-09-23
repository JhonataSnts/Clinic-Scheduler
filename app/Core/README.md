# Guia de treino do Core

Este arquivo registra a ordem em que o Core foi criado e o raciocinio usado. A ideia nao e decorar todas as linhas, mas conseguir reconstruir cada classe sabendo:

1. Qual problema ela resolve.
2. Quais dados precisa guardar.
3. Quais metodos deve oferecer.
4. Como testar o menor comportamento util.

Todas as classes desta pasta usam o namespace:

```php
namespace App\Core;
```

O Composer mapeia `App\` para a pasta `app/` usando PSR-4.

## Ordem de criacao

```text
Config -> Database -> Request -> Response -> Router
```

Essa ordem acompanha as dependencias: `Database` usa `Config`, enquanto `Router` usa `Request` e devolve `Response`.

## 1. Config.php

### Pergunta que resolve

Como carregar e consultar os arrays retornados pelos arquivos da pasta `config/` sem espalhar varios `require` pelo projeto?

### O que possui

- `$items`: guarda todas as configuracoes carregadas.
- `$configPath`: recebe o caminho da pasta `config/`.

### Metodos

```text
__construct(string $configPath)
get(string $key, mixed $default = null): mixed
load(): void
```

### Ordem para escrever

1. Criar a propriedade `$items` como array vazio.
2. Receber `$configPath` no construtor.
3. Criar `load()` para encontrar os arquivos `.php` com `glob()`.
4. Para cada arquivo, usar o nome sem `.php` como chave e guardar o array retornado pelo `require`.
5. Criar `get()` para percorrer chaves separadas por ponto, como `database.host`.
6. Retornar o valor padrao quando uma chave nao existir.

### Teste minimo

Criar `Config` apontando para `config/` e conferir se `get('database.host')` retorna o host configurado.

## 2. Database.php

### Pergunta que resolve

Como criar a conexao PDO usando a configuracao centralizada?

### Dependencia

Recebe `Config` pelo construtor. Nao usa variavel global e nao e Singleton.

### Metodo

```text
connection(): PDO
```

### Ordem para escrever

1. Importar `PDO` com `use PDO;`.
2. Receber `Config` no construtor.
3. Ler host, nome do banco e charset.
4. Montar o DSN do MySQL.
5. Ler usuario, senha e opcoes do PDO.
6. Criar e retornar o objeto `PDO`.

### Teste minimo

Criar `Config`, criar `Database`, chamar `connection()` e confirmar que nenhum erro de conexao foi lancado.

## 3. Request.php

### Pergunta que resolve

Como representar a requisicao HTTP sem acessar `$_SERVER`, `$_GET` e `$_POST` por toda a aplicacao?

### Metodos

```text
method(): string
path(): string
query(?string $key = null, mixed $default = null): mixed
input(?string $key = null, mixed $default = null): mixed
all(): array
```

### Papel de cada metodo

- `method()`: devolve o metodo HTTP, como `GET` ou `POST`.
- `path()`: devolve apenas o caminho da URL, sem a query string.
- `query()`: devolve todos os dados de `$_GET` ou uma chave especifica.
- `input()`: devolve todos os dados de `$_POST` ou uma chave especifica.
- `all()`: combina query string e dados de formulario.

### Ordem para escrever

1. Comecar por `method()` e usar `GET` como valor padrao.
2. Criar `path()` lendo `REQUEST_URI` e usando `parse_url()`.
3. Criar `query()` para consultar `$_GET`.
4. Criar `input()` para consultar `$_POST`.
5. Criar `all()` combinando os dois arrays.

### Teste minimo

Definir valores em `$_SERVER`, `$_GET` e `$_POST`, criar `Request` e conferir o retorno de cada metodo.

## 4. Response.php

### Pergunta que resolve

Como representar e enviar conteudo, status HTTP, headers e redirecionamentos em um unico objeto?

### O que possui

- `$content`: conteudo que sera enviado.
- `$statusCode`: codigo HTTP; o padrao e `200`.
- `$headers`: headers adicionais da resposta.

### Metodos

```text
__construct(string $content = '', int $statusCode = 200)
setHeader(string $name, string $value): self
send(): void
redirect(string $url, int $statusCode = 302): self
```

### Ordem para escrever

1. Receber conteudo e status no construtor.
2. Criar o array de headers.
3. Criar `setHeader()` para guardar um header e retornar o proprio objeto.
4. Criar `send()` para aplicar status, enviar headers e imprimir o conteudo.
5. Criar `redirect()` para definir o status e o header `Location`.

### Teste minimo

Criar `new Response('OK', 200)` e chamar `send()`. A saida deve ser `OK`.

## 5. Router.php

### Pergunta que resolve

Como registrar rotas e escolher qual handler executar usando o metodo HTTP e o caminho da requisicao?

### O que possui

- `$routes`: array organizado por metodo e caminho, por exemplo `$routes['GET']['/']`.

### Metodos

```text
get(string $path, callable $handler): void
post(string $path, callable $handler): void
add(string $method, string $path, callable $handler): void
dispatch(Request $request): Response
```

`add()` e privado porque apenas o proprio `Router` precisa saber como as rotas sao armazenadas.

### Ordem para escrever

1. Criar o array `$routes`.
2. Criar `get()` e registrar uma primeira rota GET.
3. Extrair o armazenamento para o metodo privado `add()`.
4. Criar `post()` reutilizando `add()`.
5. Criar `dispatch()` recebendo um `Request` e prometendo retornar `Response`.
6. Ler o metodo e o caminho da requisicao.
7. Procurar o handler em `$routes[$method][$path]`.
8. Se encontrar, executar o callable e retornar sua `Response`.
9. Se nao encontrar, retornar uma `Response` com status `404`.

### Testes minimos

Testar separadamente:

1. `GET /` deve retornar `Home`.
2. `POST /customers` deve retornar `Cliente cadastrado`.
3. Uma rota inexistente deve retornar status `404`.

## Fluxo HTTP construido ate aqui

```text
Request le metodo e caminho
        -> Router procura a rota
        -> handler cria uma Response
        -> Response envia status, headers e conteudo
```

Exemplo mental:

```text
GET /
-> Request informa GET e /
-> Router procura routes['GET']['/']
-> handler da rota e executado
-> Response('Home') e retornada
-> send() imprime Home
```

## Como reconstruir sem consultar o codigo pronto

Para cada classe, responda no papel antes de programar:

```text
Qual responsabilidade esta classe possui?
O que entra nela?
O que ela precisa guardar?
Qual e o menor metodo util?
O que deve sair dela?
Como provo que funcionou?
```

Implemente um comportamento pequeno, execute o teste e so depois avance. Nao e necessario testar cada linha isoladamente; teste quando houver um ciclo minimo de entrada, processamento e saida.

## Proximo componente do plano

Depois deste Core minimo, o proximo arquivo previsto em `development-plan.md` e `Session.php`, responsavel por iniciar a sessao e centralizar leitura, escrita, remocao e destruicao dos dados de sessao.
