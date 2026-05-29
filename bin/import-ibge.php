#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Application\Import\IbgeLocalidadesImporter;
use DI\ContainerBuilder;

function usage(): void
{
    $script = basename(__FILE__);
    $help = <<<TXT
Importação IBGE — estados e municípios

Uso:
  php {$script} [--db=./data/zipcode.db] [--state=BA] [--dry-run] [--help]

Opções:
  --db=PATH       Caminho do SQLite (padrão: ./data/zipcode.db ou DB_PATH)
  --state=UF      Importa apenas uma UF (ex.: BA)
  --dry-run       Simula sem gravar no banco
  --help          Exibe esta ajuda

Exemplos:
  php {$script} --state=BA --dry-run
  php {$script} --state=BA
  docker compose exec php php bin/import-ibge.php

TXT;

    fwrite(STDOUT, $help);
}

/** @return array<string, mixed> */
function parseArgs(array $argv): array
{
    $out = [];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            $out['help'] = true;
            continue;
        }
        if ($arg === '--dry-run') {
            $out['dry-run'] = true;
            continue;
        }
        if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
            [$k, $v] = explode('=', substr($arg, 2), 2);
            $out[$k] = $v;
        }
    }

    return $out;
}

$args = parseArgs($argv);
if (!empty($args['help'])) {
    usage();
    exit(0);
}

$root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
require $root . '/vendor/autoload.php';

$dbPath = getenv('DB_PATH');
if ($dbPath === false || trim($dbPath) === '') {
    $dbPath = (string) ($args['db'] ?? './data/zipcode.db');
}

if (str_starts_with($dbPath, './data/') || $dbPath === './data/zipcode.db') {
    $dataDir = $root . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0775, true);
    }
}

putenv('DB_PATH=' . $dbPath);

$dryRun = !empty($args['dry-run']);
$stateFilter = isset($args['state']) ? (string) $args['state'] : null;

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions($root . '/config/container.php');
$container = $containerBuilder->build();

/** @var IbgeLocalidadesImporter $importer */
$importer = $container->get(IbgeLocalidadesImporter::class);

fwrite(STDOUT, "IBGE import started\n");
if ($dryRun) {
    fwrite(STDOUT, "Mode: dry-run (no writes)\n");
}
fwrite(STDOUT, "DB_PATH={$dbPath}\n");

try {
    $stats = $importer->import($dryRun, $stateFilter);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro fatal: ' . $e->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf(
    "States: %d fetched, %d inserted, %d skipped\n",
    (int) $stats['states_fetched'],
    (int) $stats['states_inserted'],
    (int) $stats['states_skipped'],
));

foreach ($stats['by_state'] as $abbr => $stateStats) {
    fwrite(STDOUT, sprintf(
        "%s: %d municipalities, %d created, %d updated, %d unchanged\n",
        $abbr,
        (int) $stateStats['fetched'],
        (int) $stateStats['created'],
        (int) $stateStats['updated'],
        (int) $stateStats['unchanged'],
    ));
}

foreach ($stats['errors'] as $error) {
    fwrite(STDERR, "Aviso: {$error}\n");
}

fwrite(STDOUT, sprintf("Done in %ss\n", $stats['elapsed_seconds']));

exit(0);
