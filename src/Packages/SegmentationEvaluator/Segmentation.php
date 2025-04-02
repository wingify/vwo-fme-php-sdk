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

namespace vwo\Packages\SegmentationEvaluator;

// Import or define necessary classes like SettingsModel
use vwo\Models\SettingsModel;

interface Segmentation {
    /**
     * Validates if the segmentation defined by the DSL is applicable given the properties and settings.
     *
     * @param array $dsl - The domain-specific language defining segmentation rules.
     * @param array $properties - The properties of the entity to be segmented.
     * @param SettingsModel $settings - The settings model containing configuration details.
     * @return bool - True if the segmentation is valid, otherwise false.
     */
    public function isSegmentationValid($dsl, $properties, $settings);
}

?>
