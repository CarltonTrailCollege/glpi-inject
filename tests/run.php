<?php

declare(strict_types=1);

namespace {
    define('GLPI_PLUGIN_DOC_DIR', __DIR__ . '/fixtures');
    define('PLUGIN_GLPIINJECT_UPLOAD_DIR', GLPI_PLUGIN_DOC_DIR . '/glpiinject');
    define('CREATE', 1);
    define('UPDATE', 2);

    function __(string $message, string $domain = ''): string
    {
        return $message;
    }

    function _n(string $singular, string $plural, int $count, string $domain = ''): string
    {
        return $count === 1 ? $singular : $plural;
    }

    class CommonDBTM
    {
        public array $fields = [];
        public static array $records = [];
        public static bool $allow_item_write = true;
        public static bool $allow_item_read = true;

        public static function getTable($classname = null): string
        {
            return 'test_items';
        }

        public function isEntityAssign(): bool
        {
            return false;
        }

        public function canViewItem(): bool
        {
            return static::$allow_item_read;
        }

        public function can(int $id, int $right, array $input = []): bool
        {
            return static::$allow_item_write;
        }

        public function find(array $criteria, array $order = [], int $limit = 0): array
        {
            $matches = [];
            foreach (static::$records as $id => $record) {
                foreach ($criteria as $field => $value) {
                    if (($record[$field] ?? null) !== $value) {
                        continue 2;
                    }
                }
                $matches[$id] = ['id' => $id] + $record;
                if ($limit > 0 && count($matches) >= $limit) {
                    break;
                }
            }
            return $matches;
        }

        public function getFromDB(int $id): bool
        {
            if (!isset(static::$records[$id])) {
                return false;
            }
            $this->fields = ['id' => $id] + static::$records[$id];
            return true;
        }

        public function getFromDBByCrit(array $criteria): bool
        {
            foreach (static::$records as $id => $record) {
                $matches = true;
                foreach ($criteria as $field => $value) {
                    if (($record[$field] ?? null) !== $value) {
                        $matches = false;
                        break;
                    }
                }
                if ($matches) {
                    $this->fields = ['id' => $id] + $record;
                    return true;
                }
            }

            return false;
        }

        public function getID(): int
        {
            return (int) ($this->fields['id'] ?? 0);
        }

        public function canCreate(): bool
        {
            return true;
        }

        public function canUpdate(): bool
        {
            return true;
        }

        public function add(array $input): int
        {
            $id = count(static::$records) + 1;
            static::$records[$id] = $input;
            $this->fields = ['id' => $id] + $input;
            return $id;
        }

        public function update(array $input): bool
        {
            $id = (int) $input['id'];
            unset($input['id']);
            static::$records[$id] = array_merge(static::$records[$id], $input);
            $this->fields = ['id' => $id] + static::$records[$id];
            return true;
        }
    }

    final class User extends CommonDBTM
    {
        public static array $records = [];

        public function getFromDB(int $id): bool
        {
            if (!isset(self::$records[$id])) {
                return false;
            }
            $this->fields = ['id' => $id] + self::$records[$id];
            return true;
        }

        public function canViewItem(): bool
        {
            return (bool) ($this->fields['readable'] ?? true);
        }

        public static function getTypeName(int $count = 0): string
        {
            return 'User';
        }
    }

    final class TestUserDatabase
    {
        public int $requests = 0;

        public function request(array $query): iterable
        {
            $this->requests++;
            assert($query['FROM'] === User::getTable());
            assert($query['WHERE']['is_deleted'] === 0);
            foreach (User::$records as $id => $record) {
                if (!empty($record['is_deleted'])) {
                    continue;
                }
                foreach ($query['WHERE']['OR'] as $criteria) {
                    if ($criteria['firstname'] === $record['firstname'] && $criteria['realname'] === $record['realname']) {
                        yield ['id' => $id];
                        break;
                    }
                }
            }
        }
    }

    class CommonDropdown extends CommonDBTM
    {
        public static function getNameField(): string
        {
            return 'name';
        }
    }

    class CommonTreeDropdown extends CommonDropdown {}

    final class TestLocation extends CommonTreeDropdown
    {
        public static array $records = [];

        public static function getTypeName(int $count = 0): string
        {
            return 'Location';
        }

        public function isEntityAssign(): bool
        {
            return true;
        }

        public function canUpdate(): bool
        {
            return false;
        }
    }

    function getEntitiesRestrictCriteria(string $table, string $field, int $entity, bool $recursive): array
    {
        return ['entities_id' => $entity];
    }

    class Budget extends CommonDBTM
    {
        public static array $records = [];

        public static function getNameField(): string
        {
            return 'name';
        }

        public static function getTypeName(int $count = 0): string
        {
            return 'Budget';
        }

        public function isEntityAssign(): bool
        {
            return true;
        }
    }

    final class Supplier extends Budget
    {
        public static array $records = [];
    }

    final class Entity extends CommonTreeDropdown
    {
        public static array $records = [];
    }

    final class Session
    {
        public static function haveRight(string $right, int $value): bool
        {
            return true;
        }
    }

    final class Infocom extends CommonDBTM
    {
        public static array $records = [];
        public static string $rightname = 'infocom';

        public function getFromDBforDevice(string $itemtype, int $items_id): bool
        {
            foreach (self::$records as $id => $record) {
                if ($record['itemtype'] === $itemtype && $record['items_id'] === $items_id) {
                    $this->fields = ['id' => $id] + $record;
                    return true;
                }
            }
            return false;
        }

        public static function getTypeName(int $count = 0): string
        {
            return 'Financial and administrative information';
        }

        public function can(int $id, int $right, array $input = []): bool
        {
            return true;
        }

        public function canUpdateItem(): bool
        {
            return true;
        }

        public function add(array $input): int
        {
            $id = count(self::$records) + 1;
            self::$records[$id] = $input;
            $this->fields = ['id' => $id] + $input;
            return $id;
        }

        public function update(array $input): bool
        {
            $id = (int) $input['id'];
            unset($input['id']);
            self::$records[$id] = array_merge(self::$records[$id], $input);
            $this->fields = ['id' => $id] + self::$records[$id];
            return true;
        }
    }

    final class TestAsset extends CommonDBTM
    {
        public static array $records = [];

        public static function getTypeName(int $count = 0): string
        {
            return 'Test asset';
        }
    }

    final class TestManufacturer extends CommonDBTM
    {
        public static array $records = [1 => ['name' => 'Acme']];

        public static function getTypeName(int $count = 0): string
        {
            return 'Manufacturer';
        }
    }

    require_once dirname(__DIR__) . '/src/CsvImportService.php';
}

namespace {
    use GlpiPlugin\Glpiinject\CsvImportService;

    $service = new CsvImportService();
    $model = [
        'csv_file' => 'sample.csv',
        'delimiter' => ',',
        'has_header' => 1,
        'mappings' => [
            0 => ['itemtype' => TestAsset::class, 'field' => 'name', 'linkfield' => 'name'],
            1 => ['itemtype' => TestAsset::class, 'field' => 'custom_inventory_code', 'linkfield' => 'custom_inventory_code'],
            2 => ['itemtype' => TestAsset::class, 'field' => 'comment', 'linkfield' => 'comment'],
        ],
        'match_field' => 'name',
        'entities_id' => 0,
        'behavior_add' => 1,
        'behavior_update' => 1,
        'file_encoding' => 'UTF-8',
    ];
    $target = [
        'itemtype' => TestAsset::class,
        'fields' => [
            ['field' => 'name'],
            ['field' => 'custom_inventory_code'],
            ['field' => 'comment'],
        ],
        'mapping_options' => [
            ['itemtype' => TestAsset::class, 'field' => 'name', 'linkfield' => 'name', 'label' => 'Name', 'is_relation' => false],
            ['itemtype' => TestAsset::class, 'field' => 'custom_inventory_code', 'linkfield' => 'custom_inventory_code', 'label' => 'Inventory code', 'is_relation' => false],
            ['itemtype' => TestAsset::class, 'field' => 'comment', 'linkfield' => 'comment', 'label' => 'Comment', 'is_relation' => false],
            ['itemtype' => TestManufacturer::class, 'field' => 'name', 'linkfield' => 'manufacturers_id', 'label' => 'Manufacturer', 'is_relation' => true],
        ],
    ];

    $preview = $service->preview($model, $target);
    assert($preview['headers'] === ['Name', 'Inventory code', 'Comment']);
    assert($preview['records'][0]['values'][1] === 'CTC-001');

    $first_import = $service->import($model, $target);
    assert($first_import['created'] === 2);
    assert($first_import['updated'] === 0);
    assert($first_import['failed'] === 0);
    assert(TestAsset::$records[1]['custom_inventory_code'] === 'CTC-001');

    $model['csv_file'] = 'sample.csv';
    $second_import = $service->import($model, $target);
    assert($second_import['created'] === 0);
    assert($second_import['updated'] === 2);
    assert($second_import['failed'] === 0);

    $headerless_path = __DIR__ . '/fixtures/glpiinject/headerless.csv';
    file_put_contents($headerless_path, "Laptop C,CTC-003,Third row\n");
    $model['csv_file'] = 'headerless.csv';
    $model['has_header'] = 0;
    $headerless_preview = $service->preview($model, $target);
    assert($headerless_preview['headers'] === ['Column 1', 'Column 2', 'Column 3']);
    assert($headerless_preview['records'][0]['values'][1] === 'CTC-003');
    unlink($headerless_path);

    $automatic_encoding_path = __DIR__ . '/fixtures/glpiinject/automatic-encoding.csv';
    file_put_contents($automatic_encoding_path, "Caf\xE9 laptop,CTC-004,Legacy encoded\n");
    $automatic_encoding_model = $model;
    $automatic_encoding_model['csv_file'] = 'automatic-encoding.csv';
    $automatic_encoding_model['has_header'] = 0;
    $automatic_encoding_model['file_encoding'] = 'Automatic';
    $automatic_encoding_preview = $service->preview($automatic_encoding_model, $target);
    assert($automatic_encoding_preview['records'][0]['values'][0] === "Caf\xC3\xA9 laptop");
    unlink($automatic_encoding_path);

    $encoding_path = __DIR__ . '/fixtures/glpiinject/encoding-cases.csv';
    $encoding_model = $model;
    $encoding_model['csv_file'] = 'encoding-cases.csv';
    $encoding_model['has_header'] = 1;
    $encoding_model['file_encoding'] = 'Automatic';
    $utf8_csv = "Name,Inventory code,Comment\nCaf\xC3\xA9 laptop,ENC-1,First\nSecond laptop,ENC-2,Second\n";
    foreach (['UTF-8', 'UTF-16LE', 'UTF-16BE'] as $source_encoding) {
        $bom = match ($source_encoding) {
            'UTF-16LE' => "\xFF\xFE",
            'UTF-16BE' => "\xFE\xFF",
            default => "\xEF\xBB\xBF",
        };
        foreach (['', $bom] as $prefix) {
            file_put_contents($encoding_path, $prefix . iconv('UTF-8', $source_encoding, $utf8_csv));
            $encoding_preview = $service->preview($encoding_model, $target);
            assert($encoding_preview['headers'][0] === 'Name');
            assert($encoding_preview['records'][0]['values'][0] === "Caf\xC3\xA9 laptop");
            $encoding_first = $service->readBatch($encoding_model, 0, 0, 1);
            assert($encoding_first['rows'][0][1][0] === "Caf\xC3\xA9 laptop");
            $encoding_second = $service->readBatch($encoding_model, $encoding_first['byte_offset'], $encoding_first['physical_line'], 1);
            assert($encoding_second['rows'][0][1][0] === 'Second laptop');
            $encoding_end = $service->readBatch($encoding_model, $encoding_second['byte_offset'], $encoding_second['physical_line'], 1);
            assert($encoding_end['done'] === true);
        }
    }
    file_put_contents($encoding_path, "Name,Inventory code,Comment\n" . str_repeat('A', 70000) . ",ENC-1,First\nCaf\xE9 laptop,ENC-2,Second\n");
    $legacy_first = $service->readBatch($encoding_model, 0, 0, 1);
    $legacy_second = $service->readBatch($encoding_model, $legacy_first['byte_offset'], $legacy_first['physical_line'], 1);
    assert($legacy_second['rows'][0][1][0] === "Caf\xC3\xA9 laptop");
    file_put_contents($encoding_path, "Name,Inventory code,Comment\nCaf\xE9,ENC-1,\x93Quoted\x94\n");
    $legacy_preview = $service->preview($encoding_model, $target);
    $legacy_batch = $service->readBatch($encoding_model, 0, 0, 10);
    assert($legacy_preview['records'][0]['values'][0] === "Caf\xC3\xA9");
    assert($legacy_batch['rows'][0][1][2] === "\xE2\x80\x9CQuoted\xE2\x80\x9D");
    unlink($encoding_path);

    $cursor_path = __DIR__ . '/fixtures/glpiinject/cursor.csv';
    file_put_contents($cursor_path, "Name,Code\nFirst,1\nSecond,2\n");
    $cursor_model = $model;
    $cursor_model['csv_file'] = 'cursor.csv';
    $cursor_model['has_header'] = 1;
    $cursor_first = $service->readBatch($cursor_model, 0, 0, 1);
    assert($cursor_first['rows'][0][1] === ['First', '1']);
    assert($cursor_first['done'] === false);
    $cursor_second = $service->readBatch($cursor_model, $cursor_first['byte_offset'], $cursor_first['physical_line'], 1);
    assert($cursor_second['rows'][0][1] === ['Second', '2']);
    assert($cursor_second['byte_offset'] > $cursor_first['byte_offset']);
    $cursor_end = $service->readBatch($cursor_model, $cursor_second['byte_offset'], $cursor_second['physical_line'], 1);
    assert($cursor_end['rows'] === []);
    assert($cursor_end['done'] === true);
    unlink($cursor_path);

    $related_path = __DIR__ . '/fixtures/glpiinject/related.csv';
    file_put_contents($related_path, "Laptop D,Acme,Fourth row\nLaptop E,,Fifth row\n");
    $related_model = $model;
    $related_model['csv_file'] = 'related.csv';
    $related_model['has_header'] = 0;
    $related_model['mappings'] = [
        0 => ['itemtype' => TestAsset::class, 'field' => 'name', 'linkfield' => 'name'],
        1 => ['itemtype' => TestManufacturer::class, 'field' => 'name', 'linkfield' => 'manufacturers_id', 'is_mandatory' => true],
        2 => ['itemtype' => TestAsset::class, 'field' => 'comment', 'linkfield' => 'comment'],
    ];
    $related_target = $target;
    $related_target['mapping_options'][] = [
        'itemtype' => TestManufacturer::class,
        'field' => 'name',
        'linkfield' => 'manufacturers_id',
        'label' => 'Manufacturer',
        'is_relation' => true,
    ];
    $related_preview = $service->preview($related_model, $related_target);
    assert($related_preview['records'][0]['valid'] === true);
    assert($related_preview['records'][1]['valid'] === false);
    $related_import = $service->import($related_model, $related_target);
    assert($related_import['created'] === 1);
    assert($related_import['failed'] === 1);
    assert(TestAsset::$records[3]['manufacturers_id'] === 1);
    unlink($related_path);

    $formats_path = __DIR__ . '/fixtures/glpiinject/formats.csv';
    file_put_contents($formats_path, "Laptop F,31-12-2025,\"1 234,56\",YES\n");
    $formats_model = $model;
    $formats_model['csv_file'] = 'formats.csv';
    $formats_model['has_header'] = 0;
    $formats_model['delimiter'] = ',';
    $formats_model['date_format'] = 'dd-mm-yyyy';
    $formats_model['float_format'] = '1 234,56';
    $formats_model['mappings'] = [
        0 => ['itemtype' => TestAsset::class, 'field' => 'name', 'linkfield' => 'name'],
        1 => ['itemtype' => TestAsset::class, 'field' => 'purchase_date', 'linkfield' => 'purchase_date'],
        2 => ['itemtype' => TestAsset::class, 'field' => 'cost', 'linkfield' => 'cost'],
        3 => ['itemtype' => TestAsset::class, 'field' => 'is_active', 'linkfield' => 'is_active'],
    ];
    $formats_target = $target;
    $formats_target['mapping_options'] = [
        ['itemtype' => TestAsset::class, 'field' => 'name', 'linkfield' => 'name', 'label' => 'Name', 'datatype' => 'string', 'is_relation' => false],
        ['itemtype' => TestAsset::class, 'field' => 'purchase_date', 'linkfield' => 'purchase_date', 'label' => 'Purchase date', 'datatype' => 'date', 'is_relation' => false],
        ['itemtype' => TestAsset::class, 'field' => 'cost', 'linkfield' => 'cost', 'label' => 'Cost', 'datatype' => 'number', 'is_relation' => false],
        ['itemtype' => TestAsset::class, 'field' => 'is_active', 'linkfield' => 'is_active', 'label' => 'Active', 'datatype' => 'bool', 'is_relation' => false],
    ];
    $formats_import = $service->import($formats_model, $formats_target);
    assert($formats_import['created'] === 1);
    assert($formats_import['failed'] === 0);
    assert(TestAsset::$records[4]['purchase_date'] === '2025-12-31');
    assert((float) TestAsset::$records[4]['cost'] === 1234.56);
    assert(TestAsset::$records[4]['is_active'] === 1);
    unlink($formats_path);

    $infocom_path = __DIR__ . '/fixtures/glpiinject/infocom.csv';
    file_put_contents($infocom_path, "Laptop G,2026-01-15,1250.50\n");
    $infocom_model = $model;
    $infocom_model['csv_file'] = 'infocom.csv';
    $infocom_model['has_header'] = 0;
    $infocom_model['mappings'] = [
        0 => ['itemtype' => TestAsset::class, 'field' => 'name', 'linkfield' => 'name'],
        1 => ['itemtype' => Infocom::class, 'field' => 'buy_date', 'linkfield' => '__infocom'],
        2 => ['itemtype' => Infocom::class, 'field' => 'value', 'linkfield' => '__infocom'],
    ];
    $infocom_target = $target;
    $infocom_target['mapping_options'][] = [
        'itemtype' => Infocom::class,
        'field' => 'buy_date',
        'linkfield' => '__infocom',
        'label' => 'Financial information -> Purchase date',
        'datatype' => 'date',
        'is_relation' => false,
        'is_infocom' => true,
    ];
    $infocom_target['mapping_options'][] = [
        'itemtype' => Infocom::class,
        'field' => 'value',
        'linkfield' => '__infocom',
        'label' => 'Financial information -> Value',
        'datatype' => 'decimal',
        'is_relation' => false,
        'is_infocom' => true,
    ];
    $infocom_import = $service->import($infocom_model, $infocom_target);
    assert($infocom_import['created'] === 1);
    assert($infocom_import['failed'] === 0);
    assert(Infocom::$records[1]['itemtype'] === TestAsset::class);
    assert(Infocom::$records[1]['items_id'] === 5);
    assert(Infocom::$records[1]['buy_date'] === '2026-01-15');
    assert((float) Infocom::$records[1]['value'] === 1250.50);

    file_put_contents($infocom_path, "Laptop G,2026-02-15,1500.00\n");
    $infocom_model['behavior_add'] = 0;
    $infocom_model['behavior_update'] = 1;
    $infocom_model['match_field'] = 'name';
    $update_infocom = $service->import($infocom_model, $infocom_target);
    assert($update_infocom['updated'] === 1);
    assert($update_infocom['failed'] === 0);
    assert(Infocom::$records[1]['buy_date'] === '2026-02-15');
    assert((float) Infocom::$records[1]['value'] === 1500.00);
    unlink($infocom_path);

    TestAsset::$records = [];
    $batch_model = $model;
    $batch_model['csv_file'] = 'sample.csv';
    $batch_model['has_header'] = 1;
    $batch_one = $service->importBatch($batch_model, $target, 0, 1);
    assert($batch_one['processed'] === 1);
    assert($batch_one['created'] === 1);
    assert(TestAsset::$records[1]['name'] === 'Laptop A');
    $batch_two = $service->importBatch($batch_model, $target, 1, 1);
    assert($batch_two['processed'] === 1);
    assert($batch_two['created'] === 1);
    assert(TestAsset::$records[2]['name'] === 'Laptop B');

    TestAsset::$records = [];
    $staged_batch = $service->processStagedBatch($batch_model, $target, [
        ['line' => 2, 'row' => ['Laptop A', 'CTC-001', 'First row']],
        ['line' => 3, 'row' => ['Laptop B', 'CTC-002', 'Second row']],
    ]);
    assert($staged_batch['processed'] === 2);
    assert($staged_batch['created'] === 2);
    assert($staged_batch['rows'][1]['line'] === 3);
    assert(TestAsset::$records[2]['custom_inventory_code'] === 'CTC-002');

    $stream_path = __DIR__ . '/fixtures/glpiinject/streamed-import.csv';
    file_put_contents($stream_path, "Name,Inventory code,Comment\nStream A,STR-001,First\nStream B,STR-002,Second\n");
    TestAsset::$records = [];
    $stream_model = $batch_model;
    $stream_model['csv_file'] = 'streamed-import.csv';
    $stream_model['has_header'] = 1;
    $cursor_offset = 0;
    $cursor_line = 0;
    for ($expected = 1; $expected <= 2; $expected++) {
        $stream_batch = $service->readBatch($stream_model, $cursor_offset, $cursor_line, 1);
        assert(count($stream_batch['rows']) === 1);
        $stream_result = $service->processStagedBatch($stream_model, $target, array_map(
            static fn(array $row) => ['line' => $row[0], 'row' => $row[1]],
            $stream_batch['rows']
        ));
        assert($stream_result['created'] === 1);
        $cursor_offset = $stream_batch['byte_offset'];
        $cursor_line = $stream_batch['physical_line'];
    }
    $stream_end = $service->readBatch($stream_model, $cursor_offset, $cursor_line, 1);
    assert($stream_end['rows'] === []);
    assert($stream_end['done'] === true);
    assert(TestAsset::$records[1]['name'] === 'Stream A');
    assert(TestAsset::$records[2]['custom_inventory_code'] === 'STR-002');
    unlink($stream_path);

    $name_only_path = __DIR__ . '/fixtures/glpiinject/name-only-import.csv';
    file_put_contents($name_only_path, "Asset name\nName only asset\n");
    TestAsset::$records = [];
    $name_only_model = $batch_model;
    $name_only_model['csv_file'] = 'name-only-import.csv';
    $name_only_model['mappings'] = [
        0 => ['itemtype' => TestAsset::class, 'field' => 'name', 'linkfield' => 'name'],
    ];
    $name_only_target = $target;
    $name_only_target['mapping_options'] = [
        ['itemtype' => TestAsset::class, 'field' => 'name', 'linkfield' => 'name', 'label' => 'Name', 'datatype' => 'string', 'is_relation' => false],
    ];
    $name_only_cursor = $service->readBatch($name_only_model, 0, 0, 10);
    $name_only_import = $service->processStagedBatch($name_only_model, $name_only_target, array_map(
        static fn(array $row) => ['line' => $row[0], 'row' => $row[1]],
        $name_only_cursor['rows']
    ));
    assert($name_only_import['created'] === 1);
    assert($name_only_import['failed'] === 0);
    assert(TestAsset::$records[1]['name'] === 'Name only asset');
    unlink($name_only_path);

    $normalization_model = $name_only_model;
    $normalization_model['can_add_dropdown'] = 1;
    $normalization_model['date_format'] = 'yyyy-mm-dd';
    $normalization_model['mappings'][1] = ['itemtype' => TestLocation::class, 'field' => 'name', 'linkfield' => 'locations_id'];
    $normalization_model['mappings'][2] = ['itemtype' => TestAsset::class, 'field' => 'date_mod', 'linkfield' => 'date_mod'];
    $normalization_target = $name_only_target;
    $normalization_target['mapping_options'][] = [
        'itemtype' => TestLocation::class, 'field' => 'name', 'linkfield' => 'locations_id',
        'label' => 'Location -> Location', 'is_relation' => true,
    ];
    $normalization_target['mapping_options'][] = [
        'itemtype' => TestAsset::class, 'field' => 'date_mod', 'linkfield' => 'date_mod',
        'label' => 'Modified', 'datatype' => 'datetime', 'is_relation' => false,
    ];
    $normalization_path = __DIR__ . '/fixtures/glpiinject/normalization.csv';
    file_put_contents($normalization_path, "Name,Location,Modified\nS0010,CT 154 Classroom PCP Lab,11/14/2024 14:37\n");
    $normalization_model['csv_file'] = 'normalization.csv';
    $normalization_preview = $service->preview($normalization_model, $normalization_target);
    assert($normalization_preview['records'][0]['valid'] === true);
    assert(TestLocation::$records === []);
    unlink($normalization_path);

    TestAsset::$records = [];
    $date_cases = [
        ['11/14/2024 14:37', '2024-11-14 14:37:00'],
        ['1/31/2025 10:22', '2025-01-31 10:22:00'],
        ['3/20/2025 9:14', '2025-03-20 09:14:00'],
        ['9/12/2025 15:14', '2025-09-12 15:14:00'],
        ['5/8/2026 16:56', '2026-05-08 16:56:00'],
        ['2026-05-08T16:56:12', '2026-05-08 16:56:12'],
    ];
    foreach ($date_cases as $index => [$input_date, $expected_date]) {
        $normalized_import = $service->processStagedBatch($normalization_model, $normalization_target, [
            ['line' => $index + 2, 'row' => ['Normalized ' . $index, 'CT 154 Classroom PCP Lab', $input_date]],
        ]);
        assert($normalized_import['created'] === 1);
        assert($normalized_import['failed'] === 0);
        assert(TestAsset::$records[$index + 1]['date_mod'] === $expected_date);
        assert(TestAsset::$records[$index + 1]['locations_id'] === 1);
    }
    assert(count(TestLocation::$records) === 1);

    $normalization_model['date_format'] = 'dd-mm-yyyy';
    $preferred_date = $service->processStagedBatch($normalization_model, $normalization_target, [
        ['line' => 10, 'row' => ['Preferred date', 'CT 154 Classroom PCP Lab', '5/8/2026 16:56']],
    ]);
    assert($preferred_date['created'] === 1);
    assert(TestAsset::$records[7]['date_mod'] === '2026-08-05 16:56:00');
    $invalid_date = $service->processStagedBatch($normalization_model, $normalization_target, [
        ['line' => 11, 'row' => ['Invalid date', 'CT 154 Classroom PCP Lab', '2/30/2026 16:56']],
    ]);
    assert($invalid_date['failed'] === 1);

    $normalization_model['can_add_dropdown'] = 0;
    $missing_location = $service->processStagedBatch($normalization_model, $normalization_target, [
        ['line' => 12, 'row' => ['Missing location', 'Temporary Location', '2026-05-08']],
    ]);
    assert($missing_location['failed'] === 1);
    assert(count(TestLocation::$records) === 1);

    $normalization_model['can_add_dropdown'] = 1;
    $normalization_model['mappings'][1]['itemtype'] = Entity::class;
    $entity_target = $normalization_target;
    $entity_target['mapping_options'][1]['itemtype'] = Entity::class;
    $blocked_entity = $service->processStagedBatch($normalization_model, $entity_target, [
        ['line' => 13, 'row' => ['Blocked entity', 'New Entity', '2026-05-08']],
    ]);
    assert($blocked_entity['failed'] === 1);
    assert(Entity::$records === []);

    $normalization_model['mappings'][1]['itemtype'] = TestLocation::class;
    $normalization_model['behavior_add'] = 0;
    $normalization_model['behavior_update'] = 1;
    $normalization_model['match_field'] = 'name';
    $update_only = $service->processStagedBatch($normalization_model, $normalization_target, [
        ['line' => 14, 'row' => ['Normalized 0', 'Temporary Location', '2026-05-08']],
        ['line' => 15, 'row' => ['New asset disabled', 'Temporary Location', '2026-05-08']],
    ]);
    assert($update_only['updated'] === 1);
    assert($update_only['created'] === 0);
    assert($update_only['failed'] === 1);
    assert(count(TestLocation::$records) === 2);
    $normalization_model['behavior_add'] = 1;
    $normalization_model['behavior_update'] = 0;
    $create_only = $service->processStagedBatch($normalization_model, $normalization_target, [
        ['line' => 16, 'row' => ['Normalized 0', 'Temporary Location', '2026-05-08']],
        ['line' => 17, 'row' => ['New asset enabled', 'Temporary Location', '2026-05-08']],
    ]);
    assert($create_only['created'] === 1);
    assert($create_only['updated'] === 0);
    assert($create_only['failed'] === 1);

    foreach (['missing', 'null', 'empty'] as $match_setting) {
        $default_match_model = $normalization_model;
        $default_match_model['behavior_update'] = 1;
        unset($default_match_model['match_field']);
        if ($match_setting !== 'missing') {
            $default_match_model['match_field'] = $match_setting === 'null' ? null : '';
        }
        $record_count = count(TestAsset::$records);
        $default_match = $service->processStagedBatch($default_match_model, $normalization_target, [
            ['line' => 18, 'row' => ['Normalized 0', 'Temporary Location', '2026-05-08']],
        ]);
        assert($default_match['updated'] === 1);
        assert($default_match['created'] === 0);
        assert($default_match['failed'] === 0);
        assert(count(TestAsset::$records) === $record_count);
    }

    TestAsset::$allow_item_write = false;
    $denied_model = $normalization_model;
    $denied_model['behavior_update'] = 1;
    $asset_snapshot = TestAsset::$records;
    $denied_writes = $service->processStagedBatch($denied_model, $normalization_target, [
        ['line' => 19, 'row' => ['Normalized 0', 'Temporary Location', '2026-05-08']],
        ['line' => 20, 'row' => ['Denied new asset', 'Temporary Location', '2026-05-08']],
    ]);
    assert($denied_writes['failed'] === 2);
    assert(TestAsset::$records === $asset_snapshot);
    TestAsset::$allow_item_write = true;

    TestLocation::$records = [1 => ['name' => 'Shared label', 'entities_id' => 99]];
    $scoped_model = $denied_model;
    $scoped_model['entities_id'] = 0;
    $scoped_model['can_add_dropdown'] = 0;
    $foreign_relation = $service->processStagedBatch($scoped_model, $normalization_target, [
        ['line' => 21, 'row' => ['Scoped asset', 'Shared label', '2026-05-08']],
    ]);
    assert($foreign_relation['failed'] === 1);
    assert(TestAsset::$records === $asset_snapshot);
    $scoped_model['can_add_dropdown'] = 1;
    $local_relation = $service->processStagedBatch($scoped_model, $normalization_target, [
        ['line' => 22, 'row' => ['Scoped asset', 'Shared label', '2026-05-08']],
    ]);
    assert($local_relation['created'] === 1);
    assert(TestLocation::$records[2]['entities_id'] === 0);
    assert(TestAsset::$records[count(TestAsset::$records)]['locations_id'] === 2);
    TestLocation::$allow_item_read = false;
    $denied_relation = $service->processStagedBatch($scoped_model, $normalization_target, [
        ['line' => 23, 'row' => ['Denied relation', 'Shared label', '2026-05-08']],
    ]);
    assert($denied_relation['failed'] === 1);
    TestLocation::$allow_item_read = true;
    TestLocation::$allow_item_write = false;
    $denied_dropdown = $service->processStagedBatch($scoped_model, $normalization_target, [
        ['line' => 24, 'row' => ['Denied dropdown', 'Missing dropdown', '2026-05-08']],
    ]);
    assert($denied_dropdown['failed'] === 1);
    assert(count(TestLocation::$records) === 2);
    TestLocation::$allow_item_write = true;

    require_once dirname(__DIR__) . '/src/CsvExport.php';
    foreach (['=1+1', '+SUM(1,2)', '-1+2', '@SUM(1)', "\t=1", "\r\n=1", "\0=1"] as $formula) {
        assert(\GlpiPlugin\Glpiinject\CsvExport::sanitizeCell($formula) === "'" . $formula);
    }
    foreach (['Normal text', '123', '["=1+1"]', 'A=B', '<script>alert(1)</script>'] as $plain_value) {
        assert(\GlpiPlugin\Glpiinject\CsvExport::sanitizeCell($plain_value) === $plain_value);
    }

    $financial_model = $model;
    $financial_model['can_add_dropdown'] = 1;
    $financial_model['mappings'] = [
        0 => ['itemtype' => TestAsset::class, 'field' => 'name', 'linkfield' => 'name'],
        1 => ['itemtype' => Budget::class, 'field' => 'name', 'linkfield' => 'budgets_id'],
        2 => ['itemtype' => Supplier::class, 'field' => 'name', 'linkfield' => 'suppliers_id'],
    ];
    $financial_target = $target;
    foreach ([Budget::class => 'budgets_id', Supplier::class => 'suppliers_id'] as $reference_type => $reference_field) {
        $financial_target['mapping_options'][] = [
            'itemtype' => $reference_type, 'field' => 'name', 'linkfield' => $reference_field,
            'label' => 'Financial reference', 'is_relation' => true, 'is_infocom' => true,
        ];
    }
    $financial_rows = [['line' => 2, 'row' => ['Financial asset', 'Equipment 2026', 'Vendor A']]];
    $financial_created = $service->processStagedBatch($financial_model, $financial_target, $financial_rows);
    assert($financial_created['created'] === 1);
    assert($financial_created['failed'] === 0);
    $financial_asset_id = $financial_created['rows'][0]['items_id'];
    $financial_record = new Infocom();
    assert($financial_record->getFromDBforDevice(TestAsset::class, $financial_asset_id));
    assert($financial_record->fields['budgets_id'] === 1);
    assert($financial_record->fields['suppliers_id'] === 1);
    assert(!isset(TestAsset::$records[$financial_asset_id]['budgets_id']));
    assert(!isset(TestAsset::$records[$financial_asset_id]['suppliers_id']));
    $financial_model['can_add_dropdown'] = 0;
    $financial_updated = $service->processStagedBatch($financial_model, $financial_target, $financial_rows);
    assert($financial_updated['updated'] === 1);
    assert(count(Budget::$records) === 1);
    assert(count(Supplier::$records) === 1);
    $financial_missing = $service->processStagedBatch($financial_model, $financial_target, [
        ['line' => 3, 'row' => ['Financial missing', 'Missing budget', 'Missing supplier']],
    ]);
    assert($financial_missing['failed'] === 1);
    assert(count(Budget::$records) === 1);
    $missing_supplier = $service->processStagedBatch($financial_model, $financial_target, [
        ['line' => 3, 'row' => ['Missing supplier asset', 'Equipment 2026', 'Missing supplier']],
    ]);
    assert($missing_supplier['failed'] === 1);
    assert(count(Supplier::$records) === 1);
    $financial_model['can_add_dropdown'] = 1;
    $financial_replacement = $service->processStagedBatch($financial_model, $financial_target, [
        ['line' => 4, 'row' => ['Financial asset', 'Equipment 2027', 'Vendor B']],
    ]);
    assert($financial_replacement['updated'] === 1);
    assert($financial_record->getFromDBforDevice(TestAsset::class, $financial_asset_id));
    assert($financial_record->fields['budgets_id'] === 2);
    assert($financial_record->fields['suppliers_id'] === 2);
    $financial_path = __DIR__ . '/fixtures/glpiinject/financial-preview.csv';
    file_put_contents($financial_path, "Name,Budget,Supplier\nPreview financial,Preview budget,Preview vendor\n");
    $financial_model['csv_file'] = 'financial-preview.csv';
    $financial_model['has_header'] = 1;
    $financial_preview = $service->preview($financial_model, $financial_target);
    assert($financial_preview['records'][0]['valid']);
    assert(count(Budget::$records) === 2);
    assert(count(Supplier::$records) === 2);
    unlink($financial_path);
    Budget::$records[3] = ['name' => 'Foreign budget', 'entities_id' => 99];
    $financial_model['can_add_dropdown'] = 0;
    $foreign_budget = $service->processStagedBatch($financial_model, $financial_target, [
        ['line' => 5, 'row' => ['Foreign budget asset', 'Foreign budget', 'Vendor A']],
    ]);
    assert($foreign_budget['failed'] === 1);
    Budget::$allow_item_read = false;
    $unreadable_budget = $service->processStagedBatch($financial_model, $financial_target, $financial_rows);
    assert($unreadable_budget['failed'] === 1);
    Budget::$allow_item_read = true;
    Budget::$allow_item_write = false;
    $financial_model['can_add_dropdown'] = 1;
    $denied_budget = $service->processStagedBatch($financial_model, $financial_target, [
        ['line' => 6, 'row' => ['Denied budget asset', 'Denied new budget', 'Vendor A']],
    ]);
    assert($denied_budget['failed'] === 1);
    assert(count(Budget::$records) === 3);
    Budget::$allow_item_write = true;

    $DB = new TestUserDatabase();
    User::$records = [
        1 => ['name' => 'afolden', 'firstname' => 'Alicia', 'realname' => 'Folden'],
        2 => ['name' => 'maryjane', 'firstname' => 'Mary Jane', 'realname' => 'Van Dyke'],
        3 => ['name' => 'hidden', 'firstname' => 'Hidden', 'realname' => 'User', 'readable' => false],
        4 => ['name' => 'deleted', 'firstname' => 'Deleted', 'realname' => 'User', 'is_deleted' => 1],
    ];
    $user_model = $model;
    $user_model['mappings'] = [
        0 => ['itemtype' => TestAsset::class, 'field' => 'name', 'linkfield' => 'name'],
        1 => ['itemtype' => User::class, 'field' => 'name', 'linkfield' => 'users_id'],
    ];
    $user_model['can_add_dropdown'] = 1;
    $user_target = $target;
    $user_target['mapping_options'][] = [
        'itemtype' => User::class, 'field' => 'name', 'linkfield' => 'users_id',
        'label' => 'User -> Username', 'is_relation' => true,
    ];
    $username_only = $service->processStagedBatch($user_model, $user_target, [
        ['line' => 2, 'row' => ['Username asset', 'afolden']],
        ['line' => 3, 'row' => ['Disabled fallback asset', 'Alicia Folden']],
    ]);
    assert($username_only['created'] === 1);
    assert($username_only['failed'] === 1);
    assert($DB->requests === 0);
    $user_model['user_fullname_fallback'] = 1;
    $fullnames = $service->processStagedBatch($user_model, $user_target, [
        ['line' => 4, 'row' => ['Full name asset', 'Alicia Folden']],
        ['line' => 5, 'row' => ['Compound name asset', 'Mary   Jane Van Dyke']],
        ['line' => 6, 'row' => ['Hidden user asset', 'Hidden User']],
        ['line' => 7, 'row' => ['Deleted user asset', 'Deleted User']],
        ['line' => 8, 'row' => ['Missing user asset', 'Missing Person']],
    ]);
    assert($fullnames['created'] === 2);
    assert($fullnames['failed'] === 3);
    assert(TestAsset::$records[$fullnames['rows'][0]['items_id']]['users_id'] === 1);
    assert(TestAsset::$records[$fullnames['rows'][1]['items_id']]['users_id'] === 2);
    $user_path = __DIR__ . '/fixtures/glpiinject/user-preview.csv';
    file_put_contents($user_path, "Name,Owner\nPreview user,Alicia Folden\n");
    $user_model['csv_file'] = 'user-preview.csv';
    $user_model['has_header'] = 1;
    assert($service->preview($user_model, $user_target)['records'][0]['valid']);
    User::$records[5] = ['name' => 'afolden2', 'firstname' => 'Alicia', 'realname' => 'Folden'];
    $ambiguous_preview = $service->preview($user_model, $user_target);
    assert(!$ambiguous_preview['records'][0]['valid']);
    assert(str_contains($ambiguous_preview['records'][0]['errors'][0], 'Multiple accessible users'));
    $ambiguous_import = $service->processStagedBatch($user_model, $user_target, [
        ['line' => 9, 'row' => ['Ambiguous user asset', 'Alicia Folden']],
    ]);
    assert($ambiguous_import['failed'] === 1);
    User::$records[6] = ['name' => 'Alicia Folden', 'firstname' => 'Other', 'realname' => 'Person'];
    $queries_before_username = $DB->requests;
    $username_priority = $service->processStagedBatch($user_model, $user_target, [
        ['line' => 10, 'row' => ['Username priority asset', 'Alicia Folden']],
    ]);
    assert($username_priority['created'] === 1);
    assert(TestAsset::$records[$username_priority['rows'][0]['items_id']]['users_id'] === 6);
    assert($DB->requests === $queries_before_username);
    assert(count(User::$records) === 6);
    unlink($user_path);

    TestLocation::$records = [
        1 => ['name' => 'Reception', 'completename' => 'Campus A > Reception', 'entities_id' => 0],
        2 => ['name' => 'Reception', 'completename' => 'Campus B > Reception', 'entities_id' => 0],
    ];
    $duplicate_model = $normalization_model;
    $duplicate_model['behavior_update'] = 1;
    $duplicate_model['can_add_dropdown'] = 1;
    $duplicate_model['has_header'] = 1;
    $duplicate_path = __DIR__ . '/fixtures/glpiinject/duplicate-location.csv';
    $duplicate_model['csv_file'] = 'duplicate-location.csv';
    file_put_contents($duplicate_path, "Name,Location,Date\n" . str_repeat("Preview row,Campus A > Reception,2026-05-08\n", 10) . "Ambiguous row,Reception,2026-05-08\n");
    $short_preview = $service->preview($duplicate_model, $normalization_target, 10);
    assert(count(array_filter($short_preview['records'], static fn(array $record) => !$record['valid'])) === 0);
    $long_preview = $service->preview($duplicate_model, $normalization_target, 50);
    assert(!$long_preview['records'][10]['valid']);
    assert(str_contains($long_preview['records'][10]['errors'][0], 'Multiple'));
    $duplicate_import = $service->processStagedBatch($duplicate_model, $normalization_target, [
        ['line' => 12, 'row' => ['Ambiguous row', 'Reception', '2026-05-08']],
        ['line' => 13, 'row' => ['Full path row', 'Campus B > Reception', '2026-05-08']],
    ]);
    assert($duplicate_import['failed'] === 1);
    assert($duplicate_import['created'] === 1);
    assert(TestAsset::$records[$duplicate_import['rows'][1]['items_id']]['locations_id'] === 2);
    assert(count(TestLocation::$records) === 2);
    TestLocation::$records[3] = ['name' => 'Other leaf', 'completename' => 'Campus B > Reception', 'entities_id' => 0];
    $duplicate_full_path = $service->processStagedBatch($duplicate_model, $normalization_target, [
        ['line' => 14, 'row' => ['Duplicate full path', 'Campus B > Reception', '2026-05-08']],
    ]);
    assert($duplicate_full_path['failed'] === 1);
    assert(count(TestLocation::$records) === 3);
    unlink($duplicate_path);

    $preview_size_path = __DIR__ . '/fixtures/glpiinject/preview-size.csv';
    file_put_contents($preview_size_path, "Name\n" . str_repeat("Preview asset\n", 10001));
    $preview_size_model = $model;
    $preview_size_model['csv_file'] = 'preview-size.csv';
    $preview_size_model['has_header'] = 1;
    $preview_size_model['mappings'] = [];
    assert(count($service->preview($preview_size_model, $target)['records']) === 10);
    foreach (CsvImportService::PREVIEW_SIZES as $result_size) {
        $sized_preview = $service->preview($preview_size_model, $target, $result_size);
        assert(count($sized_preview['records']) === $result_size);
        assert($sized_preview['records'][0]['line'] === 2);
        assert($sized_preview['records'][$result_size - 1]['line'] === $result_size + 1);
    }
    foreach ([0, -1, 12, 10001] as $invalid_size) {
        $rejected_size = false;
        try {
            $service->preview($preview_size_model, $target, $invalid_size);
        } catch (InvalidArgumentException) {
            $rejected_size = true;
        }
        assert($rejected_size);
    }
    unlink($preview_size_path);

    echo "CSV importer regressions passed, including preview sizes, record permissions, entity-scoped references, spreadsheet safety, default Name matching, encoding, date normalization, dropdown reuse, and create/update flags.\n";
}
