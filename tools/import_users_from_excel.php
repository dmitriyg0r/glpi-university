<?php

declare(strict_types=1);

use Glpi\User\Import\SpreadsheetUserImporter;

require dirname(__DIR__) . '/vendor/autoload.php';

main($argv);

function main(array $argv): void
{
    $options = parseOptions();
    if (array_key_exists('help', $options)) {
        printHelp();
        return;
    }

    $file = $options['file'] ?? null;
    if (!is_string($file) || $file === '') {
        fwrite(STDERR, "--file is required\n");
        exit(1);
    }
    if (!is_file($file)) {
        fwrite(STDERR, "File not found: {$file}\n");
        exit(1);
    }

    $mode = strtolower((string) ($options['mode'] ?? 'upsert'));
    if (!in_array($mode, ['create', 'update', 'upsert'], true)) {
        fwrite(STDERR, "Unsupported --mode value: {$mode}\n");
        exit(1);
    }
    $dryRun = array_key_exists('dry-run', $options);
    $updatePasswords = array_key_exists('update-passwords', $options);

    bootGlpi((string) ($options['as-user'] ?? 'glpi'));

    $importer = new SpreadsheetUserImporter();
    $result = $importer->importFile($file, [
        'sheet'            => $options['sheet'] ?? null,
        'mode'             => $mode,
        'dry_run'          => $dryRun,
        'update_passwords' => $updatePasswords,
        'default_profile'  => $options['default-profile'] ?? '',
        'default_entity'   => $options['default-entity'] ?? '0',
        'default_location' => $options['default-location'] ?? '',
        'default_language' => $options['default-language'] ?? '',
        'default_active'   => $options['default-active'] ?? '1',
    ]);

    foreach ($result['rows'] as $rowResult) {
        echo sprintf(
            "Row %d [%s]: %s (%s)\n",
            $rowResult['row'],
            $rowResult['login'],
            $rowResult['status'],
            $rowResult['message']
        );
    }

    echo PHP_EOL . "Summary\n";
    foreach ($result['stats'] as $key => $value) {
        echo sprintf("  %s: %d\n", $key, $value);
    }
}

function parseOptions(): array
{
    $options = getopt('', [
        'file:',
        'sheet::',
        'mode::',
        'dry-run',
        'update-passwords',
        'as-user::',
        'default-profile::',
        'default-entity::',
        'default-location::',
        'default-language::',
        'default-active::',
        'help',
    ]);

    return is_array($options) ? $options : [];
}

function printHelp(): void
{
    echo <<<TXT
Usage:
  php tools/import_users_from_excel.php --file=/absolute/or/relative/path.xlsx [options]

Options:
  --file=PATH              XLSX, XLS or CSV file to import
  --sheet=NAME             Sheet name to read, default is the active sheet
  --mode=create|update|upsert
                           create: only new users
                           update: only existing users
                           upsert: create missing and update existing
  --dry-run                Validate and print actions without writing to GLPI
  --update-passwords       Apply password changes for existing users
  --as-user=LOGIN          GLPI user used to execute the import, default: glpi
  --default-profile=VALUE  Fallback profile name or ID
  --default-entity=VALUE   Fallback entity name or ID
  --default-location=VALUE Fallback location name or ID
  --default-language=CODE  Fallback language code, example: ru_RU
  --default-active=VALUE   Fallback active flag, examples: 1 / yes / no
  --help                   Show this help

Supported columns:
  %s

TXT;
    printf($help, implode(', ', SpreadsheetUserImporter::getSupportedColumns()));
}

function bootGlpi(string $runAsUser): array
{
    $kernel = new Glpi\Kernel\Kernel();
    $kernel->boot();

    if (session_status() !== PHP_SESSION_ACTIVE) {
        Session::start();
    }

    $user = new User();
    if (!$user->getFromDBbyName($runAsUser)) {
        throw new RuntimeException("Unable to load GLPI user '{$runAsUser}' for import execution.");
    }

    $auth = new Auth();
    $auth->auth_succeded = true;
    $auth->user = $user;
    Session::init($auth);
    Session::loadLanguage();

    return [
        'kernel' => $kernel,
        'user'   => $user,
    ];
}
