<?php

use GlpiPlugin\Glpiinject\AssetTargetProvider;
use GlpiPlugin\Glpiinject\CsvImportService;
use GlpiPlugin\Glpiinject\ImportModel;
use GlpiPlugin\Glpiinject\ImportRun;
use GlpiPlugin\Glpiinject\ImportRunLog;

include('../../../inc/includes.php');

header('Content-Type: application/json; charset=UTF-8');
Html::header_nocache();
global $DB;

try {
    Session::checkRight('config', UPDATE);
    $current_user_id = (int) Session::getLoginUserID();
    Session::writeClose();

    $run_id = (int) ($_POST['run_id'] ?? 0);
    $offset = max(0, (int) ($_POST['offset'] ?? 0));
    $batch_size = min(50, max(1, (int) ($_POST['batch_size'] ?? 10)));

    $run = new ImportRun();
    if (
        !$run->getFromDB($run_id)
        || (int) $run->fields['users_id'] !== $current_user_id
    ) {
        throw new RuntimeException(__('Import run not found or unavailable.', 'glpiinject'));
    }

    $action = (string) ($_POST['action'] ?? 'batch');
    if ($action === 'cancel') {
        if (($run->fields['status'] ?? null) === 'running') {
            $run->update([
                'id' => $run_id,
                'status' => 'cancelled',
                'total' => (int) $run->fields['processed'],
            ]);
            $run->getFromDB($run_id);
        }
        echo json_encode([
            'offset' => (int) $run->fields['processed'],
            'processed' => (int) $run->fields['processed'],
            'total' => (int) $run->fields['total'],
            'progress' => (int) $run->fields['total'] > 0 ? round(((int) $run->fields['processed'] / (int) $run->fields['total']) * 100, 1) : 100,
            'created' => (int) $run->fields['created'],
            'updated' => (int) $run->fields['updated'],
            'failed' => (int) $run->fields['failed'],
            'done' => true,
            'cancelled' => ($run->fields['status'] ?? null) === 'cancelled',
        ], JSON_THROW_ON_ERROR);
        return;
    }

    if (($run->fields['status'] ?? null) === 'cancelled') {
        echo json_encode([
            'offset' => (int) $run->fields['processed'],
            'processed' => (int) $run->fields['processed'],
            'total' => (int) $run->fields['total'],
            'progress' => (int) $run->fields['total'] > 0 ? round(((int) $run->fields['processed'] / (int) $run->fields['total']) * 100, 1) : 100,
            'created' => (int) $run->fields['created'],
            'updated' => (int) $run->fields['updated'],
            'failed' => (int) $run->fields['failed'],
            'done' => true,
            'cancelled' => true,
        ], JSON_THROW_ON_ERROR);
        return;
    }
    if (($run->fields['status'] ?? null) !== 'running') {
        throw new RuntimeException(__('Import run is no longer running.', 'glpiinject'));
    }

    $model_item = new ImportModel();
    if (
        !$model_item->getFromDB((int) $run->fields['models_id'])
        || (int) $model_item->fields['users_id'] !== (int) $run->fields['users_id']
    ) {
        throw new RuntimeException(__('Import model not found.', 'glpiinject'));
    }
    $model = $model_item->fields;
    $model['mappings'] = json_decode((string) ($model['mappings'] ?? ''), true) ?: [];
    $target = (new AssetTargetProvider())->getTarget((string) $model['itemtype']);
    if ($target === null) {
        throw new RuntimeException(__('The selected asset type is no longer active.', 'glpiinject'));
    }

    $batch_model = $model;
    $batch_model['delimiter'] = (string) $batch_model['delimiter'];
    $batch_model['has_header'] = (int) $batch_model['has_header'];
    $csv_batch = (new CsvImportService())->readBatch(
        $batch_model,
        (int) ($run->fields['csv_offset'] ?? 0),
        (int) ($run->fields['csv_line'] ?? 0),
        $batch_size
    );
    $rows = $csv_batch['rows'];
    $batch = (new CsvImportService())->processStagedBatch($model, $target, array_map(
        static fn(array $row) => ['line' => $row[0], 'row' => $row[1]],
        $rows
    ));
    $log_item = new ImportRunLog();
    foreach ($batch['rows'] as $outcome) {
        $log_values = [
            'runs_id' => $run_id,
            'line' => $outcome['line'],
            'status' => $outcome['status'],
            'message' => $outcome['message'] ?? '',
            'itemtype' => $outcome['itemtype'] ?? $model['itemtype'],
            'items_id' => $outcome['items_id'] ?? 0,
        ];
        $existing_log = new ImportRunLog();
        if ($existing_log->getFromDBByCrit(['runs_id' => $run_id, 'line' => $outcome['line']])) {
            $log_item->update(['id' => $existing_log->getID()] + $log_values);
        } else {
            $log_item->add($log_values + ['source_row' => json_encode($outcome['row'] ?? [], JSON_THROW_ON_ERROR)]);
        }
    }

    $processed = (int) $run->fields['processed'] + $batch['processed'];
    $created = (int) $run->fields['created'] + $batch['created'];
    $updated = (int) $run->fields['updated'] + $batch['updated'];
    $failed = (int) $run->fields['failed'] + $batch['failed'];
    $done = $csv_batch['done'];
    $run->getFromDB($run_id);
    $cancelled = ($run->fields['status'] ?? null) === 'cancelled';
    $total = ($done || $cancelled) ? $processed : 0;
    $run->update([
        'id' => $run_id,
        'processed' => $processed,
        'total' => $total,
        'csv_offset' => $csv_batch['byte_offset'],
        'csv_line' => $csv_batch['physical_line'],
        'created' => $created,
        'updated' => $updated,
        'failed' => $failed,
        'status' => $cancelled ? 'cancelled' : ($done ? 'completed' : 'running'),
    ]);

    echo json_encode([
        'offset' => $processed,
        'processed' => $processed,
        'total' => $total,
        'progress' => $done ? 100 : 0,
        'created' => $created,
        'updated' => $updated,
        'failed' => $failed,
        'done' => $done || $cancelled,
        'cancelled' => $cancelled,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    if (isset($run_id, $run) && $run instanceof ImportRun && $run->getID() > 0 && $run->fields['status'] === 'running') {
        try {
            $error_log = new ImportRunLog();
            $error_log->add([
                'runs_id' => $run_id,
                'line' => (int) $run->fields['processed'] + 1,
                'status' => 'failed',
                'message' => $exception->getMessage(),
                'itemtype' => null,
                'items_id' => 0,
                'source_row' => '',
            ]);
        } catch (Throwable) {
        }
        $run->update([
            'id' => $run_id,
            'failed' => (int) $run->fields['failed'] + 1,
            'status' => 'failed',
        ]);
    }
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
}