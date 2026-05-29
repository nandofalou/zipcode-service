# Integração — Consulta de CEP

Documentação para serviços que consomem apenas a **busca de CEP**. Não inclui endpoints administrativos.

## Base URL

```
http://localhost:8080
```

Em produção, substitua pelo host interno do microserviço.

## Autenticação

Toda requisição autenticada deve enviar os headers:

| Header | Descrição |
|--------|-----------|
| `X-Service-Key` | Nome da conta (`service_name`) |
| `X-Service-Token` | Token da conta (`service_token`) |

A conta precisa estar ativa (`is_active = 1`). Tokens inválidos ou ausentes retornam **HTTP 401**.

```http
X-Service-Key: core-api
X-Service-Token: seu_token_aqui
```

## Consultar CEP

Busca um CEP no banco local. Se não existir, consulta providers externos, normaliza os dados, grava e retorna.

```http
GET /api/getcep/{cep}
```

### Parâmetro de rota

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `cep` | string | CEP com ou sem máscara. Letras e símbolos são ignorados. Deve resultar em **8 dígitos**. |

Exemplos válidos na URL:

- `/api/getcep/40330200`
- `/api/getcep/40330-200`

### Exemplo de requisição

```bash
curl -s "http://localhost:8080/api/getcep/40330200" \
  -H "X-Service-Key: core-api" \
  -H "X-Service-Token: SEU_TOKEN"
```

### Resposta de sucesso (HTTP 200)

```json
{
  "status": true,
  "message": "",
  "zipcode": "40330200",
  "street": "Rua Conde de Porto Alegre",
  "neighborhood": "IAPI",
  "lat": "-12.9541279",
  "lng": "-38.481378",
  "city": {
    "id": 1,
    "name": "Salvador",
    "ibge_code": 2927408
  },
  "state": {
    "id": 1,
    "abbr": "BA"
  }
}
```

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `status` | boolean | `true` quando o CEP foi encontrado |
| `message` | string | Vazio em sucesso; mensagem de erro quando `status` é `false` |
| `zipcode` | string | CEP com 8 dígitos, sem máscara |
| `street` | string | Logradouro |
| `neighborhood` | string | Bairro |
| `lat` | string | Latitude (pode ser vazio se o provider não retornar) |
| `lng` | string | Longitude (pode ser vazio se o provider não retornar) |
| `city.id` | integer | ID interno da cidade |
| `city.name` | string | Nome da cidade |
| `city.ibge_code` | integer \| null | Código IBGE do município |
| `state.id` | integer | ID interno do estado |
| `state.abbr` | string | UF em maiúsculas (ex.: `BA`) |

### Respostas de erro de negócio (HTTP 200)

Quando o CEP é inválido ou não encontrado, a API retorna **HTTP 200** com `status: false`:

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

### Erros de autenticação

| HTTP | Situação |
|------|----------|
| 401 | Headers ausentes ou credenciais inválidas/inativas |

```json
{
  "status": false,
  "message": "Autenticação obrigatória (X-Service-Key e X-Service-Token)."
}
```

## Comportamento da consulta

1. O CEP é normalizado para 8 dígitos numéricos.
2. O sistema busca no SQLite (cache local).
3. Se não existir, consulta providers externos em cadeia até obter um resultado válido.
4. Estado, cidade e CEP são persistidos com nomenclatura normalizada.
5. Consultas seguintes ao mesmo CEP usam apenas o banco local.

## Reverse geocode (coordenadas → endereço)

Converte latitude/longitude em endereço completo via **Nominatim**, extrai o CEP e reutiliza o fluxo de consulta de CEP. Se os providers de CEP não retornarem dados, usa o endereço do Nominatim como fallback.

Disponível via **GET** (query string) ou **POST** (JSON body).

```http
GET /api/reverse-geocode?lat=-12.974&lng=-38.5014
```

```http
POST /api/reverse-geocode
Content-Type: application/json
```

### Parâmetros

| Campo | Tipo | GET (query) | POST (body) | Descrição |
|-------|------|-------------|-------------|-----------|
| `lat` | number | sim | sim | Latitude (-90 a 90) |
| `lng` | number | sim | sim | Longitude (-180 a 180) |

### Exemplos de requisição

```bash
curl -s "http://localhost:8080/api/reverse-geocode?lat=-12.974&lng=-38.5014" \
  -H "X-Service-Key: core-api" \
  -H "X-Service-Token: SEU_TOKEN"
```

```bash
curl -s -X POST "http://localhost:8080/api/reverse-geocode" \
  -H "X-Service-Key: core-api" \
  -H "X-Service-Token: SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"lat": -12.9714, "lng": -38.5014}'
```

### Resposta de sucesso (HTTP 200)

```json
{
  "status": true,
  "message": "",
  "zipcode": "40050565",
  "street": "Ladeira da Fonte das Pedras",
  "neighborhood": "Nazaré",
  "lat": "-12.9714",
  "lng": "-38.5014",
  "city": {
    "id": 1,
    "name": "Salvador",
    "ibge_code": null
  },
  "state": {
    "id": 1,
    "abbr": "BA"
  }
}
```

Os campos `lat` e `lng` na resposta refletem os valores enviados na requisição.

### Erros comuns (HTTP 200, `status: false`)

```json
{
  "status": false,
  "message": "Campos lat e lng são obrigatórios."
}
```

```json
{
  "status": false,
  "message": "Endereço não encontrado ou fora do Brasil."
}
```

### Fluxo interno

1. Consulta Nominatim (`format=jsonv2`) com as coordenadas.
2. Extrai `address.postcode` (CEP brasileiro, 8 dígitos).
3. Consulta o CEP via fluxo padrão (`GET /api/getcep` equivalente).
4. Se providers de CEP falharem, persiste e retorna dados mapeados do Nominatim.

## Obtenção de credenciais

Contas de integração são criadas por um administrador (`is_master = 1`) via endpoint administrativo. Solicite ao time responsável:

- `service_name` → valor do header `X-Service-Key`
- `service_token` → valor do header `X-Service-Token`

O token é exibido **apenas na criação** da conta. Guarde-o em local seguro.

## Boas práticas

- Envie o CEP preferencialmente sem máscara (`40330200`).
- Trate `status === false` na resposta, não apenas o código HTTP.
- Não exponha o `X-Service-Token` em logs ou frontends públicos.
- Em caso de `401`, verifique se a conta continua ativa junto ao administrador.
