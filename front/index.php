<?php

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Glpiinject\AssetTargetProvider;
use GlpiPlugin\Glpiinject\CsvImportService;
use GlpiPlugin\Glpiinject\ImportModel;
use GlpiPlugin\Glpiinject\ImportRun;
use GlpiPlugin\Glpiinject\ImportRunLog;
use GlpiPlugin\Glpiinject\MappingSuggester;

include('../../../inc/includes.php');

Session::checkRight('config', READ);

global $DB, $CFG_GLPI;

$provider = new AssetTargetProvider();
$service = new CsvImportService();
$preview_size = filter_var($_POST['preview_size'] ?? $_SESSION['glpiinject_preview_size'] ?? 10, FILTER_VALIDATE_INT);
if (!in_array($preview_size, CsvImportService::PREVIEW_SIZES, true)) {
    $preview_size = 10;
}
$_SESSION['glpiinject_preview_size'] = $preview_size;
$user_id = (int) Session::getLoginUserID();
$model_id = (int) ($_POST['models_id'] ?? $_GET['id'] ?? 0);
$run_id = (int) ($_GET['run'] ?? 0);
$preview = null;
$import_results = null;
$selected_run = null;
$error = null;
$mapping_saved = false;
$mapping_suggestions = [];
$scroll_to_preview = false;
$model_item = new ImportModel();
$model = null;

if ($model_id > 0 && $model_item->getFromDB($model_id)) {
   if ((int) $model_item->fields['users_id'] !== $user_id || !Session::haveAccessToEntity((int) $model_item->fields['entities_id'])) {
       Html::displayRightError();
   }
    $model = $model_item->fields;
    $model['mappings'] = json_decode((string) ($model['mappings'] ?? ''), true) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkRight('config', UPDATE);

   try {
       $action = (string) ($_POST['action'] ?? '');
      if ($action === '' && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && $_POST === []) {
          throw new \RuntimeException(__('The upload request could not be read. Check the server post_max_size and upload_max_filesize limits.', 'glpiinject'));
      }
      if (!in_array($action, ['upload_csv', 'save_mapping', 'preview', 'run_import'], true)) {
          throw new \RuntimeException(__('Invalid import action.', 'glpiinject'));
      }
      if ($model === null) {
          throw new \RuntimeException(__('Select a model before continuing.', 'glpiinject'));
      }
       $target = $provider->getTarget((string) $model['itemtype']);
      if ($target === null) {
          throw new \RuntimeException(__('This asset type is no longer active.', 'glpiinject'));
      }

      if ($action === 'upload_csv') {
          $upload = $_FILES['csv_file'] ?? null;
         if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
             $upload_error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
             throw new \RuntimeException(match ($upload_error) {
                 UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __('The CSV exceeds the server upload size limit.', 'glpiinject'),
                 UPLOAD_ERR_PARTIAL => __('The CSV upload was interrupted. Choose the file and try again.', 'glpiinject'),
                 UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => __('The server could not write the uploaded CSV. Check its upload temporary directory.', 'glpiinject'),
                 UPLOAD_ERR_EXTENSION => __('A server PHP extension blocked the CSV upload.', 'glpiinject'),
                 default => __('Choose a CSV file to upload.', 'glpiinject'),
             });
         }
         if (($upload['size'] ?? 0) > 10 * 1024 * 1024) {
             throw new \RuntimeException(__('CSV files must be 10 MB or smaller.', 'glpiinject'));
         }
         if (strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION)) !== 'csv') {
             throw new \RuntimeException(__('Only .csv files are accepted.', 'glpiinject'));
         }
          $delimiter = (string) ($_POST['delimiter'] ?? $model['delimiter']);
          $encoding = (string) ($_POST['file_encoding'] ?? 'Automatic');
         if (!in_array($delimiter, [',', ';', "\t", '|'], true)) {
             throw new \RuntimeException(__('Choose a supported CSV delimiter.', 'glpiinject'));
         }
         if (!in_array($encoding, ['Automatic', 'UTF-8', 'ISO-8859-1', 'Windows-1252'], true)) {
             throw new \RuntimeException(__('Choose a supported file encoding.', 'glpiinject'));
         }

          $directory = PLUGIN_GLPIINJECT_UPLOAD_DIR;
         if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
             throw new \RuntimeException(__('The plugin upload directory could not be created.', 'glpiinject'));
         }
          $filename = sprintf('model-%d-%s.csv', $model_id, bin2hex(random_bytes(8)));
         if (!move_uploaded_file((string) $upload['tmp_name'], $directory . '/' . $filename)) {
             throw new \RuntimeException(__('The CSV file could not be stored.', 'glpiinject'));
         }
          $uploaded_at = date('Y-m-d H:i:s');
          $uploaded_model = array_replace($model, [
              'id' => $model_id,
              'csv_file' => $filename,
              'csv_uploaded_at' => $uploaded_at,
              'delimiter' => $delimiter,
              'file_encoding' => $encoding,
              'has_header' => isset($_POST['has_header']) ? 1 : 0,
          ]);
         try {
            $uploaded_preview = $service->preview($uploaded_model, $target, $preview_size);
            if (!$model_item->update([
               'id' => $model_id,
               'csv_file' => $filename,
               'csv_uploaded_at' => $uploaded_at,
               'delimiter' => $delimiter,
               'file_encoding' => $encoding,
                 'has_header' => $uploaded_model['has_header'],
             ])) {
                  throw new \RuntimeException(__('The CSV was uploaded but its model settings could not be saved.', 'glpiinject'));
            }
            if (!$model_item->getFromDB($model_id) || ($model_item->fields['csv_file'] ?? null) !== $filename) {
                throw new \RuntimeException(__('The uploaded CSV reference could not be verified. Reload the model before continuing.', 'glpiinject'));
            }
         } catch (\Throwable $exception) {
            if (($model_item->fields['csv_file'] ?? null) !== $filename) {
                 unlink($directory . '/' . $filename);
            }
             throw $exception;
         }
         if (!empty($model['csv_file']) && basename($model['csv_file']) === $model['csv_file']) {
             $old_path = $directory . '/' . $model['csv_file'];
            if (is_file($old_path)) {
                unlink($old_path);
            }
         }
           $model = $uploaded_model;
           $preview = $uploaded_preview;
      }

      if ($action === 'save_mapping') {
          $mapping_options = $target['mapping_options'] ?? [];
          $mappings = [];
         foreach ((array) ($_POST['mapping'] ?? []) as $column => $mapping_input) {
             $option_index = filter_var($mapping_input['option'] ?? null, FILTER_VALIDATE_INT);
            if ($option_index === false || !isset($mapping_options[$option_index])) {
               continue;
            }

             $option = $mapping_options[$option_index];
             $mappings[(int) $column] = [
                 'itemtype' => $option['itemtype'],
                 'field' => $option['field'],
                 'linkfield' => $option['linkfield'],
                 'is_mandatory' => isset($mapping_input['is_mandatory']),
             ];
         }
          $match_field = trim((string) ($_POST['match_field'] ?? '')) ?: 'name';
          $matchable_fields = array_column(array_filter(
              $target['fields'],
              static fn (array $field) => !$field['is_custom']
          ), 'field');
         if (!in_array($match_field, $matchable_fields, true)) {
            throw new \RuntimeException(__('The duplicate matching field is not valid.', 'glpiinject'));
         }
          $updated = $model_item->update([
              'id' => $model_id,
              'match_field' => $match_field,
              'mappings' => json_encode($mappings, JSON_THROW_ON_ERROR),
          ]);
         if (!$updated) {
            throw new \RuntimeException(__('The mapping could not be saved.', 'glpiinject'));
         }
          $model['match_field'] = $match_field;
          $model['mappings'] = $mappings;
          $mapping_saved = true;
          $scroll_to_preview = true;
      }

      if (empty($model['csv_file'])) {
          throw new \RuntimeException(__('Upload a CSV file before previewing or importing.', 'glpiinject'));
      }
       $model['delimiter'] = (string) $model['delimiter'];
       $model['has_header'] = (int) $model['has_header'];
      if ($action === 'preview') {
          $preview = $service->preview($model, $target, $preview_size);
          $scroll_to_preview = true;
      } else if ($action === 'run_import') {
         if ($model['mappings'] === []) {
             throw new \RuntimeException(__('Configure at least one column mapping before importing.', 'glpiinject'));
         }
          $run = new ImportRun();
          $run_id = $run->add([
              'models_id' => $model_id,
              'users_id' => $user_id,
              'entities_id' => (int) Session::getActiveEntity(),
              'status' => 'running',
              'total' => 0,
              'processed' => 0,
              'created' => 0,
              'updated' => 0,
              'failed' => 0,
          ]);
         if ($run_id === false) {
             throw new \RuntimeException(__('Unable to start the import run.', 'glpiinject'));
         }
          $run_id = (int) $run_id;
      }
   } catch (\Throwable $exception) {
       $error = $exception->getMessage();
   }
}

if ($run_id > 0) {
    $run = new ImportRun();
   if ($run->getFromDB($run_id) && (int) $run->fields['users_id'] === $user_id && (int) $run->fields['models_id'] === $model_id) {
       $selected_run = $run->fields;
       $import_results = [
           'created' => (int) $run->fields['created'],
           'updated' => (int) $run->fields['updated'],
           'failed' => (int) $run->fields['failed'],
           'processed' => (int) $run->fields['processed'],
           'total' => (int) $run->fields['total'],
           'status' => (string) $run->fields['status'],
           'rows' => [],
       ];

       foreach ($DB->request([
           'FROM' => ImportRunLog::getTable(),
           'WHERE' => ['runs_id' => $run_id],
           'ORDER' => ['line', 'id'],
       ]) as $log_row) {
           $import_results['rows'][] = [
               'line' => (int) $log_row['line'],
               'status' => $log_row['status'],
               'message' => $log_row['message'],
               'itemtype' => $log_row['itemtype'],
               'items_id' => (int) $log_row['items_id'],
           ];
       }
       $failed_row = null;
       foreach ($import_results['rows'] as $result_row) {
          if ($result_row['status'] === 'failed') {
              $failed_row = $result_row;
              break;
          }
       }
       $import_results['first_error'] = $failed_row['message'] ?? null;
   }
}

if ($model !== null && $preview === null && !empty($model['csv_file']) && $error === null) {
   try {
       $target = $provider->getTarget((string) $model['itemtype']);
      if ($target !== null) {
          $preview = $service->preview($model, $target, $preview_size);
      }
   } catch (\Throwable $exception) {
       $error = $exception->getMessage();
   }
}

if ($model !== null) {
    $model['csv_uploaded_at_display'] = !empty($model['csv_uploaded_at'])
        ? Html::convDateTime($model['csv_uploaded_at'])
        : null;
   if ($preview !== null) {
       $suggestion_target = $provider->getTarget((string) $model['itemtype']);
      if ($suggestion_target !== null) {
          $mapping_suggestions = MappingSuggester::suggest(
              $preview['headers'],
              $suggestion_target['mapping_options'],
              $model['mappings']
          );
      }
   }
}

$models = [];
foreach ($DB->request([
    'FROM' => ImportModel::getTable(),
    'WHERE' => [
        'users_id' => $user_id,
        'entities_id' => Session::getActiveEntity(),
    ],
    'ORDER' => 'name',
]) as $row) {
    $models[] = $row;
}

Html::header(__('GLPI Inject', 'glpiinject'), $_SERVER['PHP_SELF'], 'tools', 'glpiinject');
TemplateRenderer::getInstance()->display('@glpiinject/asset_targets.html.twig', [
    'targets' => $provider->getTargets(),
    'models' => $models,
    'model' => $model,
    'preview' => $preview,
    'import_results' => $import_results,
    'selected_run' => $selected_run,
    'batch_url' => $CFG_GLPI['root_doc'] . '/plugins/glpiinject/ajax/import_batch.php',
    'csrf_token' => Session::getNewCSRFToken(),
    'error' => $error,
    'mapping_saved' => $mapping_saved,
    'mapping_suggestions' => $mapping_suggestions,
    'scroll_to_preview' => $scroll_to_preview,
    'preview_size' => $preview_size,
    'preview_sizes' => CsvImportService::PREVIEW_SIZES,
    'root_doc' => $CFG_GLPI['root_doc'],
]);

Html::footer();
