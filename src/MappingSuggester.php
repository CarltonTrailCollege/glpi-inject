<?php

namespace GlpiPlugin\Glpiinject;

final class MappingSuggester
{
   private const MINIMUM_SCORE = 0.68;
   private const WINNING_MARGIN = 0.12;

   private const PHRASE_ALIASES = [
    'asset description' => 'comments',
       'asset tag' => 'name',
       'asset name' => 'name',
    'purchase cost' => 'value',
       'purchase price' => 'value',
       'acquisition cost' => 'value',
       'purchase date' => 'date purchase',
       'acquisition date' => 'date purchase',
       'purchase order' => 'order number',
       'po number' => 'order number',
       'po no' => 'order number',
       'order no' => 'order number',
    'vendor name' => 'supplier',
       'supplier name' => 'supplier',
       'warranty provider' => 'supplier',
       'owner name' => 'user',
       'assigned user' => 'user',
       'assigned to' => 'user',
       'responsible person' => 'user',
       'responsible user' => 'user',
       'last audit date' => 'last inventory date',
       'audit date' => 'inventory date',
       'serial no' => 'serial number',
       'serial #' => 'serial number',
       'mfr' => 'manufacturer',
       'mfg' => 'manufacturer',
       'serial number' => 'serial number',
   ];

   private const TOKEN_ALIASES = [
       'description' => 'comments',
       'comment' => 'comments',
       'note' => 'comments',
       'notes' => 'comments',
       'remark' => 'comments',
       'remarks' => 'comments',
       'tag' => 'name',
       'owner' => 'user',
       'assignee' => 'user',
       'assignees' => 'user',
       'vendor' => 'supplier',
       'vendors' => 'supplier',
       'provider' => 'supplier',
       'providers' => 'supplier',
       'cost' => 'value',
       'price' => 'value',
       'amount' => 'value',
       'acquisition' => 'purchase',
       'audit' => 'inventory',
       'mfr' => 'manufacturer',
       'mfg' => 'manufacturer',
   ];

   private const IGNORED_TOKENS = ['a', 'an', 'and', 'asset', 'assets', 'field', 'glpi', 'item', 'items', 'of', 'the'];

   public static function suggest(array $headers, array $options, array $mappings): array {
       $suggestions = [];

      foreach ($headers as $column => $header) {
          $saved_option = self::findSavedOption($mappings[$column] ?? null, $options);
         if ($saved_option !== null) {
             $suggestions[$column] = [
                 'status' => 'saved',
                 'option_index' => $saved_option,
                 'label' => $options[$saved_option]['label'],
                 'candidate_count' => 1,
             ];
             continue;
         }

          $ranked_options = self::rankOptions((string) $header, $options);
          $best_score = $ranked_options[0]['score'] ?? 0.0;
          $near_matches = array_values(array_filter(
              $ranked_options,
              static fn (array $candidate): bool => $candidate['score'] >= self::MINIMUM_SCORE
                  && $best_score - $candidate['score'] < self::WINNING_MARGIN
          ));

         if ($best_score >= self::MINIMUM_SCORE && count($near_matches) === 1) {
             $option_index = $near_matches[0]['option_index'];
             $suggestions[$column] = [
                 'status' => 'suggested',
                 'option_index' => $option_index,
                 'label' => $options[$option_index]['label'],
                 'candidate_count' => 1,
             ];
         } else {
             $candidate_options = $best_score >= self::MINIMUM_SCORE
                 ? $near_matches
                 : array_values(array_filter(
                     $ranked_options,
                     static fn (array $candidate): bool => $candidate['score'] >= self::MINIMUM_SCORE
                 ));
             $suggestions[$column] = [
                 'status' => $candidate_options !== [] ? 'ambiguous' : 'unmatched',
                 'option_index' => null,
                 'label' => '',
                 'candidate_count' => count($candidate_options),
                 'candidate_labels' => array_map(
                     static fn (array $candidate): string => $options[$candidate['option_index']]['label'],
                     array_slice($candidate_options, 0, 3)
                 ),
             ];
         }
      }

       return $suggestions;
   }

   private static function findSavedOption(mixed $mapping, array $options): ?int {
      if (is_string($mapping)) {
          $mapping = ['field' => $mapping];
      }
      if (!is_array($mapping)) {
          return null;
      }

      foreach ($options as $option_index => $option) {
         if (($mapping['itemtype'] ?? null) === $option['itemtype']
              && ($mapping['field'] ?? null) === $option['field']
              && ($mapping['linkfield'] ?? $option['linkfield']) === $option['linkfield']
          ) {
              return $option_index;
         }
         if (!isset($mapping['itemtype']) && ($mapping['field'] ?? null) === $option['field']) {
             return $option_index;
         }
      }

       return null;
   }

   private static function optionKeys(array $option): array {
       $label = (string) ($option['label'] ?? '');
       $parts = preg_split('/\s*->\s*/', $label);
       return array_values(array_unique(array_merge(
           self::tokens((string) ($option['field'] ?? '')),
           self::tokens((string) end($parts)),
           self::tokens((string) ($option['itemtype_label'] ?? ''))
       )));
   }

   private static function rankOptions(string $header, array $options): array {
       $header_tokens = self::tokens($header);
      if ($header_tokens === []) {
          return [];
      }

       $ranked = [];
      foreach ($options as $option_index => $option) {
          $option_tokens = self::optionKeys($option);
         if ($option_tokens === []) {
             continue;
         }

          $intersection = count(array_intersect($header_tokens, $option_tokens));
          $dice_score = (2 * $intersection) / (count($header_tokens) + count($option_tokens));
          $containment_score = $intersection / min(count($header_tokens), count($option_tokens));
          $score = max($dice_score, $containment_score * 0.82);
         if (self::isAssetTagHeader($header) && empty($option['is_relation']) && empty($option['is_infocom'])) {
             $score = min(1.0, $score + 0.15);
         }
         if ($header_tokens === $option_tokens) {
             $score = 1.0;
         }

          $ranked[] = ['option_index' => $option_index, 'score' => $score];
      }

       usort($ranked, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);
       return $ranked;
   }

   private static function tokens(string $value): array {
       $value = strtolower(trim($value));
       $value = preg_replace('/[^\pL\pN#]+/u', ' ', $value);
      if (!is_string($value)) {
          return [];
      }

       $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
      foreach (self::PHRASE_ALIASES as $phrase => $alias) {
          $value = preg_replace('/(?<![\pL\pN])' . preg_quote($phrase, '/') . '(?![\pL\pN])/', $alias, $value) ?? $value;
      }

       $tokens = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
       $normalized = [];
      foreach ($tokens as $token) {
         if ($token === 'custom') {
             continue;
         }
          $token = self::TOKEN_ALIASES[$token] ?? $token;
          $token = self::singularize($token);
         if (!in_array($token, self::IGNORED_TOKENS, true)) {
             $normalized[] = $token;
         }
      }

       return array_values(array_unique($normalized));
   }

   private static function isAssetTagHeader(string $header): bool {
       $normalized = trim(preg_replace('/[^\pL\pN]+/u', ' ', strtolower($header)) ?? '');
       return in_array($normalized, ['asset tag', 'asset name', 'tag number', 'asset number'], true);
   }

   private static function singularize(string $token): string {
      if (strlen($token) > 3 && str_ends_with($token, 'ies')) {
          return substr($token, 0, -3) . 'y';
      }
      if (strlen($token) > 3 && str_ends_with($token, 's') && !str_ends_with($token, 'ss')) {
          return substr($token, 0, -1);
      }
       return $token;
   }
}
