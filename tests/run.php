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

        public function canUpdate(): bool
        {
            return false;
        }
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

    echo "CSV importer regressions passed, including date normalization, Location creation/reuse, read-only preview, entity exclusion, and asset create/update flags.\n";
}
