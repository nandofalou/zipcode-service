CREATE TABLE IF NOT EXISTS country (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    alphacode2 CHAR(2) NOT NULL UNIQUE,
    alphacode3 CHAR(3),
    numcode INTEGER
);

CREATE INDEX IF NOT EXISTS idx_country_name ON country(name);

CREATE TABLE IF NOT EXISTS state (
    id INTEGER PRIMARY KEY,
    country_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    abbr CHAR(5) NOT NULL,
    ibge_code INTEGER,
    FOREIGN KEY (country_id) REFERENCES country(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_state_country_abbr ON state(country_id, abbr);
CREATE INDEX IF NOT EXISTS idx_state_name ON state(name);

CREATE TABLE IF NOT EXISTS city (
    id INTEGER PRIMARY KEY,
    state_id INTEGER NOT NULL,
    ibge_code INTEGER UNIQUE,
    name TEXT NOT NULL,
    latitude REAL,
    longitude REAL,
    normalized_name TEXT NOT NULL,
    FOREIGN KEY (state_id) REFERENCES state(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_city_state_name ON city(state_id, normalized_name);
CREATE INDEX IF NOT EXISTS idx_city_ibge_code ON city(ibge_code);
CREATE INDEX IF NOT EXISTS idx_city_geo ON city(latitude, longitude);

CREATE TABLE IF NOT EXISTS zipcode (
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

CREATE INDEX IF NOT EXISTS idx_zipcode_city ON zipcode(city_id);

CREATE TABLE IF NOT EXISTS service_account (
    id INTEGER PRIMARY KEY,
    service_name TEXT NOT NULL UNIQUE,
    service_token TEXT NOT NULL UNIQUE,
    is_active INTEGER NOT NULL DEFAULT 1,
    is_master INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
);
