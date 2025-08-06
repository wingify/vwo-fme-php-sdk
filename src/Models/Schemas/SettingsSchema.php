<?php

/**
 * Copyright 2024-2025 Wingify Software Pvt. Ltd.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace vwo\Models\Schemas;

class SettingsSchema {
    private $campaignMetricSchema;
    private $variableObjectSchema;
    private $campaignVariationSchema;
    private $campaignObjectSchema;
    private $settingsSchema;
    private $featureSchema;
    private $ruleSchema;

    public function __construct() {
        $this->initializeSchemas();
    }

    private function initializeSchemas() {
        $this->campaignMetricSchema = [
            'id' => ['type' => ['number', 'string']],
            'type' => ['type' => 'string'],
            'identifier' => ['type' => 'string'],
            'mca' => ['type' => ['number', 'string'], 'optional' => true],
            'hasProps' => ['type' => 'boolean', 'optional' => true],
            'revenueProp' => ['type' => 'string', 'optional' => true],
        ];

        $this->variableObjectSchema = [
            'id' => ['type' => ['number', 'string']],
            'type' => ['type' => 'string'],
            'key' => ['type' => 'string'],
            'value' => ['type' => ['number', 'string', 'boolean', 'array']],
        ];

        $this->campaignVariationSchema = [
            'id' => ['type' => ['number', 'string']],
            'name' => ['type' => 'string'],
            'weight' => ['type' => ['number', 'string']],
            'segments' => ['type' => 'array', 'optional' => true],
            'variables' => ['type' => 'array', 'schema' => $this->variableObjectSchema, 'optional' => true],
            'startRangeVariation' => ['type' => 'number', 'optional' => true],
            'endRangeVariation' => ['type' => 'number', 'optional' => true],
        ];

        $this->campaignObjectSchema = [
            'id' => ['type' => ['number', 'string']],
            'type' => ['type' => 'string'],
            'key' => ['type' => 'string'],
            'percentTraffic' => ['type' => 'number', 'optional' => true],
            'status' => ['type' => 'string'],
            'variations' => ['type' => 'array', 'schema' => $this->campaignVariationSchema],
            'segments' => ['type' => 'array'],
            'isForcedVariationEnabled' => ['type' => 'boolean', 'optional' => true],
            'isAlwaysCheckSegment' => ['type' => 'boolean', 'optional' => true],
            'name' => ['type' => 'string'],
        ];

        $this->ruleSchema = [
            'type' => ['type' => 'string'],
            'ruleKey' => ['type' => 'string'],
            'campaignId' => ['type' => 'number'],
            'variationId' => ['type' => 'number', 'optional' => true],
        ];

        $this->featureSchema = [
            'id' => ['type' => ['number', 'string']],
            'key' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'type' => ['type' => 'string'],
            'metrics' => ['type' => 'array', 'schema' => $this->campaignMetricSchema],
            'impactCampaign' => ['type' => 'array', 'optional' => true],
            'rules' => ['type' => 'array', 'schema' => $this->ruleSchema, 'optional' => true],
            'variables' => ['type' => 'array', 'schema' => $this->variableObjectSchema, 'optional' => true],
        ];

        $this->settingsSchema = [
            'sdkKey' => ['type' => 'string', 'optional' => true],
            'version' => ['type' => ['number', 'string']],
            'accountId' => ['type' => ['number', 'string']],
            'features' => ['type' => 'array', 'schema' => $this->featureSchema, 'optional' => true],
            'campaigns' => ['type' => 'array', 'schema' => $this->campaignObjectSchema],
            'groups' => ['type' => 'array', 'optional' => true],
            'campaignGroups' => ['type' => 'array', 'optional' => true],
            'collectionPrefix' => ['type' => 'string', 'optional' => true],
            'wasInitializedEarlier' => ['type' => 'boolean', 'optional' => true]
        ];
    }

    public function deepConvertToArray($data) {
        if (is_object($data)) {
            $data = get_object_vars($data); // Convert object to associative array
        }
        if (is_array($data)) {
            // Use [$this, 'deepConvertToArray'] to reference the method within the class
            return array_map([$this, 'deepConvertToArray'], $data);
        }
        return $data; // Return primitive data types as is
    }
    
    public function isSettingsValid($settings): bool {
        if (!$settings) {
            return false;
        }
    
        // Use deepConvertToArray for robust normalization
        $normalizedSettings = is_object($settings) ? $this->deepConvertToArray($settings) : $settings;
    
        // Validate required keys and schema
        $isValid = $this->validate($normalizedSettings, $this->settingsSchema);
    
        return $isValid;
    }
    

    private function validate($data, $schema): bool {
        foreach ($schema as $key => $rules) {
            // Retrieve the value for the current key or set it to null if missing
            $value = $data[$key] ?? null;
    
            // Handle missing values for optional keys
            if ($value === null) {
                if (!empty($rules['optional'])) {
                    continue; // Skip validation for optional keys
                }
                // Log the missing key
                return false; // Required key is missing
            }
    
            // Convert object values to arrays for consistent handling
            if (is_object($value)) {
                $value = json_decode(json_encode($value), true); // Deep conversion of object to array
            }
    
            // Normalize the type (e.g., treat integers as numbers)
            $type = gettype($value);
            if ($type === 'integer') {
                $type = 'number';
            }
    
            // Validate the type of the value against the schema
            if (is_array($rules['type'])) {
                if (!in_array($type, $rules['type'], true)) {
                    // Debugging message for type mismatch
                    return false;
                }
            } elseif ($type !== $rules['type']) {
                // Debugging message for type mismatch
                return false;
            }
    
            // If the value is an array, validate its items recursively using the nested schema
            if ($type === 'array' && isset($rules['schema'])) {
                foreach ($value as $item) {
                    // Convert nested objects to arrays for consistent handling
                    if (is_object($item)) {
                        $item = json_decode(json_encode($item), true);
                    }
                    if (!$this->validate($item, $rules['schema'])) {
                        return false; // Nested validation failed
                    }
                }
            }
        }
    
        // All validations passed for the current schema
        return true;
    }       
}
?>