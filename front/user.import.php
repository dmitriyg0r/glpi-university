<?php

declare(strict_types=1);

require_once(__DIR__ . '/_check_webserver_config.php');

use Glpi\Application\View\TemplateRenderer;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\User\Import\SpreadsheetUserImporter;
use Glpi\User\Import\SpreadsheetUserImportTemplateGenerator;

if (!User::canCreate()) {
    throw new AccessDeniedHttpException();
}

$importer = new SpreadsheetUserImporter();
$references = $importer->getReferenceData();

if (isset($_GET['download_template'])) {
    $target = GLPI_TMP_DIR . '/user_import_template.xlsx';
    (new SpreadsheetUserImportTemplateGenerator())->generate($target);

    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="user_import_template.xlsx"');
    header('Content-Length: ' . (string) filesize($target));
    readfile($target);
    exit;
}

$form = [
    'sheet'            => '',
    'mode'             => 'upsert',
    'default_profile'  => '',
    'default_entity'   => '0',
    'default_location' => '',
    'default_language' => 'ru_RU',
    'default_active'   => '1',
    'update_passwords' => 0,
];
$importResult = null;
$globalError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'sheet'            => trim((string) ($_POST['sheet'] ?? '')),
        'mode'             => trim((string) ($_POST['mode'] ?? 'upsert')),
        'default_profile'  => trim((string) ($_POST['default_profile'] ?? '')),
        'default_entity'   => trim((string) ($_POST['default_entity'] ?? '0')),
        'default_location' => trim((string) ($_POST['default_location'] ?? '')),
        'default_language' => trim((string) ($_POST['default_language'] ?? '')),
        'default_active'   => trim((string) ($_POST['default_active'] ?? '1')),
        'update_passwords' => isset($_POST['update_passwords']) ? 1 : 0,
    ];

    $uploadedFile = $_FILES['import_file'] ?? null;
    if (!is_array($uploadedFile) || (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $globalError = 'Выберите файл таблицы для импорта.';
    } else {
        $extension = strtolower((string) pathinfo((string) $uploadedFile['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, SpreadsheetUserImporter::getSupportedExtensions(), true)) {
            $globalError = 'Неподдерживаемый формат файла. Используйте XLSX, XLS или CSV.';
        } else {
            $target = GLPI_TMP_DIR . '/' . uniqid('user-import-', true) . '.' . $extension;
            if (!move_uploaded_file((string) $uploadedFile['tmp_name'], $target)) {
                $globalError = 'Не удалось прочитать загруженный файл.';
            } else {
                // Файл перемещён — убираем из $_FILES, иначе Html::footer()
                // попытается переоткрыть уже несуществующий temp-файл через Symfony Request.
                unset($_FILES['import_file']);
                try {
                    $importResult = $importer->importFile($target, [
                        'sheet'            => $form['sheet'],
                        'mode'             => $form['mode'],
                        'dry_run'          => ($_POST['run_mode'] ?? 'dry-run') !== 'execute',
                        'update_passwords' => (bool) $form['update_passwords'],
                        'default_profile'  => $form['default_profile'],
                        'default_entity'   => $form['default_entity'],
                        'default_location' => $form['default_location'],
                        'default_language' => $form['default_language'],
                        'default_active'   => $form['default_active'],
                    ]);
                } catch (Throwable $e) {
                    $globalError = $e->getMessage();
                } finally {
                    @unlink($target);
                }
            }
        }
    }
}

$defaultProfileOptions = ['' => 'Из файла'] + $references['profiles'];
$defaultEntityOptions = ['' => 'Из файла'] + $references['entities'];
$defaultLocationOptions = ['' => 'Из файла'] + $references['locations'];
$defaultActiveOptions = [
    ''  => 'Из файла',
    '1' => 'Да',
    '0' => 'Нет',
];
$modeOptions = [
    'create' => 'Только создание',
    'update' => 'Только обновление',
    'upsert' => 'Создание и обновление',
];

Html::header('Импорт пользователей из таблицы', '', 'admin', 'user');

TemplateRenderer::getInstance()->display('pages/admin/user/import_users.html.twig', [
    'title'                   => 'Импорт пользователей из таблицы',
    'form'                    => $form,
    'mode_options'            => $modeOptions,
    'default_profile_options' => $defaultProfileOptions,
    'default_entity_options'  => $defaultEntityOptions,
    'default_location_options'=> $defaultLocationOptions,
    'default_active_options'  => $defaultActiveOptions,
    'references'              => $references,
    'supported_extensions'    => SpreadsheetUserImporter::getSupportedExtensions(),
    'supported_columns'       => SpreadsheetUserImporter::getSupportedColumns(),
    'import_result'           => $importResult,
    'global_error'            => $globalError,
]);

Html::footer();
