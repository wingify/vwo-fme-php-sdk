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

    private function initializeSchemas(): void {
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
        ];
    }

    public function isSettingsValid($settings): bool {
        if (!$settings) {
            return false;
        }

        return $this->validate($settings, $this->settingsSchema);
    }

    private function validate($data, $schema): bool {
        foreach ($schema as $key => $rules) {
            $value = $data[$key] ?? null;

            if ($value === null) {
                if (isset($rules['optional']) && $rules['optional']) {
                    continue;
                }
                return false;
            }

            $type = gettype($value);

            if (is_array($rules['type'])) {
                if (!in_array($type, $rules['type'], true)) {
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