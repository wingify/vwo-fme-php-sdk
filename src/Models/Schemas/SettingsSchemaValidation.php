<?php

/**
 * Copyright 2024 Wingify Software Pvt. Ltd.
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
    public $campaignGoalSchema;
    public $variableObjectSchema;
    public $campaignVariationSchema;
    public $campaignObjectSchema;
    public $settingsFileSchema;
    public $campaignGroupSchema;
    public $featureSchema;

    public function __construct() {
        $this->initializeSchemas();
    }

    private function initializeSchemas(): void {
        $this->campaignGoalSchema = [
            'id' => ['type' => ['number', 'string']],
            'key' => ['type' => 'string'],
            'type' => ['type' => 'string']
        ];

        $this->variableObjectSchema = [
            'id' => ['type' => ['number', 'string']],
            'type' => ['type' => 'string'],
            'key' => ['type' => 'string'],
            'value' => ['type' => ['number', 'string', 'boolean']]
        ];

        $this->campaignVariationSchema = [
            'id' => ['type' => ['number', 'string']],
            'key' => ['type' => 'string'],
            'weight' => ['type' => ['number', 'string']],
            'segments' => ['type' => 'array', 'optional' => true],
            'variables' => ['type' => 'array', 'schema' => $this->variableObjectSchema, 'optional' => true],
            'startRangeVariation' => ['type' => 'number', 'optional' => true],
            'endRangeVariation' => ['type' => 'number', 'optional' => true]
        ];

        $this->campaignObjectSchema = [
            'id' => ['type' => ['number', 'string']],
            'type' => ['type' => 'string'],
            'key' => ['type' => 'string'],
            'featureId' => ['type' => 'number', 'optional' => true],
            'featureKey' => ['type' => 'string', 'optional' => true],
            'percentTraffic' => ['type' => 'number'],
            'goals' => ['type' => 'array', 'schema' => $this->campaignGoalSchema],
            'variations' => ['type' => 'array', 'schema' => $this->campaignVariationSchema],
            'variables' => ['type' => 'array', 'schema' => $this->variableObjectSchema, 'optional' => true],
            'segments' => ['type' => 'array'],
            'isForcedVariationEnabled' => ['type' => 'boolean', 'optional' => true],
            'priority' => ['type' => 'number'],
            'autoActivate' => ['type' => 'boolean'],
            'autoTrack' => ['type' => 'boolean']
        ];

        $this->featureSchema = [
            'id' => ['type' => ['number', 'string']],
            'key' => ['type' => 'string'],
            'variables' => ['type' => 'array', 'schema' => $this->variableObjectSchema, 'optional' => true],
            'campaigns' => ['type' => 'array', 'schema' => $this->campaignGroupSchema]
        ];

        $this->settingsFileSchema = [
            'sdkKey' => ['type' => 'string', 'optional' => true],
            'version' => ['type' => ['number', 'string']],
            'accountId' => ['type' => ['number', 'string']],
            'features' => ['type' => 'array', 'schema' => $this->featureSchema, 'optional' => true]
        ];

        $this->campaignGroupSchema = [
            'id' => ['type' => 'number'],
            'campaigns' => ['type' => 'array', 'schema' => ['type' => 'number']]
        ];
    }

    public function isSettingsValid($settings): bool {
        return $this->validate($settings, $this->settingsFileSchema);
    }

    private function validate($data, $schema): bool {
        foreach ($schema as $key => $rules) {
            if (!isset($data[$key])) {
                if (isset($rules['optional']) && $rules['optional']) {
                    continue;
                }
                return false;
            }

            $value = $data[$key];
            $type = gettype($value);

            if (is_array($rules['type'])) {
                if (!in_array($type, $rules['type'])) {
                    return false;
                }
            } else {
                if ($type !== $rules['type']) {
                    return false;
                }
            }

            if ($type === 'array' && isset($rules['schema'])) {
                foreach ($value as $item) {
                    if (!$this->validate($item, $rules['schema'])) {
                        return false;
                    }
                }
            }
        }
        return true;
    }
}


?>
