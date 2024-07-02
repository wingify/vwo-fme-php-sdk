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

namespace vwo\Packages\SegmentationEvaluator\Core;

use vwo\Packages\SegmentationEvaluator\Evaluators\SegmentEvaluator;
use vwo\Models\SettingsModel;

class SegmentationManager {
    private static $instance;
    private $evaluator;

    public static function instance(): SegmentationManager {
        self::$instance = self::$instance ?? new SegmentationManager();
        return self::$instance;
    }

    public function attachEvaluator($evaluator = null): void {
        $this->evaluator = $evaluator ?? new SegmentEvaluator();
    }

    public function validateSegmentation($dsl, $properties, $settings, $context = null) {
        return $this->evaluator->isSegmentationValid($dsl, $properties, $settings, $context);
    }
}

?>
