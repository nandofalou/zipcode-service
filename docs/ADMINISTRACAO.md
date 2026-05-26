# Administração

Documentação dos endpoints restritos a contas **master** (`is_master = 1`), além do bootstrap inicial.

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
curl -s http://localhost:8080/api/install
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
curl -s "http://localhost:8080/api/zipcodes?city=Salvador&state=BA&page=1&per_page=10&sort=zipcode&order=asc" \
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
curl -s -X DELETE "http://localhost:8080/api/zipcodes/40330200" \
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

A consulta de CEP para integração está documentada em [INTEGRACAO.md](INTEGRACAO.md).

---

## Variáveis de ambiente

| Variável | Padrão | Descrição |
|----------|--------|-----------|
| `INSTALL_ENABLED` | `true` | `false` bloqueia `/api/install` |
| `DB_PATH` | `/app/data/zipcode.db` | Caminho do SQLite |

Após o bootstrap em produção, defina `INSTALL_ENABLED=false`.
