<?php

declare(strict_types=1);

namespace Glpi\User\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

final class SpreadsheetUserImporter
{
    private const SUPPORTED_COLUMNS = [
        'login',
        'firstname',
        'realname',
        'password',
        'email',
        'phone',
        'phone2',
        'mobile',
        'entity',
        'profile',
        'location',
        'language',
        'is_active',
        'comment',
    ];

    private const HEADER_ALIASES = [
        'login' => 'login',
        'name' => 'login',
        'user_login' => 'login',
        'username' => 'login',
        'логин' => 'login',
        'firstname' => 'firstname',
        'first_name' => 'firstname',
        'имя' => 'firstname',
        'realname' => 'realname',
        'last_name' => 'realname',
        'lastname' => 'realname',
        'surname' => 'realname',
        'фамилия' => 'realname',
        'password' => 'password',
        'пароль' => 'password',
        'email' => 'email',
        'e_mail' => 'email',
        'mail' => 'email',
        'почта' => 'email',
        'phone' => 'phone',
        'телефон' => 'phone',
        'phone2' => 'phone2',
        'телефон2' => 'phone2',
        'additional_phone' => 'phone2',
        'mobile' => 'mobile',
        'mobile_phone' => 'mobile',
        'мобильный' => 'mobile',
        'entity' => 'entity',
        'entity_id' => 'entity',
        'entity_name' => 'entity',
        'организация' => 'entity',
        'сущность' => 'entity',
        'profile' => 'profile',
        'profile_id' => 'profile',
        'profile_name' => 'profile',
        'профиль' => 'profile',
        'location' => 'location',
        'location_id' => 'location',
        'location_name' => 'location',
        'локация' => 'location',
        'language' => 'language',
        'lang' => 'language',
        'язык' => 'language',
        'is_active' => 'is_active',
        'active' => 'is_active',
        'enabled' => 'is_active',
        'активен' => 'is_active',
        'comment' => 'comment',
        'comments' => 'comment',
        'комментарий' => 'comment',
    ];

    private const TRUE_VALUES = ['1', 'true', 'yes', 'y', 'да', 'active', 'enabled'];
    private const FALSE_VALUES = ['0', 'false', 'no', 'n', 'нет', 'inactive', 'disabled'];

    public function importFile(string $file, array $options = []): array
    {
        if (!is_file($file)) {
            throw new RuntimeException(sprintf('File not found: %s', $file));
        }

        $options = $this->normalizeOptions($options);
        $context = $this->buildContext();
        $rows = $this->readSpreadsheetRows($file, $options['sheet']);

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors'  => 0,
        ];
        $results = [];

        foreach ($rows as $row) {
            $login = trim((string) ($row['data']['login'] ?? ''));

            try {
                $input = $this->normalizeUserRow($row['data'], $context, $options);
                if ($input === null) {
                    $stats['skipped']++;
                    $results[] = [
                        'row'     => $row['row'],
                        'login'   => $login,
                        'status'  => 'skipped',
                        'message' => 'Пустой логин — строка пропущена',
                    ];
                    continue;
                }

                $result = $this->importUserRow(
                    $input,
                    $options['mode'],
                    $options['dry_run'],
                    $options['update_passwords']
                );

                $stats[$result['status']]++;
                $results[] = [
                    'row'     => $row['row'],
                    'login'   => $input['login'],
                    'status'  => $result['status'],
                    'message' => $result['message'],
                ];
            } catch (Throwable $e) {
                $stats['errors']++;
                $results[] = [
                    'row'     => $row['row'],
                    'login'   => $login,
                    'status'  => 'errors',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'rows'       => $results,
            'stats'      => $stats,
            'options'    => $options,
            'references' => $this->getReferenceData(),
        ];
    }

    public function getReferenceData(): array
    {
        $context = $this->buildContext();

        return [
            'profiles'  => $context['profiles_by_id'],
            'entities'  => $context['entities_by_id'],
            'locations' => $context['locations_by_id'],
        ];
    }

    public static function getSupportedColumns(): array
    {
        return self::SUPPORTED_COLUMNS;
    }

    public static function getSupportedExtensions(): array
    {
        return ['xlsx', 'xls', 'csv'];
    }

    private function normalizeOptions(array $options): array
    {
        $mode = strtolower((string) ($options['mode'] ?? 'upsert'));
        if (!in_array($mode, ['create', 'update', 'upsert'], true)) {
            throw new RuntimeException(sprintf('Unsupported import mode: %s', $mode));
        }

        return [
            'sheet'            => isset($options['sheet']) && trim((string) $options['sheet']) !== ''
                ? trim((string) $options['sheet'])
                : null,
            'mode'             => $mode,
            'dry_run'          => (bool) ($options['dry_run'] ?? false),
            'update_passwords' => (bool) ($options['update_passwords'] ?? false),
            'default_profile'  => trim((string) ($options['default_profile'] ?? '')),
            'default_entity'   => trim((string) ($options['default_entity'] ?? '0')),
            'default_location' => trim((string) ($options['default_location'] ?? '')),
            'default_language' => trim((string) ($options['default_language'] ?? '')),
            'default_active'   => trim((string) ($options['default_active'] ?? '1')),
        ];
    }

    private function buildContext(): array
    {
        global $DB;

        $profilesById = [];
        $profilesByName = [];
        foreach ($DB->request([
            'FROM'   => 'glpi_profiles',
            'SELECT' => ['id', 'name'],
            'ORDER'  => 'id',
        ]) as $row) {
            $profilesById[(int) $row['id']] = (string) $row['name'];
            $profilesByName[mb_strtolower(trim((string) $row['name']))] = (int) $row['id'];
        }

        $entitiesById = [];
        $entitiesByName = [];
        foreach ($DB->request([
            'FROM'   => 'glpi_entities',
            'SELECT' => ['id', 'name', 'completename'],
            'ORDER'  => 'id',
        ]) as $row) {
            $id = (int) $row['id'];
            $displayName = trim((string) ($row['completename'] ?: $row['name']));
            $entitiesById[$id] = $displayName;
            $entitiesByName[mb_strtolower(trim((string) $row['name']))] = $id;
            $entitiesByName[mb_strtolower($displayName)] = $id;
        }

        $locationsById = [];
        $locationsByName = [];
        foreach ($DB->request([
            'FROM'   => 'glpi_locations',
            'SELECT' => ['id', 'name', 'completename'],
            'ORDER'  => 'id',
        ]) as $row) {
            $id = (int) $row['id'];
            $displayName = trim((string) $row['completename']);
            $locationsById[$id] = $displayName;
            $locationsByName[mb_strtolower(trim((string) $row['name']))] = $id;
            $locationsByName[mb_strtolower($displayName)] = $id;
        }

        return [
            'profiles_by_id'    => $profilesById,
            'profiles_by_name'  => $profilesByName,
            'entities_by_id'    => $entitiesById,
            'entities_by_name'  => $entitiesByName,
            'locations_by_id'   => $locationsById,
            'locations_by_name' => $locationsByName,
        ];
    }

    private function readSpreadsheetRows(string $file, ?string $sheetName): array
    {
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($file);
        $worksheet = $sheetName !== null
            ? $spreadsheet->getSheetByName($sheetName)
            : $spreadsheet->getActiveSheet();

        if (!$worksheet instanceof Worksheet) {
            throw new RuntimeException('Указанный лист не найден в файле.');
        }

        $highestColumnIndex = Coordinate::columnIndexFromString($worksheet->getHighestColumn());
        $highestRow = $worksheet->getHighestRow();

        $headers = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $rawHeader = trim((string) $worksheet->getCell([$col, 1])->getFormattedValue());
            $headers[$col] = $this->normalizeHeader($rawHeader);
        }

        $rows = [];
        for ($row = 2; $row <= $highestRow; $row++) {
            $data = [];
            $isEmpty = true;
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $header = $headers[$col] ?? null;
                if ($header === null) {
                    continue;
                }

                $value = trim((string) $worksheet->getCell([$col, $row])->getFormattedValue());
                if ($value !== '') {
                    $isEmpty = false;
                }
                $data[$header] = $value;
            }

            if ($isEmpty) {
                continue;
            }

            $rows[] = [
                'row'  => $row,
                'data' => $data,
            ];
        }

        return $rows;
    }

    private function normalizeHeader(string $rawHeader): ?string
    {
        if ($rawHeader === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($rawHeader));
        $normalized = str_replace([' ', '-', '.'], '_', $normalized);

        return self::HEADER_ALIASES[$normalized]
            ?? (in_array($normalized, self::SUPPORTED_COLUMNS, true) ? $normalized : null);
    }

    private function normalizeUserRow(array $row, array $context, array $options): ?array
    {
        $login = trim((string) ($row['login'] ?? ''));
        if ($login === '') {
            return null;
        }

        $profileValue = $this->valueOrDefault($row['profile'] ?? '', $options['default_profile']);
        $entityValue = $this->valueOrDefault($row['entity'] ?? '', $options['default_entity']);
        $locationValue = $this->valueOrDefault($row['location'] ?? '', $options['default_location']);
        $languageValue = $this->valueOrDefault($row['language'] ?? '', $options['default_language']);
        $activeValue = $this->valueOrDefault($row['is_active'] ?? '', $options['default_active']);

        $profileId = $this->resolveReference(
            $profileValue,
            $context['profiles_by_id'],
            $context['profiles_by_name'],
            'профиль'
        );
        $entityId = $this->resolveReference(
            $entityValue,
            $context['entities_by_id'],
            $context['entities_by_name'],
            'организацию'
        );
        $locationId = $locationValue === ''
            ? 0
            : $this->resolveReference(
                $locationValue,
                $context['locations_by_id'],
                $context['locations_by_name'],
                'локацию'
            );

        return [
            'login'       => $login,
            'firstname'   => trim((string) ($row['firstname'] ?? '')),
            'realname'    => trim((string) ($row['realname'] ?? '')),
            'password'    => trim((string) ($row['password'] ?? '')),
            'email'       => trim((string) ($row['email'] ?? '')),
            'phone'       => trim((string) ($row['phone'] ?? '')),
            'phone2'      => trim((string) ($row['phone2'] ?? '')),
            'mobile'      => trim((string) ($row['mobile'] ?? '')),
            'entity_id'   => $entityId,
            'profile_id'  => $profileId,
            'location_id' => $locationId,
            'language'    => $languageValue,
            'is_active'   => $this->parseBoolean($activeValue) ? 1 : 0,
            'comment'     => trim((string) ($row['comment'] ?? '')),
        ];
    }

    private function valueOrDefault(string $value, string $default): string
    {
        return trim($value) !== '' ? trim($value) : trim($default);
    }

    private function resolveReference(string $value, array $byId, array $byName, string $label): int
    {
        if ($value === '') {
            throw new RuntimeException(sprintf('Не указано значение для поля «%s».', $label));
        }

        if (ctype_digit($value)) {
            $id = (int) $value;
            if (!array_key_exists($id, $byId)) {
                throw new RuntimeException(sprintf('Неизвестный ID %1$s: %2$s', $label, $value));
            }
            return $id;
        }

        $key = mb_strtolower(trim($value));
        if (!array_key_exists($key, $byName)) {
            throw new RuntimeException(sprintf('Неизвестное значение %1$s: «%2$s»', $label, $value));
        }

        return (int) $byName[$key];
    }

    private function parseBoolean(string $value): bool
    {
        $normalized = mb_strtolower(trim($value));
        if ($normalized === '') {
            return true;
        }
        if (in_array($normalized, self::TRUE_VALUES, true)) {
            return true;
        }
        if (in_array($normalized, self::FALSE_VALUES, true)) {
            return false;
        }

        throw new RuntimeException(sprintf('Неподдерживаемое логическое значение: «%s»', $value));
    }

    private function importUserRow(array $input, string $mode, bool $dryRun, bool $updatePasswords): array
    {
        $user = new \User();
        $exists = $user->getFromDBbyName($input['login']);

        if (!$exists && $mode === 'update') {
            return ['status' => 'skipped', 'message' => 'Пользователь не найден'];
        }
        if ($exists && $mode === 'create') {
            return ['status' => 'skipped', 'message' => 'Пользователь уже существует'];
        }
        if (!$exists && $input['password'] === '') {
            throw new RuntimeException('Для нового пользователя необходимо указать пароль.');
        }

        if ($dryRun) {
            return [
                'status'  => $exists ? 'updated' : 'created',
                'message' => $exists ? 'Будет обновлён' : 'Будет создан',
            ];
        }

        if (!$exists) {
            $createdId = $this->createUser($input);
            return [
                'status'  => 'created',
                'message' => sprintf('Создан пользователь (ID %d)', $createdId),
            ];
        }

        $this->updateUser($user, $input, $updatePasswords);
        return [
            'status'  => 'updated',
            'message' => 'Данные обновлены',
        ];
    }

    private function createUser(array $input): int
    {
        $user = new \User();

        $payload = [
            'name'         => $input['login'],
            'password'     => $input['password'],
            'password2'    => $input['password'],
            'firstname'    => $input['firstname'],
            'realname'     => $input['realname'],
            'phone'        => $input['phone'],
            'phone2'       => $input['phone2'],
            'mobile'       => $input['mobile'],
            'comment'      => $input['comment'],
            'language'     => $input['language'],
            'is_active'    => $input['is_active'],
            'entities_id'  => $input['entity_id'],
            '_entities_id' => $input['entity_id'],
            'profiles_id'  => $input['profile_id'],
            '_profiles_id' => $input['profile_id'],
            'locations_id' => $input['location_id'],
        ];

        if ($input['email'] !== '') {
            $payload['_useremails'] = ['-1' => $input['email']];
            $payload['_default_email'] = '-1';
        }

        $id = (int) $user->add($payload);
        if ($id <= 0) {
            throw new RuntimeException(sprintf('Не удалось создать пользователя «%s»', $input['login']));
        }

        $this->syncProfileAuthorization($id, $input['profile_id'], $input['entity_id']);

        return $id;
    }

    private function updateUser(\User $user, array $input, bool $updatePasswords): void
    {
        $this->syncProfileAuthorization((int) $user->getID(), $input['profile_id'], $input['entity_id']);

        $payload = [
            'id'           => (int) $user->getID(),
            'firstname'    => $input['firstname'],
            'realname'     => $input['realname'],
            'phone'        => $input['phone'],
            'phone2'       => $input['phone2'],
            'mobile'       => $input['mobile'],
            'comment'      => $input['comment'],
            'language'     => $input['language'],
            'is_active'    => $input['is_active'],
            'entities_id'  => $input['entity_id'],
            'profiles_id'  => $input['profile_id'],
            'locations_id' => $input['location_id'],
        ];

        if ($updatePasswords && $input['password'] !== '') {
            $payload['password'] = $input['password'];
            $payload['password2'] = $input['password'];
        }

        if (!$user->update($payload)) {
            throw new RuntimeException(sprintf('Не удалось обновить пользователя «%s»', $input['login']));
        }

        if ($input['email'] !== '') {
            $this->syncUserEmail((int) $user->getID(), $input['email']);
        }
    }

    private function syncProfileAuthorization(int $userId, int $profileId, int $entityId): void
    {
        $profileUser = new \Profile_User();
        $existing = $profileUser->find([
            'users_id'    => $userId,
            'profiles_id' => $profileId,
            'entities_id' => $entityId,
        ]);

        if ($existing === []) {
            $added = $profileUser->add([
                'users_id'           => $userId,
                'profiles_id'        => $profileId,
                'entities_id'        => $entityId,
                'is_recursive'       => 0,
                'is_dynamic'         => 0,
                'is_default_profile' => 1,
            ]);
            if ((int) $added <= 0) {
                throw new RuntimeException(sprintf('Не удалось назначить профиль пользователю с ID %d', $userId));
            }
        }

        foreach ($profileUser->find(['users_id' => $userId]) as $row) {
            $profileUser->update([
                'id'                 => (int) $row['id'],
                'is_default_profile' => ((int) $row['profiles_id'] === $profileId
                    && (int) $row['entities_id'] === $entityId) ? 1 : 0,
            ]);
        }
    }

    private function syncUserEmail(int $userId, string $email): void
    {
        global $DB;

        $userEmail = new \UserEmail();
        $existingId = null;

        foreach ($DB->request([
            'FROM'   => 'glpi_useremails',
            'SELECT' => ['id', 'email'],
            'WHERE'  => ['users_id' => $userId],
        ]) as $row) {
            if (mb_strtolower((string) $row['email']) === mb_strtolower($email)) {
                $existingId = (int) $row['id'];
            }
        }

        if ($existingId === null) {
            $existingId = (int) $userEmail->add([
                'users_id'   => $userId,
                'email'      => $email,
                'is_default' => 1,
            ]);
            if ($existingId <= 0) {
                throw new RuntimeException(sprintf('Не удалось добавить email %1$s пользователю с ID %2$d', $email, $userId));
            }
        }

        foreach ($DB->request([
            'FROM'   => 'glpi_useremails',
            'SELECT' => ['id'],
            'WHERE'  => ['users_id' => $userId],
        ]) as $row) {
            $userEmail->update([
                'id'         => (int) $row['id'],
                'is_default' => ((int) $row['id'] === $existingId) ? 1 : 0,
            ]);
        }
    }
}
