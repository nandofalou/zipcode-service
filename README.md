# Microserviço de CEP

API interna para **consulta de CEP**, **reverse geocode** (coordenadas → endereço) e **importação de estados/municípios do IBGE**. Stack: **Slim 4**, **SQLite (WAL)**, providers externos em cadeia e **PHP 8.4 FPM Alpine** + **Nginx Alpine**.

## Recursos

| Recurso | Tipo | Descrição |
|---------|------|-----------|
| Consulta de CEP | API (`GET /api/getcep/{cep}`) | Cache SQLite + fallback em 6 providers |
| Reverse geocode | API (`GET/POST /api/reverse-geocode`) | Nominatim → extrai CEP → fluxo padrão de CEP |
| Importação IBGE | CLI (`bin/import-ibge.php`) | Estados (insert-only) e municípios (upsert por `ibge_code`) |
| Administração | API (master) | Install, service accounts, listagem/exclusão de CEPs |

## Requisitos

- Docker e Docker Compose (produção/desenvolvimento containerizado)
- PHP 8.4+ e Composer (desenvolvimento local)

## Subir o ambiente

```bash
docker compose up --build -d
```

A API fica em `http://localhost:8090`.

## Bootstrap

### 1. Instalar banco e conta admin

```bash
curl -s http://localhost:8090/api/install | jq
```

Guarde o `service_token` retornado (exibido apenas na primeira instalação).

### 2. (Recomendado) Importar estados e municípios do IBGE

Popula `state` e `city` com códigos IBGE oficiais antes das consultas de CEP:

```bash
# Teste com uma UF
docker compose exec php php bin/import-ibge.php --state=BA --dry-run

# Importação completa (~5.570 municípios, ~30–90s)
docker compose exec php php bin/import-ibge.php
```

Local (sem Docker):

```bash
php bin/import-ibge.php --state=BA
php bin/import-ibge.php
```

Detalhes em [docs/ADMINISTRACAO.md](docs/ADMINISTRACAO.md#importação-ibge-cli).

### 3. Consultar CEP

```bash
curl -s http://localhost:8090/api/getcep/40330200 \
  -H "X-Service-Key: admin" \
  -H "X-Service-Token: SEU_TOKEN_AQUI" | jq
```

### 4. Reverse geocode

```bash
curl -s "http://localhost:8090/api/reverse-geocode?lat=-12.974&lng=-38.5014" \
  -H "X-Service-Key: admin" \
  -H "X-Service-Token: SEU_TOKEN_AQUI" | jq
```

## Variáveis de ambiente

| Variável | Padrão | Descrição |
|----------|--------|-----------|
| `DB_PATH` | `{raiz}/data/zipcode.db` | Caminho do SQLite (Docker: `/app/data/zipcode.db`) |
| `INSTALL_ENABLED` | `true` | `false` bloqueia `/api/install` (403) |
| `DISPLAY_ERROR_DETAILS` | `false` | Detalhes de erro Slim |
| `NOMINATIM_BASE_URL` | `https://nominatim.openstreetmap.org` | Base da API Nominatim (reverse geocode) |
| `NOMINATIM_USER_AGENT` | `ZipcodeMicroservice/1.0 (dev-local)` | User-Agent obrigatório para Nominatim |
| `IBGE_BASE_URL` | `https://servicodados.ibge.gov.br/api/v1/localidades` | Base da API IBGE (CLI import) |

## Documentação

| Documento | Público | Conteúdo |
|-----------|---------|----------|
| [docs/INTEGRACAO.md](docs/INTEGRACAO.md) | Serviços integradores | Consulta de CEP e reverse geocode |
| [docs/ADMINISTRACAO.md](docs/ADMINISTRACAO.md) | Administradores (master) | Install, service accounts, CEPs, importação IBGE |
| [GUIADEV.md](GUIADEV.md) | Desenvolvedores | Especificação técnica completa |

## Endpoints (resumo)

| Método | Rota | Auth |
|--------|------|------|
| GET | `/api/install` | Nenhuma |
| GET | `/api/getcep/{cep}` | Service |
| GET/POST | `/api/reverse-geocode` | Service |
| GET | `/api/service-accounts` | Master |
| POST | `/api/service-accounts` | Master |
| PUT | `/api/service-accounts/{id}` | Master |
| DELETE | `/api/service-accounts/{id}` | Master |
| GET | `/api/zipcodes` | Master |
| DELETE | `/api/zipcodes/{cep}` | Master |

## Scripts CLI

| Script | Descrição |
|--------|-----------|
| `bin/import-ibge.php` | Importa estados/municípios da API IBGE |
| `zipcodeserver` | Servidor PHP embutido para desenvolvimento local |

### Importação IBGE

```bash
php bin/import-ibge.php [--db=./data/zipcode.db] [--state=BA] [--dry-run] [--help]
```

| Flag | Descrição |
|------|-----------|
| `--db=PATH` | Sobrescreve `DB_PATH` |
| `--state=UF` | Importa apenas uma UF |
| `--dry-run` | Simula sem gravar |
| `--help` | Ajuda |

Saída exemplo:

```
IBGE import started
States: 27 fetched, 2 inserted, 25 skipped
BA: 417 municipalities, 10 created, 407 updated, 0 unchanged
Done in 45s
```

Exit code: `0` sucesso, `1` erro fatal.

## Providers (ordem de consulta)

1. ViaCEP
2. AwesomeAPI
3. BrasilAPI v2
4. BrasilAPI v1
5. OpenCEP
6. ApiCEP

A consulta verifica o SQLite antes de chamar providers externos.

## Dados persistentes

O banco fica em `./data/zipcode.db` (volume montado no container `php`).

## Desenvolvimento local (sem Docker)

```bash
composer install
mkdir -p data
php zipcodeserver
```

Servidor em `http://127.0.0.1:8090` (porta padrão do script).

Opcionalmente, servidor embutido direto:

```bash
DB_PATH=./data/zipcode.db INSTALL_ENABLED=true \
php -S localhost:8090 -t public public/index.php
```

Executável no Linux:

```bash
chmod +x zipcodeserver bin/import-ibge.php
./zipcodeserver --port=8090
./bin/import-ibge.php --state=BA --dry-run
```

Após alterações no código usado pelo container, rebuild: `docker compose up --build`.
