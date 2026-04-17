<?php

declare(strict_types=1);

use Glpi\User\Import\SpreadsheetUserImportTemplateGenerator;

require dirname(__DIR__) . '/vendor/autoload.php';

$target = $argv[1] ?? (__DIR__ . '/templates/user_import_template.xlsx');
$generator = new SpreadsheetUserImportTemplateGenerator();
echo $generator->generate($target) . PHP_EOL;
