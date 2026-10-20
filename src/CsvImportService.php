<?php

namespace GlpiPlugin\Glpiinject;

use CommonDBTM;
use CommonDropdown;
use CommonTreeDropdown;
use RuntimeException;
use Throwable;

final class CsvImportService
{
    private const MAX_PREVIEW_ROWS = 12;

    public function preview(array $model, array $target): array
    {
        $records = [];
        $count = 0;

        foreach ($this->readRows($model) as [$line, $row, $headers]) {
            $column_values = [];
            foreach ($this->normalizeMappings($model['mappings'], $target) as $column => $mapping) {
                $column_values[$column] = trim((string) ($row[(int) $column] ?? ''));
            }
            $errors = [];
            if ($headers !== [] && count($row) !== count($headers)) {
                $errors[] = sprintf(
                    __('Expected %1$d columns but found %2$d.', 'glpiinject'),
                    count($headers),
                    count($row)
                );
            }
            try {
                $this->mapRow($row, $model['mappings'], $target, $model, false);
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
            if ($headers === []) {
                $headers = array_map(
                    static fn ($index) => sprintf(__('Column %d', 'glpiinject'), $index + 1),
                    array_keys($row)
                );
            }
            $records[] = [
                'line' => $line,
                'values' => $column_values,
                'errors' => $errors,
                'valid' => $errors === [],
            ];
            $count++;

            if ($count >= self::MAX_PREVIEW_ROWS) {
                break;
            }
        }

        return [
            'records' => $records,
            'headers' => $this->getHeaders($model),
        ];
    }

    public function import(array $model, array $target): array
    {
        $all_rows = iterator_to_array($this->readRows($model), false);
        return $this->processRows($model, $target, $all_rows, 0, count($all_rows));
    }

    public function countRows(array $model): int
    {
        $count = 0;
        foreach ($this->readRows($model) as $_row) {
            $count++;
        }
        return $count;
    }

    public function getSourceRows(array $model): iterable
    {
        return $this->readRows($model);
    }

    public function readBatch(array $model, int $byte_offset, int $physical_line, int $batch_size): array
    {
        $filename = (string) ($model['csv_file'] ?? '');
        if ($filename === '' || basename($filename) !== $filename) {
            throw new RuntimeException(__('The model has no valid CSV file.', 'glpiinject'));
        }

        $path = PLUGIN_GLPIINJECT_UPLOAD_DIR . '/' . $filename;
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException(__('The CSV file could not be opened.', 'glpiinject'));
        }

        try {
            $encoding = $this->detectEncoding($handle, (string) ($model['file_encoding'] ?? 'Automatic'));
            if ($byte_offset === 0) {
                if ((int) $model['has_header'] === 1) {
                    fgetcsv($handle, null, $model['delimiter'], '"', '\\');
                    $physical_line++;
                    $byte_offset = ftell($handle);
                }
            } elseif (fseek($handle, $byte_offset) !== 0) {
                throw new RuntimeException(__('The CSV read position is invalid.', 'glpiinject'));
            }

            $rows = [];
            $done = false;
            while (count($rows) < $batch_size) {
                $row = fgetcsv($handle, null, $model['delimiter'], '"', '\\');
                if ($row === false) {
                    $done = true;
                    break;
                }

                $physical_line++;
                $next_offset = ftell($handle);
                if ($next_offset === false) {
                    throw new RuntimeException(__('The CSV read position could not be determined.', 'glpiinject'));
                }
                if ($byte_offset === 0 && isset($row[0])) {
                    $row[0] = preg_replace('/^\\xEF\\xBB\\xBF/', '', (string) $row[0]);
                }
                $byte_offset = $next_offset;

                $row = array_map(function ($value) use ($encoding) {
                    if ($value === null || $encoding === 'UTF-8') {
                        return $value;
                    }
                    $converted = iconv($encoding, 'UTF-8', (string) $value);
                    if ($converted === false) {
                        throw new RuntimeException(__('A CSV value could not be converted to UTF-8.', 'glpiinject'));
                    }
                    return $converted;
                }, $row);

                if ($row === [null] || count(array_filter($row, static fn($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                $rows[] = [$physical_line, $row, []];
            }

            return [
                'rows' => $rows,
                'byte_offset' => $byte_offset,
                'physical_line' => $physical_line,
                'done' => $done,
            ];
        } finally {
            fclose($handle);
        }
    }

    public function processStagedBatch(array $model, array $target, array $staged_rows): array
    {
        $rows = [];
        foreach ($staged_rows as $staged_row) {
            $rows[] = [
                (int) $staged_row['line'],
                is_array($staged_row['row']) ? $staged_row['row'] : [],
                [],
            ];
        }

        return $this->processRows($model, $target, $rows, 0, count($rows));
    }

    public function importBatch(array $model, array $target, int $offset, int $batch_size): array
    {
        $rows = [];
        $data_row_index = 0;
        foreach ($this->readRows($model) as $row) {
            if ($data_row_index >= $offset && count($rows) < $batch_size) {
                $rows[] = $row;
            }
            $data_row_index++;
            if (count($rows) >= $batch_size) {
                break;
            }
        }

        return $this->processRows($model, $target, $rows, $offset, count($rows));
    }

    private function processRows(array $model, array $target, array $rows, int $offset, int $count): array
    {
        $itemtype = $target['itemtype'];
        if (!is_a($itemtype, CommonDBTM::class, true)) {
            throw new RuntimeException(__('The selected item type is not importable.', 'glpiinject'));
        }

        foreach ($this->normalizeMappings($model['mappings'], $target) as $mapping) {
            if (!empty($mapping['is_infocom'])) {
                if ((int) ($model['behavior_add'] ?? 1) === 1 && !\Session::haveRight(\Infocom::$rightname, CREATE)) {
                    throw new RuntimeException(sprintf(__('You do not have permission to create %s.', 'glpiinject'), \Infocom::getTypeName(1)));
                }
                if ((int) ($model['behavior_update'] ?? 0) === 1 && !\Session::haveRight(\Infocom::$rightname, UPDATE)) {
                    throw new RuntimeException(sprintf(__('You do not have permission to update %s.', 'glpiinject'), \Infocom::getTypeName(1)));
                }
                continue;
            }

            $must_write = !$mapping['is_relation'];
            if ($must_write) {
                $mapped_itemtype = $mapping['is_relation'] ? $mapping['itemtype'] : $itemtype;
                if (!is_a($mapped_itemtype, CommonDBTM::class, true)) {
                    throw new RuntimeException(__('A mapped item type is not importable.', 'glpiinject'));
                }
                $mapped_item = new $mapped_itemtype();
                if ((int) ($model['behavior_add'] ?? 1) === 1 && !$mapped_item->canCreate()) {
                    throw new RuntimeException(sprintf(__('You do not have permission to create %s.', 'glpiinject'), $mapped_itemtype::getTypeName(1)));
                }
                if ((int) ($model['behavior_update'] ?? 0) === 1 && !$mapped_item->canUpdate()) {
                    throw new RuntimeException(sprintf(__('You do not have permission to update %s.', 'glpiinject'), $mapped_itemtype::getTypeName(1)));
                }
            }
        }

        $results = [
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'rows' => [],
            'errors' => [],
        ];

        $processed = 0;
        foreach ($rows as [$line, $row, $headers]) {
            $processed++;
            if ($this->normalizeMappings($model['mappings'], $target) === []) {
                continue;
            }

            $outcome = [
                'line' => $line,
                'status' => 'failed',
                'message' => '',
                'itemtype' => $itemtype,
                'items_id' => 0,
                'row' => $row,
            ];
            try {
                if ($headers !== [] && count($row) !== count($headers)) {
                    throw new RuntimeException(sprintf(
                        __('Expected %1$d columns but found %2$d.', 'glpiinject'),
                        count($headers),
                        count($row)
                    ));
                }
                $values = $this->mapRow($row, $model['mappings'], $target, $model);
                $infocom_values = $values['_infocom'] ?? [];
                unset($values['_infocom']);
                $item = new $itemtype();
                $values['entities_id'] = (int) $model['entities_id'];

                $existing = null;
                $match_field = trim((string) ($model['match_field'] ?? '')) ?: 'name';
                if ($match_field !== '' && isset($values[$match_field]) && $values[$match_field] !== '') {
                    $candidate = new $itemtype();
                    if ($candidate->getFromDBByCrit([
                        $match_field => $values[$match_field],
                        'entities_id' => (int) $model['entities_id'],
                    ])) {
                        $existing = $candidate;
                    }
                }

                if ($existing !== null) {
                    if ((int) ($model['behavior_update'] ?? 0) !== 1) {
                        throw new RuntimeException(__('Updates are disabled for this model.', 'glpiinject'));
                    }
                    $values['id'] = $existing->getID();
                    if (!$existing->update($values)) {
                        throw new RuntimeException(__('GLPI rejected the item update.', 'glpiinject'));
                    }
                    $this->saveInfocom($itemtype, $existing->getID(), $infocom_values);
                    $results['updated']++;
                    $outcome['status'] = 'updated';
                    $outcome['items_id'] = $existing->getID();
                    $results['rows'][] = $outcome;
                } else {
                    if ((int) ($model['behavior_add'] ?? 1) !== 1) {
                        throw new RuntimeException(__('Creation is disabled for this model.', 'glpiinject'));
                    }
                    $created_id = $item->add($values);
                    if ($created_id === false) {
                        throw new RuntimeException(__('GLPI rejected the item creation.', 'glpiinject'));
                    }
                    $this->saveInfocom($itemtype, (int) $created_id, $infocom_values);
                    $results['created']++;
                    $outcome['status'] = 'created';
                    $outcome['items_id'] = (int) $created_id;
                    $results['rows'][] = $outcome;
                }
            } catch (Throwable $exception) {
                $results['failed']++;
                $outcome['message'] = $exception->getMessage();
                $results['errors'][] = [
                    'line' => $line,
                    'message' => $exception->getMessage(),
                    'row' => $row,
                ];
                $results['rows'][] = $outcome;
            }
        }

        $results['processed'] = $processed;
        $results['offset'] = $offset;
        return $results;
    }

    private function getHeaders(array $model): array
    {
        foreach ($this->readRows($model) as [, $row, $headers]) {
            if ($headers !== []) {
                return $headers;
            }

            return array_map(
                static fn ($index) => sprintf(__('Column %d', 'glpiinject'), $index + 1),
                array_keys($row)
            );
        }

        return [];
    }

    private function mapRow(array $row, array $mappings, array $target, array $model, bool $create_dropdowns = true): array
    {
        $values = [];
        $mapping_options = $target['mapping_options'] ?? [];
        $mappings = $this->normalizeMappings($mappings, $target);

        foreach ($mappings as $column => $mapping) {
            $column = (int) $column;
            $value = trim((string) ($row[$column] ?? ''));
            if ($value === '') {
                if (!empty($mapping['is_mandatory'])) {
                    throw new RuntimeException(sprintf(
                        __('Required CSV value is missing for "%s".', 'glpiinject'),
                        $mapping['label']
                    ));
                }
                continue;
            }

            if (!empty($mapping['is_infocom'])) {
                $values['_infocom'][$mapping['field']] = $this->formatValue($value, $mapping, $model);
                continue;
            }

            if (!empty($mapping['is_relation'])) {
                $related_itemtype = $mapping['itemtype'];
                if (!is_a($related_itemtype, CommonDBTM::class, true)) {
                    throw new RuntimeException(__('The mapped related item type is invalid.', 'glpiinject'));
                }
                $related_item = new $related_itemtype();
                $related_found = $related_item->getFromDBByCrit([$mapping['field'] => $value]);
                if (!$related_found && $related_item instanceof CommonTreeDropdown && $mapping['field'] !== 'completename') {
                    $related_found = $related_item->getFromDBByCrit(['completename' => $value]);
                }
                if (!$related_found) {
                    if (
                        (int) ($model['can_add_dropdown'] ?? 0) === 1
                        && $related_item instanceof CommonDropdown
                        && !$related_item instanceof \Entity
                        && $related_item->canCreate()
                    ) {
                        if (!$create_dropdowns) {
                            continue;
                        }
                        $name_field = $related_item instanceof CommonTreeDropdown ? 'name' : $related_item::getNameField();
                        if ($related_item->getFromDBByCrit([$name_field => $value])) {
                            $values[$mapping['linkfield']] = $related_item->getID();
                            continue;
                        }
                        $new_id = $related_item->add([
                            $name_field => $value,
                            'entities_id' => (int) $model['entities_id'],
                        ]);
                        if ($new_id === false) {
                            throw new RuntimeException(__('GLPI rejected creation of the dropdown value.', 'glpiinject'));
                        }
                        $related_item->fields['id'] = $new_id;
                    } else {
                        throw new RuntimeException(sprintf(
                            __('No existing %1$s matches "%2$s" for "%3$s".', 'glpiinject'),
                            $related_itemtype::getTypeName(1),
                            $value,
                            $mapping['label']
                        ));
                    }
                }
                $values[$mapping['linkfield']] = $related_item->getID();
            } else {
                $values[$mapping['linkfield']] = $this->formatValue($value, $mapping, $model);
            }
        }

        return $values;
    }

    private function saveInfocom(string $itemtype, int $items_id, array $values): void
    {
        if ($values === []) {
            return;
        }

        $infocom = new \Infocom();
        $exists = $infocom->getFromDBforDevice($itemtype, $items_id);
        $input = $values + [
            'itemtype' => $itemtype,
            'items_id' => $items_id,
        ];

        if ($exists) {
            if (!$infocom->canUpdateItem()) {
                throw new RuntimeException(__('You do not have permission to update financial and administrative information.', 'glpiinject'));
            }
            $input['id'] = $infocom->getID();
            if (!$infocom->update($input)) {
                throw new RuntimeException(__('GLPI rejected the financial and administrative information update.', 'glpiinject'));
            }
            return;
        }

        if (!$infocom->can(-1, CREATE, $input)) {
            throw new RuntimeException(__('You do not have permission to create financial and administrative information for this item.', 'glpiinject'));
        }
        if ($infocom->add($input) === false) {
            throw new RuntimeException(__('GLPI rejected the financial and administrative information creation.', 'glpiinject'));
        }
    }

    private function normalizeMappings(array $mappings, array $target): array
    {
        $allowed = $target['mapping_options'] ?? [];
        $normalized = [];

        foreach ($mappings as $column => $mapping) {
            if (is_string($mapping)) {
                $mapping = [
                    'itemtype' => $target['itemtype'],
                    'field' => $mapping,
                    'linkfield' => $mapping,
                    'is_mandatory' => false,
                ];
            }
            if (!is_array($mapping)) {
                continue;
            }

            foreach ($allowed as $option) {
                if (
                    ($mapping['itemtype'] ?? null) === $option['itemtype']
                    && ($mapping['field'] ?? null) === $option['field']
                    && ($mapping['linkfield'] ?? null) === $option['linkfield']
                ) {
                    $normalized[(int) $column] = $option + [
                        'is_mandatory' => (bool) ($mapping['is_mandatory'] ?? false),
                    ];
                    break;
                }
            }
        }

        ksort($normalized);
        return $normalized;
    }

    private function formatValue(string $value, array $mapping, array $model): mixed
    {
        $datatype = $mapping['datatype'] ?? 'string';

        if ($datatype === 'date' || $datatype === 'datetime') {
            $formats = [
                'yyyy-mm-dd' => 'Y-m-d',
                'dd-mm-yyyy' => 'd-m-Y',
                'mm-dd-yyyy' => 'm-d-Y',
            ];
            $date_format = $formats[$model['date_format'] ?? 'yyyy-mm-dd'] ?? 'Y-m-d';
            $normalized_value = preg_replace('/(?<=\d)\/(?=\d)/', '-', $value);
            $input_formats = [];
            foreach (array_unique([$date_format, 'Y-m-d', 'm-d-Y', 'd-m-Y']) as $format) {
                foreach ([' H:i:s', ' H:i', '', '\\TH:i:s', ' g:i:s A', ' g:i A'] as $time_format) {
                    $input_formats[] = $format . $time_format;
                }
            }
            $date = false;
            foreach ($input_formats as $input_format) {
                $candidate = \DateTimeImmutable::createFromFormat('!' . $input_format, $normalized_value);
                $errors = \DateTimeImmutable::getLastErrors();
                if ($candidate !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                    $date = $candidate;
                    break;
                }
            }
            if ($date === false) {
                throw new RuntimeException(sprintf(__('Invalid date value "%s".', 'glpiinject'), $value));
            }

            return $date->format($datatype === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s');
        }

        if (in_array($datatype, ['number', 'integer', 'decimal'], true)) {
            if (($model['float_format'] ?? '1,234.56') === '1 234,56') {
                $value = str_replace([' ', '.'], ['', ''], $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }

            if (!is_numeric($value) || ($datatype === 'integer' && filter_var($value, FILTER_VALIDATE_INT) === false)) {
                throw new RuntimeException(sprintf(__('Invalid number value "%s".', 'glpiinject'), $value));
            }

            return $value;
        }

        if (in_array($datatype, ['bool', 'boolean'], true)) {
            $normalized = strtolower($value);
            return match ($normalized) {
                '1', 'true', 'yes', 'y' => 1,
                '0', 'false', 'no', 'n' => 0,
                default => throw new RuntimeException(sprintf(__('Invalid boolean value "%s".', 'glpiinject'), $value)),
            };
        }

        return $value;
    }

    private function readRows(array $model): iterable
    {
        $filename = (string) ($model['csv_file'] ?? '');
        if ($filename === '' || basename($filename) !== $filename) {
            throw new RuntimeException(__('The model has no valid CSV file.', 'glpiinject'));
        }

        $path = PLUGIN_GLPIINJECT_UPLOAD_DIR . '/' . $filename;
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException(__('The CSV file could not be opened.', 'glpiinject'));
        }
        $encoding = (string) ($model['file_encoding'] ?? 'UTF-8');
        if ($encoding === 'Automatic') {
            if (str_starts_with($contents, "\xEF\xBB\xBF") || preg_match('//u', $contents) === 1) {
                $encoding = 'UTF-8';
            } else {
                $encoding = 'Windows-1252';
            }
        }

        if ($encoding !== 'UTF-8') {
            $contents = iconv($encoding, 'UTF-8', $contents);
            if ($contents === false) {
                throw new RuntimeException(__('The selected file encoding could not be converted to UTF-8.', 'glpiinject'));
            }
        }

        $handle = fopen('php://temp', 'w+b');
        if ($handle === false || fwrite($handle, $contents) === false) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException(__('The CSV contents could not be prepared for reading.', 'glpiinject'));
        }
        rewind($handle);

        try {
            $headers = [];
            $line = 0;
            if ((int) $model['has_header'] === 1) {
                $headers = fgetcsv($handle, null, $model['delimiter'], '"', '\\') ?: [];
                $line++;
                if (isset($headers[0])) {
                    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
                }
            }

            while (($row = fgetcsv($handle, null, $model['delimiter'], '"', '\\')) !== false) {
                $line++;
                if ($row === [null] || count(array_filter($row, static fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                yield [$line, $row, $headers];
            }
        } finally {
            fclose($handle);
        }
    }

    private function detectEncoding($handle, string $requested): string
    {
        if ($requested !== 'Automatic') {
            return $requested;
        }

        $sample = fread($handle, 65536);
        if ($sample === false) {
            throw new RuntimeException(__('The CSV file could not be read.', 'glpiinject'));
        }
        rewind($handle);
        if (str_starts_with($sample, "\xEF\xBB\xBF")) {
            return 'UTF-8';
        }
        for ($trim = 0; $trim <= 4 && strlen($sample) >= $trim; $trim++) {
            if (preg_match('//u', $trim === 0 ? $sample : substr($sample, 0, -$trim)) === 1) {
                return 'UTF-8';
            }
        }
        return 'Windows-1252';
    }
}