<?php

use GlpiPlugin\Glpiinject\AssetTargetProvider;
use GlpiPlugin\Glpiinject\CsvImportService;
use GlpiPlugin\Glpiinject\ImportModel;
use GlpiPlugin\Glpiinject\ImportRun;
use GlpiPlugin\Glpiinject\ImportRunLog;

include('../../../inc/includes.php');

header('Content-Type: application/json; charset=UTF-8');
Html::header_nocache();
Session::checkRight('config', UPDATE);

global $DB;

try {
    $run_id = (int) ($_POST['run_id'] ?? 0);
    $offset = max(0, (int) ($_POST['offset'] ?? 0));
    $batch_size = min(50, max(1, (int) ($_POST['batch_size'] ?? 10)));

    $run = new ImportRun();
    if (
        !$run->getFromDB($run_id)
        || (int) $run->fields['users_id'] !== (int) Session::getLoginUserID()
        || $run->fields['status'] !== 'running'
    ) {
        throw new RuntimeException(__('Import run not found or unavailable.', 'glpiinject'));
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

    $staged_rows = [];
    foreach ($DB->request([
        'FROM' => ImportRunLog::getTable(),
        'WHERE' => ['runs_id' => $run_id, 'status' => 'pending'],
        'ORDER' => 'id',
        'LIMIT' => $batch_size,
    ]) as $staged_row) {
        $staged_rows[] = [
            'id' => (int) $staged_row['id'],
            'line' => (int) $staged_row['line'],
            'row' => json_decode((string) $staged_row['source_row'], true) ?: [],
        ];
    }

    $batch = (new CsvImportService())->processStagedBatch($model, $target, $staged_rows);
    $log_item = new ImportRunLog();
    foreach ($staged_rows as $index => $staged_row) {
        $outcome = $batch['rows'][$index] ?? [
            'line' => $staged_row['line'],
            'status' => 'failed',
            'message' => __('No result was returned for this row.', 'glpiinject'),
            'itemtype' => $model['itemtype'],
            'items_id' => 0,
        ];
        $log_item->update([
            'id' => $staged_row['id'],
            'status' => $outcome['status'],
            'message' => $outcome['message'] ?? '',
            'itemtype' => $outcome['itemtype'] ?? $model['itemtype'],
            'items_id' => $outcome['items_id'] ?? 0,
        ]);
    }

    $processed = min((int) $run->fields['total'], (int) $run->fields['processed'] + $batch['processed']);
    $remaining = countElementsInTable(ImportRunLog::getTable(), [
        'runs_id' => $run_id,
        'status' => 'pending',
    ]);
    $done = $processed >= (int) $run->fields['total'] || $remaining === 0;
    if ($done) {
        $processed = (int) $run->fields['total'];
    }
    $run->update([
        'id' => $run_id,
        'processed' => $processed,
        'created' => (int) $run->fields['created'] + $batch['created'],
        'updated' => (int) $run->fields['updated'] + $batch['updated'],
        'failed' => (int) $run->fields['failed'] + $batch['failed'],
        'status' => $done ? 'completed' : 'running',
    ]);

    echo json_encode([
        'offset' => $processed,
        'processed' => $processed,
        'total' => (int) $run->fields['total'],
        'progress' => (int) $run->fields['total'] > 0 ? round(($processed / (int) $run->fields['total']) * 100, 1) : 100,
        'created' => (int) $run->fields['created'] + $batch['created'],
        'updated' => (int) $run->fields['updated'] + $batch['updated'],
        'failed' => (int) $run->fields['failed'] + $batch['failed'],
        'done' => $done,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    if (isset($run_id, $run) && $run instanceof ImportRun && $run->getID() > 0 && $run->fields['status'] === 'running') {
        $run->update([
            'id' => $run_id,
            'status' => 'failed',
        ]);
    }
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
}