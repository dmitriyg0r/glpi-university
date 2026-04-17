<?php

declare(strict_types=1);

use Glpi\Kernel\Kernel;

require dirname(__DIR__) . '/vendor/autoload.php';

$kernel = new Kernel();
$kernel->boot();

if (session_status() !== PHP_SESSION_ACTIVE) {
    Session::start();
}

loadAdminSession('glpi');

$entityId = 0;
$sharedPassword = 'DemoPass123!';

$profiles = [
    'self-service' => 1,
    'hotliner'     => 5,
    'technician'   => 6,
];

$summary = [
    'locations'          => [],
    'manufacturers'      => [],
    'computer_types'     => [],
    'printer_types'      => [],
    'network_types'      => [],
    'states'             => [],
    'ticket_categories'  => [],
    'users'              => [],
    'computers'          => [],
    'monitors'           => [],
    'printers'           => [],
    'networkequipments'  => [],
    'tickets'            => [],
];

$locations = [
    ['name' => 'Москва HQ',       'parent' => 0],
    ['name' => 'Санкт-Петербург', 'parent' => 0],
    ['name' => 'Серверная',       'parent' => 'Москва HQ'],
    ['name' => 'Склад IT',        'parent' => 'Москва HQ'],
];

$locationIds = [];
foreach ($locations as $location) {
    $parentId = 0;
    if (is_string($location['parent'])) {
        $parentId = $locationIds[$location['parent']] ?? 0;
    }
    $id = ensureRecord(new Location(), [
        'name'       => $location['name'],
        'entities_id' => $entityId,
        'locations_id' => $parentId,
    ]);
    $locationIds[$location['name']] = $id;
    $summary['locations'][] = [$location['name'], $id];
}

$manufacturerIds = [];
foreach (['Dell', 'Lenovo', 'HP', 'Cisco', 'APC'] as $name) {
    $id = ensureRecord(new Manufacturer(), ['name' => $name]);
    $manufacturerIds[$name] = $id;
    $summary['manufacturers'][] = [$name, $id];
}

$computerTypeIds = [];
foreach (['Ноутбук', 'Рабочая станция'] as $name) {
    $id = ensureRecord(new ComputerType(), ['name' => $name]);
    $computerTypeIds[$name] = $id;
    $summary['computer_types'][] = [$name, $id];
}

$printerTypeIds = [];
foreach (['Лазерный принтер', 'МФУ'] as $name) {
    $id = ensureRecord(new PrinterType(), ['name' => $name]);
    $printerTypeIds[$name] = $id;
    $summary['printer_types'][] = [$name, $id];
}

$networkTypeIds = [];
foreach (['Коммутатор', 'Маршрутизатор'] as $name) {
    $id = ensureRecord(new NetworkEquipmentType(), ['name' => $name]);
    $networkTypeIds[$name] = $id;
    $summary['network_types'][] = [$name, $id];
}

$stateIds = [];
foreach (['В эксплуатации', 'На складе', 'В ремонте'] as $name) {
    $id = ensureRecord(new State(), ['name' => $name]);
    $stateIds[$name] = $id;
    $summary['states'][] = [$name, $id];
}

$ticketCategoryIds = [];
foreach ([
    ['name' => 'Принтеры',            'code' => 'PRN'],
    ['name' => 'Рабочие станции',     'code' => 'PC'],
    ['name' => 'Сеть и доступ',       'code' => 'NET'],
    ['name' => 'Учетные записи',      'code' => 'ACC'],
    ['name' => 'Периферия',           'code' => 'PER'],
] as $category) {
    $id = ensureRecord(new ITILCategory(), [
        'name'              => $category['name'],
        'itilcategories_id' => 0,
        'code'              => $category['code'],
    ]);
    $ticketCategoryIds[$category['name']] = $id;
    $summary['ticket_categories'][] = [$category['name'], $id];
}

$userSpecs = [
    [
        'login'       => 'demo.alexey',
        'firstname'   => 'Алексей',
        'realname'    => 'Иванов',
        'email'       => 'alexey.ivanov@example.local',
        'profile_id'  => $profiles['self-service'],
        'location'    => 'Москва HQ',
    ],
    [
        'login'       => 'demo.maria',
        'firstname'   => 'Мария',
        'realname'    => 'Петрова',
        'email'       => 'maria.petrova@example.local',
        'profile_id'  => $profiles['self-service'],
        'location'    => 'Москва HQ',
    ],
    [
        'login'       => 'demo.oleg',
        'firstname'   => 'Олег',
        'realname'    => 'Смирнов',
        'email'       => 'oleg.smirnov@example.local',
        'profile_id'  => $profiles['self-service'],
        'location'    => 'Санкт-Петербург',
    ],
    [
        'login'       => 'demo.elena',
        'firstname'   => 'Елена',
        'realname'    => 'Кузнецова',
        'email'       => 'elena.k@example.local',
        'profile_id'  => $profiles['self-service'],
        'location'    => 'Санкт-Петербург',
    ],
    [
        'login'       => 'support.nikita',
        'firstname'   => 'Никита',
        'realname'    => 'Орлов',
        'email'       => 'nikita.orlov@example.local',
        'profile_id'  => $profiles['technician'],
        'location'    => 'Москва HQ',
    ],
    [
        'login'       => 'support.irina',
        'firstname'   => 'Ирина',
        'realname'    => 'Волкова',
        'email'       => 'irina.volkova@example.local',
        'profile_id'  => $profiles['hotliner'],
        'location'    => 'Москва HQ',
    ],
];

$userIds = [];
foreach ($userSpecs as $spec) {
    $user = new User();
    if ($user->getFromDBbyName($spec['login'])) {
        $id = (int) $user->getID();
    } else {
        $id = (int) $user->add([
            'name'         => $spec['login'],
            'password'     => $sharedPassword,
            'password2'    => $sharedPassword,
            'firstname'    => $spec['firstname'],
            'realname'     => $spec['realname'],
            'email'        => $spec['email'],
            'entities_id'  => $entityId,
            '_entities_id' => $entityId,
            '_profiles_id' => $spec['profile_id'],
            'is_active'    => 1,
        ]);
        if ($id <= 0) {
            throw new RuntimeException('Failed to create user ' . $spec['login']);
        }
        $user->update([
            'id'           => $id,
            'locations_id' => $locationIds[$spec['location']],
        ]);
    }
    $userIds[$spec['login']] = $id;
    $summary['users'][] = [$spec['login'], $id];
}

$techAssignees = [
    $userIds['support.nikita'],
    $userIds['support.irina'],
];

$computerSpecs = [
    [
        'name'          => 'NB-MSK-001',
        'user'          => 'demo.alexey',
        'manufacturer'  => 'Dell',
        'type'          => 'Ноутбук',
        'location'      => 'Москва HQ',
        'state'         => 'В эксплуатации',
        'serial'        => 'DL-NB-001',
    ],
    [
        'name'          => 'NB-MSK-002',
        'user'          => 'demo.maria',
        'manufacturer'  => 'Lenovo',
        'type'          => 'Ноутбук',
        'location'      => 'Москва HQ',
        'state'         => 'В эксплуатации',
        'serial'        => 'LN-NB-002',
    ],
    [
        'name'          => 'WS-SPB-001',
        'user'          => 'demo.oleg',
        'manufacturer'  => 'HP',
        'type'          => 'Рабочая станция',
        'location'      => 'Санкт-Петербург',
        'state'         => 'В эксплуатации',
        'serial'        => 'HP-WS-001',
    ],
    [
        'name'          => 'WS-SPB-002',
        'user'          => 'demo.elena',
        'manufacturer'  => 'Dell',
        'type'          => 'Рабочая станция',
        'location'      => 'Санкт-Петербург',
        'state'         => 'В эксплуатации',
        'serial'        => 'DL-WS-002',
    ],
    [
        'name'          => 'NB-STOCK-003',
        'user'          => null,
        'manufacturer'  => 'Lenovo',
        'type'          => 'Ноутбук',
        'location'      => 'Склад IT',
        'state'         => 'На складе',
        'serial'        => 'LN-NB-003',
    ],
];

$computerIds = [];
foreach ($computerSpecs as $spec) {
    $computer = new Computer();
    $id = findByName($computer, $spec['name']);
    if ($id === null) {
        $id = (int) $computer->add([
            'name'              => $spec['name'],
            'entities_id'       => $entityId,
            'users_id'          => $spec['user'] ? $userIds[$spec['user']] : 0,
            'manufacturers_id'  => $manufacturerIds[$spec['manufacturer']],
            'computertypes_id'  => $computerTypeIds[$spec['type']],
            'locations_id'      => $locationIds[$spec['location']],
            'states_id'         => $stateIds[$spec['state']],
            'serial'            => $spec['serial'],
            'comment'           => 'Demo asset generated automatically',
        ]);
        if ($id <= 0) {
            throw new RuntimeException('Failed to create computer ' . $spec['name']);
        }
    }
    $computerIds[$spec['name']] = $id;
    $summary['computers'][] = [$spec['name'], $id];
}

$monitorSpecs = [
    ['name' => 'MON-MSK-001', 'user' => 'demo.alexey', 'manufacturer' => 'Dell',   'location' => 'Москва HQ',       'state' => 'В эксплуатации'],
    ['name' => 'MON-MSK-002', 'user' => 'demo.maria',  'manufacturer' => 'Dell',   'location' => 'Москва HQ',       'state' => 'В эксплуатации'],
    ['name' => 'MON-SPB-001', 'user' => 'demo.oleg',   'manufacturer' => 'HP',     'location' => 'Санкт-Петербург', 'state' => 'В эксплуатации'],
    ['name' => 'MON-STOCK-001', 'user' => null,        'manufacturer' => 'Lenovo', 'location' => 'Склад IT',        'state' => 'На складе'],
];

$monitorIds = [];
foreach ($monitorSpecs as $spec) {
    $monitor = new Monitor();
    $id = findByName($monitor, $spec['name']);
    if ($id === null) {
        $id = (int) $monitor->add([
            'name'             => $spec['name'],
            'entities_id'      => $entityId,
            'users_id'         => $spec['user'] ? $userIds[$spec['user']] : 0,
            'manufacturers_id' => $manufacturerIds[$spec['manufacturer']],
            'locations_id'     => $locationIds[$spec['location']],
            'states_id'        => $stateIds[$spec['state']],
            'comment'          => 'Demo monitor generated automatically',
        ]);
        if ($id <= 0) {
            throw new RuntimeException('Failed to create monitor ' . $spec['name']);
        }
    }
    $monitorIds[$spec['name']] = $id;
    $summary['monitors'][] = [$spec['name'], $id];
}

$printerSpecs = [
    ['name' => 'PRN-MSK-01', 'manufacturer' => 'HP', 'type' => 'Лазерный принтер', 'location' => 'Москва HQ',       'state' => 'В эксплуатации', 'serial' => 'HP-PRN-01'],
    ['name' => 'MFP-SPB-01', 'manufacturer' => 'HP', 'type' => 'МФУ',               'location' => 'Санкт-Петербург', 'state' => 'В эксплуатации', 'serial' => 'HP-MFP-01'],
    ['name' => 'PRN-STOCK-01', 'manufacturer' => 'Dell', 'type' => 'Лазерный принтер', 'location' => 'Склад IT', 'state' => 'На складе', 'serial' => 'DL-PRN-01'],
];

$printerIds = [];
foreach ($printerSpecs as $spec) {
    $printer = new Printer();
    $id = findByName($printer, $spec['name']);
    if ($id === null) {
        $id = (int) $printer->add([
            'name'             => $spec['name'],
            'entities_id'      => $entityId,
            'manufacturers_id' => $manufacturerIds[$spec['manufacturer']],
            'printertypes_id'  => $printerTypeIds[$spec['type']],
            'locations_id'     => $locationIds[$spec['location']],
            'states_id'        => $stateIds[$spec['state']],
            'serial'           => $spec['serial'],
            'comment'          => 'Demo printer generated automatically',
        ]);
        if ($id <= 0) {
            throw new RuntimeException('Failed to create printer ' . $spec['name']);
        }
    }
    $printerIds[$spec['name']] = $id;
    $summary['printers'][] = [$spec['name'], $id];
}

$networkSpecs = [
    ['name' => 'SW-MSK-CORE-01', 'manufacturer' => 'Cisco', 'type' => 'Коммутатор',   'location' => 'Серверная', 'state' => 'В эксплуатации', 'serial' => 'CS-SW-01'],
    ['name' => 'RTR-MSK-EDGE-01', 'manufacturer' => 'Cisco', 'type' => 'Маршрутизатор', 'location' => 'Серверная', 'state' => 'В эксплуатации', 'serial' => 'CS-RT-01'],
    ['name' => 'SW-SPB-FLR-01', 'manufacturer' => 'Cisco', 'type' => 'Коммутатор',     'location' => 'Санкт-Петербург', 'state' => 'В эксплуатации', 'serial' => 'CS-SW-02'],
];

$networkIds = [];
foreach ($networkSpecs as $spec) {
    $equipment = new NetworkEquipment();
    $id = findByName($equipment, $spec['name']);
    if ($id === null) {
        $id = (int) $equipment->add([
            'name'                      => $spec['name'],
            'entities_id'               => $entityId,
            'manufacturers_id'          => $manufacturerIds[$spec['manufacturer']],
            'networkequipmenttypes_id'  => $networkTypeIds[$spec['type']],
            'locations_id'              => $locationIds[$spec['location']],
            'states_id'                 => $stateIds[$spec['state']],
            'serial'                    => $spec['serial'],
            'comment'                   => 'Demo network equipment generated automatically',
        ]);
        if ($id <= 0) {
            throw new RuntimeException('Failed to create network equipment ' . $spec['name']);
        }
    }
    $networkIds[$spec['name']] = $id;
    $summary['networkequipments'][] = [$spec['name'], $id];
}

$ticketSpecs = [
    [
        'name'       => 'Не работает Wi-Fi на ноутбуке NB-MSK-001',
        'content'    => 'После обновления системы ноутбук перестал подключаться к корпоративному Wi-Fi.',
        'category'   => 'Сеть и доступ',
        'requester'  => 'demo.alexey',
        'assignee'   => $techAssignees[0],
        'urgency'    => 4,
        'impact'     => 3,
        'itemtype'   => 'Computer',
        'items_id'   => $computerIds['NB-MSK-001'],
        'timeline'   => [
            'status'              => CommonITILObject::ASSIGNED,
            'date'                => '2026-03-24 09:12:00',
            'takeintoaccountdate' => '2026-03-24 09:36:00',
            'date_mod'            => '2026-03-24 11:05:00',
        ],
    ],
    [
        'name'       => 'Заканчивается тонер в PRN-MSK-01',
        'content'    => 'Принтер печатает бледно, нужен новый картридж.',
        'category'   => 'Принтеры',
        'requester'  => 'demo.maria',
        'assignee'   => $techAssignees[1],
        'urgency'    => 2,
        'impact'     => 2,
        'itemtype'   => 'Printer',
        'items_id'   => $printerIds['PRN-MSK-01'],
        'timeline'   => [
            'status'              => CommonITILObject::SOLVED,
            'date'                => '2026-03-26 10:03:00',
            'takeintoaccountdate' => '2026-03-26 10:25:00',
            'solvedate'           => '2026-03-26 15:40:00',
            'date_mod'            => '2026-03-26 15:50:00',
        ],
    ],
    [
        'name'       => 'Монитор MON-SPB-001 мигает',
        'content'    => 'Экран периодически гаснет на 1-2 секунды. Нужна диагностика.',
        'category'   => 'Периферия',
        'requester'  => 'demo.oleg',
        'assignee'   => $techAssignees[0],
        'urgency'    => 3,
        'impact'     => 2,
        'itemtype'   => 'Monitor',
        'items_id'   => $monitorIds['MON-SPB-001'],
        'timeline'   => [
            'status'              => CommonITILObject::WAITING,
            'date'                => '2026-03-31 14:10:00',
            'takeintoaccountdate' => '2026-03-31 14:50:00',
            'begin_waiting_date'  => '2026-04-01 10:00:00',
            'date_mod'            => '2026-04-12 17:20:00',
        ],
    ],
    [
        'name'       => 'Нужен доступ к VPN для нового сотрудника',
        'content'    => 'Требуется настроить удаленный доступ для работы из дома.',
        'category'   => 'Учетные записи',
        'requester'  => 'demo.elena',
        'assignee'   => $techAssignees[1],
        'urgency'    => 3,
        'impact'     => 3,
        'itemtype'   => 'Computer',
        'items_id'   => $computerIds['WS-SPB-002'],
        'timeline'   => [
            'status'              => CommonITILObject::CLOSED,
            'date'                => '2026-04-02 09:00:00',
            'takeintoaccountdate' => '2026-04-02 09:10:00',
            'solvedate'           => '2026-04-03 16:15:00',
            'closedate'           => '2026-04-04 12:00:00',
            'date_mod'            => '2026-04-04 12:00:00',
        ],
    ],
    [
        'name'       => 'Проверить пропадание линка на SW-MSK-CORE-01',
        'content'    => 'В серверной были кратковременные обрывы связи на коммутаторе ядра.',
        'category'   => 'Сеть и доступ',
        'requester'  => 'demo.alexey',
        'assignee'   => $techAssignees[0],
        'urgency'    => 5,
        'impact'     => 4,
        'itemtype'   => 'NetworkEquipment',
        'items_id'   => $networkIds['SW-MSK-CORE-01'],
        'timeline'   => [
            'status'              => CommonITILObject::PLANNED,
            'date'                => '2026-04-07 08:45:00',
            'takeintoaccountdate' => '2026-04-07 09:30:00',
            'date_mod'            => '2026-04-10 11:20:00',
        ],
    ],
    [
        'name'       => 'Подготовить ноутбук NB-STOCK-003 для нового сотрудника',
        'content'    => 'Нужно выдать ноутбук со стандартным набором ПО и доменной учетной записью.',
        'category'   => 'Рабочие станции',
        'requester'  => 'demo.maria',
        'assignee'   => $techAssignees[1],
        'urgency'    => 2,
        'impact'     => 2,
        'itemtype'   => 'Computer',
        'items_id'   => $computerIds['NB-STOCK-003'],
        'timeline'   => [
            'status'              => CommonITILObject::INCOMING,
            'date'                => '2026-04-11 13:15:00',
            'date_mod'            => '2026-04-11 13:15:00',
        ],
    ],
    [
        'name'       => 'МФУ MFP-SPB-01 сканирует с полосами',
        'content'    => 'При сканировании на стекле появляются вертикальные полосы.',
        'category'   => 'Принтеры',
        'requester'  => 'demo.oleg',
        'assignee'   => $techAssignees[1],
        'urgency'    => 2,
        'impact'     => 2,
        'itemtype'   => 'Printer',
        'items_id'   => $printerIds['MFP-SPB-01'],
        'timeline'   => [
            'status'              => CommonITILObject::SOLVED,
            'date'                => '2026-04-13 10:40:00',
            'takeintoaccountdate' => '2026-04-13 11:20:00',
            'solvedate'           => '2026-04-14 18:05:00',
            'date_mod'            => '2026-04-14 18:20:00',
        ],
    ],
    [
        'name'       => 'Слишком медленно работает WS-SPB-001',
        'content'    => 'После запуска CRM и браузера рабочая станция начинает сильно тормозить.',
        'category'   => 'Рабочие станции',
        'requester'  => 'demo.oleg',
        'assignee'   => $techAssignees[0],
        'urgency'    => 3,
        'impact'     => 3,
        'itemtype'   => 'Computer',
        'items_id'   => $computerIds['WS-SPB-001'],
        'timeline'   => [
            'status'              => CommonITILObject::ASSIGNED,
            'date'                => '2026-04-15 09:05:00',
            'takeintoaccountdate' => '2026-04-15 09:50:00',
            'date_mod'            => '2026-04-16 10:15:00',
        ],
    ],
];

foreach ($ticketSpecs as $spec) {
    $ticket = new Ticket();
    $existingId = findTicketByName($spec['name']);
    if ($existingId === null) {
        $id = (int) $ticket->add([
            'name'                 => $spec['name'],
            'content'              => $spec['content'],
            'entities_id'          => $entityId,
            'itilcategories_id'    => $ticketCategoryIds[$spec['category']],
            'type'                 => Ticket::INCIDENT_TYPE,
            'status'               => CommonITILObject::INCOMING,
            'urgency'              => $spec['urgency'],
            'impact'               => $spec['impact'],
            '_users_id_requester'  => $userIds[$spec['requester']],
            '_users_id_assign'     => $spec['assignee'],
            'items_id'             => [
                $spec['itemtype'] => [$spec['items_id']],
            ],
        ]);
        if ($id <= 0) {
            throw new RuntimeException('Failed to create ticket ' . $spec['name']);
        }
        applyTicketTimeline($id, $spec['timeline'], $spec['assignee']);
        $summary['tickets'][] = [$spec['name'], $id];
    } else {
        $ticket->update([
            'id'               => $existingId,
            'itilcategories_id' => $ticketCategoryIds[$spec['category']],
        ]);
        applyTicketTimeline($existingId, $spec['timeline'], $spec['assignee']);
        $summary['tickets'][] = [$spec['name'], $existingId];
    }
}

echo json_encode([
    'shared_password' => $sharedPassword,
    'summary'         => $summary,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

function loadAdminSession(string $username): void
{
    $user = new User();
    if (!$user->getFromDBbyName($username)) {
        throw new RuntimeException('Admin user not found: ' . $username);
    }

    $auth = new Auth();
    $auth->auth_succeded = true;
    $auth->user = $user;
    Session::init($auth);
    Session::loadLanguage();
}

function ensureRecord(CommonDBTM $item, array $input): int
{
    $existingId = findByName($item, (string) $input['name']);
    if ($existingId !== null) {
        return $existingId;
    }

    $id = (int) $item->add($input);
    if ($id <= 0) {
        throw new RuntimeException('Failed to create ' . $item::class . ' "' . $input['name'] . '"');
    }

    return $id;
}

function findByName(CommonDBTM $item, string $name): ?int
{
    global $DB;

    if (method_exists($item, 'getFromDBbyName') && $item->getFromDBbyName($name)) {
        return (int) $item->getID();
    }

    $iterator = $DB->request([
        'SELECT' => ['id'],
        'FROM'   => $item::getTable(),
        'WHERE'  => ['name' => $name],
        'LIMIT'  => 1,
    ]);

    foreach ($iterator as $row) {
        return (int) $row['id'];
    }

    return null;
}

function findTicketByName(string $name): ?int
{
    global $DB;

    $iterator = $DB->request([
        'SELECT' => ['id'],
        'FROM'   => Ticket::getTable(),
        'WHERE'  => ['name' => $name],
        'LIMIT'  => 1,
    ]);

    foreach ($iterator as $row) {
        return (int) $row['id'];
    }

    return null;
}

function applyTicketTimeline(int $ticketId, array $timeline, int $lastUpdaterId): void
{
    global $DB;

    $date = $timeline['date'];
    $take = $timeline['takeintoaccountdate'] ?? null;
    $solve = $timeline['solvedate'] ?? null;
    $close = $timeline['closedate'] ?? null;
    $beginWaiting = $timeline['begin_waiting_date'] ?? null;
    $dateMod = $timeline['date_mod'] ?? ($close ?? $solve ?? $take ?? $date);

    $params = [
        'status'                    => $timeline['status'],
        'date'                      => $date,
        'date_creation'             => $date,
        'date_mod'                  => $dateMod,
        'takeintoaccountdate'       => $take,
        'solvedate'                 => $solve,
        'closedate'                 => $close,
        'begin_waiting_date'        => $beginWaiting,
        'users_id_lastupdater'      => $lastUpdaterId,
        'takeintoaccount_delay_stat'=> $take ? secondsBetween($date, $take) : 0,
        'solve_delay_stat'          => $solve ? secondsBetween($date, $solve) : 0,
        'close_delay_stat'          => $close ? secondsBetween($date, $close) : 0,
        'waiting_duration'          => ($beginWaiting && $timeline['status'] === CommonITILObject::WAITING)
            ? secondsBetween($beginWaiting, $dateMod)
            : 0,
        'sla_waiting_duration'      => 0,
        'ola_waiting_duration'      => 0,
    ];

    $DB->update(Ticket::getTable(), $params, ['id' => $ticketId]);
}

function secondsBetween(string $from, string $to): int
{
    return max(0, strtotime($to) - strtotime($from));
}
