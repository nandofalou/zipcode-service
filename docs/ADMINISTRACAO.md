# Administração

Documentação dos endpoints restritos a contas **master** (`is_master = 1`), do bootstrap inicial e das **operações de manutenção** (importação IBGE).

## Fluxo operacional recomendado

1. `GET /api/install` — criar banco e conta admin master.
2. `php bin/import-ibge.php` — popular estados e municípios com códigos IBGE oficiais.
3. Criar service accounts para integradores (`POST /api/service-accounts`).
4. Em produção: `INSTALL_ENABLED=false`.
5. Manutenção periódica: reexecutar importação IBGE para atualizar nomes de municípios.

---

## Autenticação master

Todos os endpoints desta documentação (exceto install) exigem:

```http
X-Service-Key: admin
X-Service-Token: TOKEN_MASTER
```

Contas sem `is_master = 1` recebem **HTTP 403**:

```json
{
  "status": false,
  "message": "Acesso restrito a contas master."
}
```

---

## Bootstrap — Instalação

Cria o banco, tabelas, país padrão (Brasil) e conta `admin` master.

```http
GET /api/install
```

**Sem autenticação.** Desabilitável com `INSTALL_ENABLED=false` (retorna 403).

### Primeira instalação

```bash
curl -s http://localhost:8090/api/install
```

```json
{
  "status": true,
  "message": "Instalação concluída.",
  "service_name": "admin",
  "service_token": "64_caracteres_hex",
  "is_master": true
}
```

Guarde o `service_token` — ele só é retornado na primeira instalação.

### Reinstalação (banco já existe)

```json
{
  "status": true,
  "message": "Banco já instalado. Conta admin já existe.",
  "service_name": "admin",
  "service_token": null
}
```

---

## Importação IBGE (CLI)

Script operacional para popular **estados** e **municípios** a partir da [API pública do IBGE](https://servicodados.ibge.gov.br/api/docs/localidades). Não é um endpoint HTTP — roda via terminal ou cron.

**Arquivo:** `bin/import-ibge.php`

**Pré-requisito:** banco instalado (`GET /api/install`).

### Uso

```bash
php bin/import-ibge.php [--db=./data/zipcode.db] [--state=BA] [--dry-run] [--help]
```

| Flag | Descrição |
|------|-----------|
| `--db=PATH` | Sobrescreve `DB_PATH` |
| `--state=UF` | Importa apenas uma UF (útil para teste) |
| `--dry-run` | Simula sem gravar no banco |
| `--help` | Exibe ajuda |

### Docker

```bash
# Importação completa
docker compose exec php php bin/import-ibge.php

# Uma UF (teste)
docker compose run --rm php php bin/import-ibge.php --state=BA --dry-run
docker compose run --rm php php bin/import-ibge.php --state=BA
```

Após alterações em `bin/` ou código PHP, rebuild: `docker compose up --build`.

### Comportamento

| Entidade | Estratégia | Fonte IBGE |
|----------|------------|------------|
| País | Garantir Brasil (`CountryRepository::findOrCreateDefault`) | Settings `default_country` |
| Estado | **Insert-only** — UF existente não é alterada | `GET /estados` |
| Município | **Upsert** por `city.ibge_code` (UNIQUE) | `GET /estados/{id}/municipios` |

**Mapeamento de estados:**

| Campo IBGE | Coluna `state` |
|------------|----------------|
| `id` | `ibge_code` |
| `sigla` | `abbr` (uppercase) |
| `nome` | `name` |

**Mapeamento de municípios:**

| Campo IBGE | Coluna `city` |
|------------|---------------|
| `id` | `ibge_code` |
| `nome` | `name` |
| — | `normalized_name` via `Normalizer::citySlug()` |

- **Novo município:** INSERT.
- **Existente (mesmo `ibge_code`):** UPDATE de `name`, `normalized_name` e `state_id` se necessário.
- **Transação:** uma transação PDO por UF; rollback em erro parcial.

> **Nota:** UFs criadas anteriormente via consulta de CEP (sem `ibge_code`) são **ignoradas** no insert de estados (insert-only). Os municípios são upsertados normalmente por `ibge_code`.

### Saída exemplo

```
IBGE import started
DB_PATH=./data/zipcode.db
States: 27 fetched, 2 inserted, 25 skipped
BA: 417 municipalities, 10 created, 407 updated, 0 unchanged
SP: 645 municipalities, 0 created, 0 updated, 645 unchanged
Done in 45s
```

| Exit code | Significado |
|-----------|-------------|
| `0` | Sucesso |
| `1` | Erro fatal (API IBGE indisponível, UF inválida, falha de banco, etc.) |

Avisos não fatais (ex.: municípios não obtidos para uma UF) são impressos em stderr.

### Variável de ambiente

| Variável | Padrão | Descrição |
|----------|--------|-----------|
| `IBGE_BASE_URL` | `https://servicodados.ibge.gov.br/api/v1/localidades` | Base da API IBGE |

---

## Service accounts

Gerenciamento de credenciais de integração.

### Listar contas

```http
GET /api/service-accounts
```

```json
{
  "status": true,
  "message": "",
  "data": [
    {
      "id": 1,
      "service_name": "admin",
      "is_active": 1,
      "is_master": 1,
      "created_at": "2026-05-26T01:02:28+00:00"
    }
  ]
}
```

Tokens **não** são listados por segurança.

### Criar conta

```http
POST /api/service-accounts
Content-Type: application/json
```

```json
{
  "service_name": "core-api"
}
```

Resposta (**HTTP 201**):

```json
{
  "status": true,
  "message": "Conta criada.",
  "data": {
    "id": 2,
    "service_name": "core-api",
    "service_token": "token_gerado_uma_vez",
    "is_active": 1,
    "is_master": 0,
    "created_at": "2026-05-26T12:00:00+00:00"
  }
}
```

### Atualizar conta

```http
PUT /api/service-accounts/{id}
Content-Type: application/json
```

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `is_active` | boolean | Ativar ou desativar a conta |
| `rotate_token` | boolean | `true` gera novo token e invalida o anterior |

```json
{
  "is_active": false,
  "rotate_token": true
}
```

Quando `rotate_token` é `true`, o novo token aparece em `data.service_token`.

### Excluir conta

```http
DELETE /api/service-accounts/{id}
```

Contas **master** não podem ser excluídas.

```json
{
  "status": true,
  "message": "Conta excluída."
}
```

---

## CEPs — Listagem e exclusão

Operações sobre o cache local de CEPs.

### Listar CEPs (paginado)

```http
GET /api/zipcodes
```

#### Query parameters

| Parâmetro | Tipo | Padrão | Descrição |
|-----------|------|--------|-----------|
| `page` | integer | `1` | Página (mínimo 1) |
| `per_page` | integer | `20` | Itens por página (máximo 100) |
| `city` | string | — | Filtro parcial pelo nome da cidade |
| `neighborhood` | string | — | Filtro parcial pelo bairro |
| `state` | string | — | UF exata (ex.: `BA`) |
| `zipcode` | string | — | Início do CEP (apenas dígitos) |
| `sort` | string | `created_at` | Campo de ordenação (ver abaixo) |
| `order` | string | `desc` | `asc` ou `desc` |

**Campos de ordenação (`sort`):**

| Valor | Ordena por |
|-------|------------|
| `zipcode` | CEP |
| `city` | Nome da cidade |
| `neighborhood` | Bairro |
| `state` | UF |
| `created_at` | Data de cadastro |

#### Exemplo

```bash
curl -s "http://localhost:8090/api/zipcodes?city=Salvador&state=BA&page=1&per_page=10&sort=zipcode&order=asc" \
  -H "X-Service-Key: admin" \
  -H "X-Service-Token: TOKEN"
```

#### Resposta

```json
{
  "status": true,
  "message": "",
  "data": [
    {
      "zipcode": "40330200",
      "street": "Rua Conde de Porto Alegre",
      "neighborhood": "IAPI",
      "lat": "-12.9541279",
      "lng": "-38.481378",
      "provider": "viacep",
      "created_at": "2026-05-26T01:02:30+00:00",
      "city": {
        "id": 1,
        "name": "Salvador",
        "ibge_code": 2927408
      },
      "state": {
        "id": 1,
        "abbr": "BA",
        "name": "Bahia"
      }
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 10,
    "total": 1,
    "total_pages": 1,
    "sort": "zipcode",
    "order": "asc"
  }
}
```

Filtros podem ser combinados. Todos são opcionais.

### Excluir CEP

Remove um CEP do cache local. Na próxima consulta via `/api/getcep/{cep}`, o sistema buscará novamente nos providers.

```http
DELETE /api/zipcodes/{cep}
```

```bash
curl -s -X DELETE "http://localhost:8090/api/zipcodes/40330200" \
  -H "X-Service-Key: admin" \
  -H "X-Service-Token: TOKEN"
```

#### Sucesso

```json
{
  "status": true,
  "message": "CEP excluído.",
  "zipcode": "40330200"
}
```

#### CEP inválido ou inexistente

```json
{
  "status": false,
  "message": "CEP inválido. Informe 8 dígitos."
}
```

```json
{
  "status": false,
  "message": "CEP não encontrado."
}
```

---

## Resumo dos endpoints

| Método | Rota | Auth | Descrição |
|--------|------|------|-----------|
| GET | `/api/install` | Nenhuma | Bootstrap do banco e conta admin |
| GET | `/api/service-accounts` | Master | Listar contas |
| POST | `/api/service-accounts` | Master | Criar conta |
| PUT | `/api/service-accounts/{id}` | Master | Atualizar conta |
| DELETE | `/api/service-accounts/{id}` | Master | Excluir conta |
| GET | `/api/zipcodes` | Master | Listar CEPs (filtros + paginação) |
| DELETE | `/api/zipcodes/{cep}` | Master | Excluir CEP do cache |

A consulta de CEP e reverse geocode para integração estão em [INTEGRACAO.md](INTEGRACAO.md).

---

## Scripts CLI (referência)

| Script | Descrição |
|--------|-----------|
| `bin/import-ibge.php` | Importação IBGE (esta seção) |
| `zipcodeserver` | Servidor PHP embutido para dev local (não usar em produção) |

---

## Variáveis de ambiente

| Variável | Padrão | Descrição |
|----------|--------|-----------|
| `DB_PATH` | `{raiz}/data/zipcode.db` (Docker: `/app/data/zipcode.db`) | Caminho do SQLite |
| `INSTALL_ENABLED` | `true` | `false` bloqueia `/api/install` |
| `DISPLAY_ERROR_DETAILS` | `false` | Detalhes de erro Slim |
| `NOMINATIM_BASE_URL` | `https://nominatim.openstreetmap.org` | Base Nominatim (reverse geocode) |
| `NOMINATIM_USER_AGENT` | `ZipcodeMicroservice/1.0 (dev-local)` | User-Agent Nominatim |
| `IBGE_BASE_URL` | `https://servicodados.ibge.gov.br/api/v1/localidades` | Base API IBGE (CLI) |

Após o bootstrap em produção, defina `INSTALL_ENABLED=false`. Configure `NOMINATIM_USER_AGENT` com contato real em produção.
