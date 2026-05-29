# Microserviço de CEP — Especificação

Serviço interno para **buscar endereço por CEP**, **reverse geocode** (coordenadas → endereço via Nominatim) e **importação de localidades IBGE** via CLI. Cache local em **SQLite** e fallback para múltiplos **providers** externos.

## Documentação por público

| Arquivo | Conteúdo |
|---------|----------|
| [README.md](README.md) | Visão geral, bootstrap e quick start |
| [docs/INTEGRACAO.md](docs/INTEGRACAO.md) | API de CEP e reverse geocode |
| [docs/ADMINISTRACAO.md](docs/ADMINISTRACAO.md) | Install, admin, importação IBGE |

## Requisitos técnicos

- **Framework**: Slim Framework 4 (PHP 8.4)
- **DI**: PHP-DI (`config/container.php`)
- **HTTP client**: Guzzle
- **Banco**: SQLite (`DB_PATH`, padrão `{raiz}/data/zipcode.db`; Docker: `/app/data/zipcode.db`)
- **PRAGMAs obrigatórios**:
  - `PRAGMA journal_mode=WAL;`
  - `PRAGMA synchronous=NORMAL;`
  - `PRAGMA foreign_keys=ON;`

Exemplo de inicialização do PDO:

```php
$pdo = new PDO('sqlite:/app/data/zipcode.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA journal_mode=WAL;');
$pdo->exec('PRAGMA synchronous=NORMAL;');
$pdo->exec('PRAGMA foreign_keys=ON;');
```

## Estrutura de código (principais)

```
microservice_zipcodes/
├── bin/import-ibge.php              # CLI importação IBGE
├── config/
│   ├── container.php                # DI (repos, providers, import, geocode)
│   ├── routes.php
│   └── settings.php                 # env: DB_PATH, NOMINATIM_*, IBGE_*
├── public/index.php                 # Entrypoint HTTP
├── src/
│   ├── Application/
│   │   ├── CepLookupService.php
│   │   ├── ReverseGeocodeService.php
│   │   ├── ReverseGeocodeAction.php
│   │   └── Import/IbgeLocalidadesImporter.php
│   ├── Infrastructure/
│   │   ├── Ibge/IbgeLocalidadesClient.php
│   │   ├── Geocode/NominatimClient.php
│   │   ├── Geocode/NominatimAddressMapper.php
│   │   ├── Provider/                  # ViaCEP, AwesomeAPI, BrasilAPI, etc.
│   │   └── Repository/                # Country, State, City, Zipcode, ServiceAccount
│   └── Support/Normalizer.php
├── zipcodeserver                      # CLI dev server
└── docs/INTEGRACAO.md, ADMINISTRACAO.md
```

## Modelagem do banco (DDL)

### `country`

```sql
CREATE TABLE country (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    alphacode2 CHAR(2) NOT NULL UNIQUE,
    alphacode3 CHAR(3),
    numcode INTEGER
);

CREATE INDEX idx_country_name ON country(name);
```

### `state`

```sql
CREATE TABLE state (
    id INTEGER PRIMARY KEY,
    country_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    abbr CHAR(5) NOT NULL,
    ibge_code INTEGER,
    FOREIGN KEY (country_id) REFERENCES country(id)
);

CREATE UNIQUE INDEX uq_state_country_abbr ON state(country_id, abbr);
CREATE INDEX idx_state_name ON state(name);
```

### `city`

```sql
CREATE TABLE city (
    id INTEGER PRIMARY KEY,
    state_id INTEGER NOT NULL,
    ibge_code INTEGER UNIQUE,
    name TEXT NOT NULL,
    latitude REAL,
    longitude REAL,
    normalized_name TEXT NOT NULL,
    FOREIGN KEY (state_id) REFERENCES state(id)
);

CREATE UNIQUE INDEX uq_city_state_name ON city(state_id, normalized_name);
CREATE INDEX idx_city_ibge_code ON city(ibge_code);
CREATE INDEX idx_city_geo ON city(latitude, longitude);
```

### `zipcode`

```sql
CREATE TABLE zipcode (
    zipcode CHAR(8) PRIMARY KEY,
    city_id INTEGER NOT NULL,
    street TEXT,
    neighborhood TEXT,
    provider TEXT NOT NULL,
    latitude REAL,
    longitude REAL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (city_id) REFERENCES city(id)
);

CREATE INDEX idx_zipcode_city ON zipcode(city_id);
```

### `service_account`

```sql
CREATE TABLE service_account (
    id INTEGER PRIMARY KEY,
    service_name TEXT NOT NULL UNIQUE,
    service_token TEXT NOT NULL UNIQUE,
    is_active INTEGER NOT NULL DEFAULT 1,
    is_master INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
);
```

## Providers de CEP (fallback / cadeia)

Ordem implementada em `config/container.php`:

1. ViaCEP
2. AwesomeAPI
3. BrasilAPI v2
4. BrasilAPI v1
5. OpenCEP
6. ApiCEP

A busca deve ser implementada de forma **extensível**, permitindo adicionar providers no futuro.

### Regras da cadeia

- Consultar **primeiro o banco** (cache local). Se o CEP existir, **não** chamar provider.
- Caso não exista, consultar providers em sequência (preferindo os que retornam mais dados primeiro).
- Se o provider retornar erro, payload inválido ou dados insuficientes, seguir para o próximo.
- Parar no **primeiro provider válido**; se nenhum for válido, retornar erro “CEP não encontrado”.

### Providers e exemplos

#### ViaCEP

- Requisição: `GET https://viacep.com.br/ws/40330200/json/`

```json
{
  "cep": "40330-200",
  "logradouro": "Rua Conde de Porto Alegre",
  "complemento": "",
  "unidade": "",
  "bairro": "IAPI",
  "localidade": "Salvador",
  "uf": "BA",
  "estado": "Bahia",
  "regiao": "Nordeste",
  "ibge": "2927408",
  "gia": "",
  "ddd": "71",
  "siafi": "3849"
}
```

#### BrasilAPI v1

- Requisição: `GET https://brasilapi.com.br/api/cep/v1/40330200`

```json
{
  "cep": "40330200",
  "state": "BA",
  "city": "Salvador",
  "neighborhood": "IAPI",
  "street": "Rua Conde de Porto Alegre",
  "service": "open-cep"
}
```

#### BrasilAPI v2 (OpenStreetMap)

- Requisição: `GET https://brasilapi.com.br/api/cep/v2/40330200`

```json
{
  "cep": "40330200",
  "state": "BA",
  "city": "Salvador",
  "neighborhood": "IAPI",
  "street": "Rua Conde de Porto Alegre",
  "service": "open-cep",
  "timezoneName": null,
  "location": {
    "type": "Point",
    "coordinates": {
    }
  }
}
```

#### AwesomeAPI

- Requisição: `GET https://cep.awesomeapi.com.br/json/40330200`

```json
{
  "cep": "40330200",
  "address_type": "Rua",
  "address_name": "Conde de Porto Alegre",
  "address": "Rua Conde de Porto Alegre",
  "state": "BA",
  "district": "IAPI",
  "lat": "-12.9541279",
  "lng": "-38.481378",
  "city": "Salvador",
  "city_ibge": "2927408",
  "ddd": "71"
}
```

#### OpenCEP

- Requisição: `GET https://opencep.com/v1/40330200`

```json
{
  "cep": "40330-200",
  "logradouro": "Rua Conde de Porto Alegre",
  "complemento": "lado par",
  "bairro": "IAPI",
  "localidade": "Salvador",
  "uf": "BA",
  "ibge": "2927408"
}
```

#### ApiCEP

- Requisição: `GET https://cdn.apicep.com/file/apicep/40330-200.json`
- Observação: o CEP é enviado **mascarado** no path: `#####-###.json`

```json
{
  "code": "40330-200",
  "state": "BA",
  "city": "Salvador",
  "district": "IAPI",
  "address": "Rua Conde de Porto Alegre - lado par",
  "status": 200,
  "ok": true,
  "statusText": "ok"
}
```

## Normalização e persistência

### Sempre salvar

- **CEP**: sem máscara (somente 8 dígitos)
- **UF**: `UPPERCASE`
- **Textos**: `trim()` (remover espaços)

### Cidade com slug/alias

Salvar também um alias/slug (ex.: `normalized_name`) por cidade, para lidar com:

- acentos
- cidades duplicadas
- inconsistência entre providers

## Fluxo de consulta

1. Recebe CEP e converte para **apenas dígitos** (remove letras, símbolos e espaços).
2. Valida que o CEP tem **8 dígitos**.
3. Busca no banco (`zipcode`).
4. Se existir, retorna o resultado do banco.
5. Se não existir, chama os providers em cadeia até achar um válido.
6. Ao localizar:
   - valida/cadastra **estado (UF)** se necessário
   - valida/cadastra **cidade** se necessário
   - grava o **CEP** (com provider e timestamp)

## Autenticação (não é API pública)

Obrigatório para endpoints de consulta (exceto `/api/install`):

```http
X-Service-Key: core-api
X-Service-Token: xxxxxxxxx
```

- `X-Service-Token` deve existir em `service_account.service_token`
- A conta deve estar ativa: `is_active = 1`
- Token é auto gerado com 64 caracteres hex:

```php
bin2hex(random_bytes(32));
```

## Endpoint — Consulta de CEP

```http
GET /api/getcep/40330200
```

- O CEP na URL pode vir com máscara, mas deve ser normalizado para 8 dígitos.

### Resposta de sucesso (HTTP 200)

```json
{
  "status": true,
  "message": "",
  "zipcode": "41810025",
  "street": "Rua X",
  "neighborhood": "Pituba",
  "lat": "-12.9541279",
  "lng": "-38.481378",
  "city": {
    "id": 123,
    "name": "Salvador",
    "ibge_code": 2910800
  },
  "state": {
    "id": 5,
    "abbr": "BA"
  }
}
```

### Erros

Em erro, `status` deve ser `false` e `message` deve conter o motivo (string).

## Endpoint — Reverse geocode (Nominatim)

```http
GET /api/reverse-geocode?lat=-12.974&lng=-38.5014
```

```http
POST /api/reverse-geocode
Content-Type: application/json
```

Parâmetros `lat` e `lng` via query (GET) ou body JSON (POST):

```json
{
  "lat": -12.9714,
  "lng": -38.5014
}
```

Fluxo:

1. Consultar Nominatim: `GET /reverse?format=jsonv2&lat={lat}&lon={lng}`
2. Extrair CEP de `address.postcode` (somente Brasil, 8 dígitos)
3. Consultar CEP via fluxo padrão (cache + providers)
4. Se providers falharem, usar dados do Nominatim como fallback (`provider: nominatim`)
5. Retornar envelope padrão; `lat`/`lng` da resposta = valores da requisição

Requer autenticação (`X-Service-Key` + `X-Service-Token`).

Variáveis: `NOMINATIM_BASE_URL`, `NOMINATIM_USER_AGENT`.

Implementação:

| Classe | Responsabilidade |
|--------|------------------|
| `NominatimClient` | HTTP GET `/reverse?format=jsonv2` |
| `NominatimAddressMapper` | Extrai CEP, UF, cidade, logradouro do payload |
| `ReverseGeocodeService` | Orquestra Nominatim → `CepLookupService` → fallback Nominatim |
| `ReverseGeocodeAction` | GET (query) e POST (JSON body) |

## Endpoint — Instalação

Criar endpoint **sem autenticação**:

```http
GET /api/install
```

Deve:

- criar o banco e as tabelas
- criar um `service_account` com `service_name = admin`
- gerar e retornar o token
- essa conta deve ser **master**: `is_master = 1`

## CRUD de `service_account` (administração)

Deve existir CRUD para administrar `service_account`, mas **somente** contas com `is_master = 1` podem acessar.

## CLI — Importação IBGE

Script: `bin/import-ibge.php`

Bootstrap: `ContainerBuilder` + `config/container.php` (mesmo padrão de `public/index.php`).

```bash
php bin/import-ibge.php [--db=./data/zipcode.db] [--state=BA] [--dry-run] [--help]
```

| Flag | Descrição |
|------|-----------|
| `--db` | Sobrescreve `DB_PATH` |
| `--state=UF` | Importa só uma UF (útil para teste) |
| `--dry-run` | Simula sem gravar |
| `--help` | Ajuda |

### Comportamento

1. Garantir país Brasil via `CountryRepository::findOrCreateDefault()` (`default_country` em settings).
2. **Estados** — insert-only (`GET .../localidades/estados`):
   - `id` → `state.ibge_code`
   - `sigla` → `state.abbr` (uppercase)
   - `nome` → `state.name`
   - Se UF já existe (`country_id` + `abbr`): pular (não atualizar).
3. **Municípios** — upsert por `ibge_code` (`GET .../estados/{id}/municipios` por UF):
   - `id` → `city.ibge_code`
   - `nome` → `city.name`
   - `normalized_name` via `Normalizer::citySlug()`
   - Novo: INSERT; existente: UPDATE `name`, `normalized_name`, `state_id` se necessário.
4. Uma transação PDO por UF na importação de municípios (rollback em erro).

### Saída esperada

```
IBGE import started
States: 27 fetched, 2 inserted, 25 skipped
BA: 417 municipalities, 10 created, 407 updated, 0 unchanged
Done in 45s
```

Exit code: `0` sucesso, `1` erro fatal.

### Docker

```bash
docker compose exec php php bin/import-ibge.php
docker compose run --rm php php bin/import-ibge.php --state=BA
```

Requer rebuild da imagem após alterações em `bin/` ou código PHP (`docker compose up --build`).

### Implementação

| Classe | Responsabilidade |
|--------|------------------|
| `IbgeLocalidadesClient` | HTTP GET estados e municípios (Guzzle, timeout 30s) |
| `IbgeLocalidadesImporter` | Orquestra importação, estatísticas, transação por UF |
| `StateRepository::insertIfNotExists` | Insert-only de UF |
| `CityRepository::findByIbgeCode` | Busca por código IBGE |
| `CityRepository::upsertFromIbge` | Insert ou update de município |

Registrados em `config/container.php`: `IbgeLocalidadesClient`, `IbgeLocalidadesImporter`, cliente HTTP `ibge.http`.

API IBGE:

- Estados: `GET {IBGE_BASE_URL}/estados`
- Municípios: `GET {IBGE_BASE_URL}/estados/{ibge_state_id}/municipios`

## CLI — Servidor de desenvolvimento

Script: `zipcodeserver` (raiz do projeto)

```bash
php zipcodeserver [--host=127.0.0.1] [--port=8090] [--db=./data/zipcode.db] [--install-enabled=true|false]
```

Bootstrap Slim via `public/index.php`. Porta padrão **8090** (alinhada ao `docker-compose.yml`).

## Variáveis de ambiente (referência)

| Variável | Padrão | Uso |
|----------|--------|-----|
| `DB_PATH` | `{raiz}/data/zipcode.db` | SQLite |
| `INSTALL_ENABLED` | `true` | Bloqueia `/api/install` se `false` |
| `DISPLAY_ERROR_DETAILS` | `false` | Slim error middleware |
| `NOMINATIM_BASE_URL` | `https://nominatim.openstreetmap.org` | Reverse geocode |
| `NOMINATIM_USER_AGENT` | `ZipcodeMicroservice/1.0 (dev-local)` | Reverse geocode (403 se genérico) |
| `IBGE_BASE_URL` | `https://servicodados.ibge.gov.br/api/v1/localidades` | CLI import IBGE |
