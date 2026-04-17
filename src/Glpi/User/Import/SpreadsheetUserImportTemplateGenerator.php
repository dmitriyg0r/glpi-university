<?php

declare(strict_types=1);

namespace Glpi\User\Import;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class SpreadsheetUserImportTemplateGenerator
{
    public function generate(string $target): string
    {
        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('users');

        $headers = SpreadsheetUserImporter::getSupportedColumns();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            [
                'excel.alexey',
                'Алексей',
                'Смирнов',
                'Secret123!',
                'excel.alexey@example.local',
                '+7 495 000-00-01',
                '',
                '+7 999 000-00-01',
                'Головная организация',
                'Self-Service',
                'Москва HQ',
                'ru_RU',
                '1',
                'Imported from Excel',
            ],
            [
                'excel.nikita',
                'Никита',
                'Орлов',
                'Secret123!',
                'excel.nikita@example.local',
                '',
                '',
                '+7 999 000-00-02',
                '0',
                'Technician',
                'Санкт-Петербург',
                'ru_RU',
                'yes',
                'Support user',
            ],
        ], null, 'A2');

        foreach (range('A', 'N') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        $reference = $spreadsheet->createSheet();
        $reference->setTitle('reference');
        $reference->fromArray(
            [
                ['Field', 'Description', 'Accepted values'],
                ['login', 'Required GLPI login', 'Unique login'],
                ['firstname', 'First name', 'Optional'],
                ['realname', 'Last name / surname', 'Optional'],
                ['password', 'Password', 'Required for new users'],
                ['email', 'Default email', 'Optional'],
                ['phone', 'Phone', 'Optional'],
                ['phone2', 'Additional phone', 'Optional'],
                ['mobile', 'Mobile phone', 'Optional'],
                ['entity', 'Entity name or ID', 'Example: Головная организация or 0'],
                ['profile', 'Profile name or ID', 'Example: Self-Service, Technician, Hotliner'],
                ['location', 'Location name or ID', 'Example: Москва HQ'],
                ['language', 'Language code', 'Example: ru_RU'],
                ['is_active', 'User active flag', '1/0, yes/no, true/false'],
                ['comment', 'Comment', 'Optional'],
            ],
            null,
            'A1'
        );

        foreach (range('A', 'C') as $column) {
            $reference->getColumnDimension($column)->setAutoSize(true);
        }

        $validation = $sheet->getCell('M2')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1('"1,0,yes,no,true,false"');
        $sheet->duplicateStyle($sheet->getStyle('M2'), 'M2:M200');
        for ($row = 2; $row <= 200; $row++) {
            $sheet->getCell("M{$row}")->setDataValidation(clone $validation);
        }

        (new Xlsx($spreadsheet))->save($target);

        return $target;
    }
}
