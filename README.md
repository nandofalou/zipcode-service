# Microserviço de CEP

API interna de consulta de CEP com **Slim 4**, **SQLite (WAL)** e providers externos em cadeia. Empacotado em **PHP 8.4 FPM Alpine** + **Nginx Alpine**.

## Requisitos

- Docker e Docker Compose

## Subir o ambiente

```bash
docker compose up --build -d
```

A API fica em `http://localhost:8080`.

## Bootstrap

### 1. Instalar banco e conta admin

```bash
curl -s http://localhost:8080/api/install | jq
```

Guarde o `service_token` retornado (exibido apenas na primeira instalação).

### 2. Consultar CEP

```bash
curl -s http://localhost:8080/api/getcep/40330200 \
  -H "X-Service-Key: admin" \
  -H "X-Service-Token: SEU_TOKEN_AQUI" | jq
```

## Variáveis de ambiente

| Variável | Padrão | Descrição |
|----------|--------|-----------|
| `DB_PATH` | `{raiz}/data/zipcode.db` | Caminho do SQLite (Docker usa `/app/data/...`) |
| `INSTALL_ENABLED` | `true` | `false` bloqueia `/api/install` (403) |
| `DISPLAY_ERROR_DETAILS` | `false` | Detalhes de erro Slim |
| `NOMINATIM_BASE_URL` | `https://nominatim.openstreetmap.org` | Base da API Nominatim |
| `NOMINATIM_USER_AGENT` | `zipcodeservice/1.0 (...)` | User-Agent obrigatório para Nominatim |

## Documentação

| Documento | Público | Conteúdo |
|-----------|---------|----------|
| [docs/INTEGRACAO.md](docs/INTEGRACAO.md) | Serviços integradores | Consulta de CEP e reverse geocode |
| [docs/ADMINISTRACAO.md](docs/ADMINISTRACAO.md) | Administradores (master) | Install, service accounts, listagem e exclusão de CEPs |

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

Opcionalmente, você pode executar diretamente o servidor embutido:

```bash
DB_PATH=./data/zipcode.db INSTALL_ENABLED=true \
php -S localhost:8000 -t public public/index.php
```

No Linux, também é possível usar o script como executável:

```bash
chmod +x zipcodeserver
./zipcodeserver --port=8000
```
