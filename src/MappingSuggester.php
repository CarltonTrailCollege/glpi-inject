<?php

namespace GlpiPlugin\Glpiinject;

final class MappingSuggester
{
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

          $header_key = self::normalize((string) $header);
          $header_keys = self::commonVariations($header_key);
          $matches = [];
         if ($header_keys !== []) {
            foreach ($options as $option_index => $option) {
               if (array_intersect($header_keys, self::optionKeys($option)) !== []) {
                   $matches[] = $option_index;
               }
            }
         }

         if (count($matches) === 1) {
             $option_index = $matches[0];
             $suggestions[$column] = [
                 'status' => 'suggested',
                 'option_index' => $option_index,
                 'label' => $options[$option_index]['label'],
                 'candidate_count' => 1,
             ];
         } else {
             $suggestions[$column] = [
                 'status' => count($matches) > 1 ? 'ambiguous' : 'unmatched',
                 'option_index' => null,
                 'label' => '',
                 'candidate_count' => count($matches),
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
       $keys = self::commonVariations(self::normalize((string) ($option['field'] ?? '')));
       $label = (string) ($option['label'] ?? '');
       $parts = preg_split('/\s*->\s*/', $label);
       $keys = array_merge($keys, self::commonVariations(self::normalize((string) end($parts))));
       return array_values(array_unique(array_filter($keys)));
   }

   private static function commonVariations(string $value): array {
      if ($value === '') {
          return [];
      }

       $variations = [$value];
      if (str_ends_with($value, 's') && strlen($value) > 1) {
          $variations[] = substr($value, 0, -1);
      } else {
          $variations[] = $value . 's';
      }
       return $variations;
   }

   private static function normalize(string $value): string {
      $value = strtolower(trim($value));
       $normalized = preg_replace('/[^\pL\pN]/u', '', $value);
      if (!is_string($normalized)) {
          return '';
      }
      if (str_starts_with($normalized, 'custom')) {
          $normalized = substr($normalized, 6);
      }
       return $normalized;
   }
}
