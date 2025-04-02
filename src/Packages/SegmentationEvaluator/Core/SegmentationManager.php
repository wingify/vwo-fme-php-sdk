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

namespace vwo\Packages\SegmentationEvaluator\Core;

use vwo\Packages\SegmentationEvaluator\Evaluators\SegmentEvaluator;
use vwo\Models\SettingsModel;
use vwo\Enums\UrlEnum;
use vwo\Utils\GatewayServiceUtil;
use vwo\Packages\Logger\Core\LogManager;
use vwo\Models\User\ContextModel;
use vwo\Models\FeatureModel;
use vwo\Models\User\ContextVWOModel;
use vwo\Services\SettingsService;
use vwo\Utils\DataTypeUtil;

class SegmentationManager {
    private static $instance;
    private $evaluator;

    /**
     * Singleton pattern implementation for getting the instance of SegmentationManager.
     * @returns SegmentationManager The singleton instance.
     */
    public static function instance(): SegmentationManager {
        if (self::$instance === null) {
            self::$instance = new SegmentationManager();
        }
        return self::$instance;
    }

    /**
     * Attaches an evaluator to the manager, or creates a new one if none is provided.
     * @param SegmentEvaluator|null $evaluator Optional evaluator to attach.
     */
    public function attachEvaluator($evaluator = null) {
        $this->evaluator = $evaluator ?? new SegmentEvaluator();
    }

    /**
     * Sets the contextual data for the segmentation process.
     * @param SettingsModel $settings The settings data.
     * @param FeatureModel $feature The feature data including segmentation needs.
     * @param ContextModel $context The context data for the evaluation.
     */
    public function setContextualData($settings, $feature, $context) {
        $this->attachEvaluator(); // Ensure a fresh evaluator instance
        $this->evaluator->settings = $settings; // Set settings in evaluator
        $this->evaluator->context = $context; // Set context in evaluator
        $this->evaluator->feature = $feature; // Set feature in evaluator

        // if both user agent and ip is null then we should not get data from gateway service
        if ($context->getUserAgent() === null && $context->getIpAddress() === null) {
            return;
        }

        if ($feature->getIsGatewayServiceRequired() === true) {
            if (
                SettingsService::instance()->isGatewayServiceProvided &&
                (DataTypeUtil::isUndefined($context->getVwo()) || $context->getVwo() === null)
            ) {
                $queryParams = [];
                if ($context->getUserAgent()) {
                    $queryParams['userAgent'] = $context->getUserAgent();
                }

                if ($context->getIpAddress()) {
                    $queryParams['ipAddress'] = $context->getIpAddress();
                }
                try {
                    $params = GatewayServiceUtil::getQueryParams($queryParams);
                    $vwoData = GatewayServiceUtil::getFromGatewayService($params, UrlEnum::GET_USER_DATA);
                    $context->setVwo((new ContextVWOModel())->modelFromDictionary($vwoData));
                } catch (\Exception $err) {
                    LogManager::instance()->error('Error in setting contextual data for segmentation. Got error: ' . $err->getMessage());
                }
            }
        }
    }

    /**
     * Validates the segmentation against provided DSL and properties.
     * @param array $dsl The segmentation DSL.
     * @param array $properties The properties to validate against.
     * @returns bool True if segmentation is valid, otherwise false.
     */
    public function validateSegmentation($dsl, $properties) {
        return $this->evaluator->isSegmentationValid($dsl, $properties); // Delegate to evaluator's method
    }
}

?>
