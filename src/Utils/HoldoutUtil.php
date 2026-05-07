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

namespace vwo\Utils;

use vwo\Models\SettingsModel;
use vwo\Models\HoldoutModel;
use vwo\Models\FeatureModel;
use vwo\Models\User\ContextModel;
use vwo\Services\ServiceContainer;
use vwo\Services\StorageService;
use vwo\Decorators\StorageDecorator;
use vwo\Packages\DecisionMaker\DecisionMaker;
use vwo\Enums\EventEnum;
use vwo\Constants\Constants;

class HoldoutUtil
{
    /**
     * Gets the applicable holdouts for a given feature ID.
     * Includes global holdouts and holdouts that have the given feature ID.
     *
     * @param SettingsModel $settings
     * @param int $featureId
     * @return HoldoutModel[]
     */
    public static function getApplicableHoldouts(SettingsModel $settings, $featureId)
    {
        $holdouts = $settings->getHoldouts();
        if (!is_array($holdouts)) {
            return [];
        }
        return array_values(array_filter($holdouts, function (HoldoutModel $holdout) use ($featureId) {
            return $holdout->getIsGlobal() || in_array($featureId, $holdout->getFeatureIds(), true);
        }));
    }

    /**
     * Gets the matched holdout(s) for a given feature and context.
     * Evaluates applicable holdouts, creates payloads for all, and returns matched vs not matched.
     *
     * @param ServiceContainer $serviceContainer
     * @param FeatureModel $feature
     * @param ContextModel $context
     * @param array|null $storedData Keys: isPartOfHoldoutIds, notPartOfHoldoutIds
     * @return array{matchedHoldouts: HoldoutModel[], notMatchedHoldouts: HoldoutModel[], holdoutPayloads: array}
     */
    public static function getMatchedHoldouts(
        ServiceContainer $serviceContainer,
        FeatureModel $feature,
        ContextModel $context,
        $storedData = null
    ) {
        $settings = $serviceContainer->getSettings();
        $storedData = is_array($storedData) ? $storedData : [];
        $isPartOfHoldoutIds = $storedData['isInHoldoutId'] ?? [];
        $notPartOfHoldoutIds = $storedData['notInHoldoutId'] ?? [];
        $alreadyEvaluatedHoldoutIds = array_merge(
            is_array($isPartOfHoldoutIds) ? $isPartOfHoldoutIds : [],
            is_array($notPartOfHoldoutIds) ? $notPartOfHoldoutIds : []
        );
        $alreadyEvaluatedHoldoutIdsNormalized = array_map('strval', $alreadyEvaluatedHoldoutIds);

        $featureId = $feature->getId();
        $featureKey = $feature->getKey();
        // get applicable holdouts for the feature
        $applicableHoldouts = self::getApplicableHoldouts($settings, $featureId);

        $notMatchedHoldouts = [];
        $matchedHoldouts = [];
        $holdoutPayloads = [];

        // if no applicable holdouts, return empty arrays
        if (empty($applicableHoldouts)) {
            return [
                'matchedHoldouts' => [],
                'notMatchedHoldouts' => [],
                'holdoutPayloads' => [],
            ];
        }

        $networkUtil = new NetworkUtil($serviceContainer);
        $decisionMaker = new DecisionMaker();
        $loggerService = $serviceContainer->getLoggerService();
        $segmentationManager = $serviceContainer->getSegmentationManager();

        // iterate over applicable holdouts and evaluate them
        foreach ($applicableHoldouts as $holdout) {
            if (in_array((string) $holdout->getId(), $alreadyEvaluatedHoldoutIdsNormalized, true)) {
                // if holdout has already been evaluated, skip evaluation
                $loggerService->debug('HOLDOUT_SKIP_EVALUATION', [
                    'holdoutId' => $holdout->getId(),
                    'userId' => $context->getId(),
                    'reason' => "user " . $context->getId() . " was already evaluated for feature with id: " . $featureId . "; SKIP decision making altogether.",
                ]);
                continue;
            }

            // get segments for the holdout
            $segments = $holdout->getSegments();
            $segmentPass = true;
            // if segments are not empty, validate them
            if ((is_array($segments) && !empty($segments)) || (is_object($segments) && !empty((array) $segments))) {
                $segmentPass = $segmentationManager->validateSegmentation(
                    $segments,
                    $context->getCustomVariables() ?: []
                );
            } else {
                $loggerService->info('HOLDOUT_SEGMENTATION_SKIP', [
                    'holdoutId' => $holdout->getId(),
                    'userId' => $context->getId(),
                ]);
            }

            // initialize variation id and is in holdout
            $variationId = null;
            $isInHoldout = false;

            // if segments got failed, set variation id to not part of holdout
            if (!$segmentPass) {
                $loggerService->debug('HOLDOUT_SEGMENTATION_FAIL', [
                    'userId' => $context->getId(),
                    'holdoutGroupName' => $holdout->getName(),
                ]);
                $variationId = Constants::VARIATION_NOT_PART_OF_HOLDOUT;
                $notMatchedHoldouts[] = $holdout;
            } else {
                $loggerService->debug('HOLDOUT_SEGMENTATION_PASS', [
                    'userId' => $context->getId(),
                    'holdoutGroupName' => $holdout->getName(),
                ]);
                // get hash key for the holdout
                $hashKey = $settings->getAccountId() . '_' . $holdout->getId() . '_' . $context->getId();
                $bucket = $decisionMaker->getBucketValueForUser($hashKey, 100);
                $isInHoldout = $bucket !== 0 && $bucket <= $holdout->getPercentTraffic();
                // set variation id based on if user is in holdout or not
                $variationId = $isInHoldout
                    ? Constants::VARIATION_IS_PART_OF_HOLDOUT
                    : Constants::VARIATION_NOT_PART_OF_HOLDOUT;

                // if user is in holdout, log it
                if ($isInHoldout) {
                    $loggerService->info('HOLDOUT_SHOULD_EXCLUDE_USER', [
                        'userId' => $context->getId(),
                        'bucketValue' => $bucket,
                        'holdoutGroupName' => $holdout->getName(),
                        'percentTraffic' => $holdout->getPercentTraffic(),
                        'featureId' => $featureId,
                    
                    ]);
                    // if user is in holdout, add to matched holdouts
                    $matchedHoldouts[] = $holdout;
                } else {
                    $loggerService->debug('HOLDOUT_SHOULD_NOT_EXCLUDE_USER', [
                        'userId' => $context->getId(),
                        'holdoutGroupName' => $holdout->getName(),
                        'featureId' => $featureId
                    ]);
                    // if user is not in holdout, add to not matched holdouts
                    $notMatchedHoldouts[] = $holdout;
                }
            }

            // create holdout payload
            $payload = $networkUtil->createHoldoutPayload(
                $serviceContainer,
                EventEnum::VWO_VARIATION_SHOWN,
                $holdout->getId(),
                $variationId,
                $context,
                $featureId
            );

            // add payload to holdout payloads
            $holdoutPayloads[] = $payload;
        }

        return [
            'matchedHoldouts' => $matchedHoldouts,
            'notMatchedHoldouts' => $notMatchedHoldouts,
            'holdoutPayloads' => $holdoutPayloads,
        ];
    }

    /**
     * Sends network calls for applicable holdouts that are not stored (not in notInHoldoutIds).
     * Updates storage with notInHoldoutId for each sent holdout.
     *
     * @param ServiceContainer $serviceContainer
     * @param FeatureModel $feature
     * @param ContextModel $context
     * @param array|null $storedData Key: notInHoldoutId (array of ids)
     * @param StorageService $storageService
     * @return void
     */
    public static function sendNetworkCallsForNotInHoldouts(
        ServiceContainer $serviceContainer,
        FeatureModel $feature,
        ContextModel $context,
        $decision,
        $storedData,
        StorageService $storageService
    ) {
        // get applicable holdouts for the feature  
        $applicableHoldouts = self::getApplicableHoldouts($serviceContainer->getSettings(), $feature->getId());
        // get updated not in holdout ids from stored data
        $updatedNotInHoldoutIds = $storedData['notInHoldoutId'] ?? [];

        $isInHoldoutIds = $storedData['isInHoldoutId'] ?? [];
        $initialNotInHoldoutCount = count($updatedNotInHoldoutIds);

        if (count($applicableHoldouts) > 0) {
            $decision['isHoldoutPresent'] = true;
        }
        $batchPayload = [];
        $networkUtil = new NetworkUtil($serviceContainer);

        // iterate over applicable holdouts and send network calls for them
        foreach ($applicableHoldouts as $holdout) {
            
            // if holdout id is in updated not in holdout ids, skip sending network call
            if ((in_array($holdout->getId(), $updatedNotInHoldoutIds, true)) || (in_array($holdout->getId(), $isInHoldoutIds, true))) {
                continue;
            }
            // add holdout id to updated not in holdout ids
            $updatedNotInHoldoutIds[] = $holdout->getId();

            // create holdout payload
            $payload = $networkUtil->createHoldoutPayload(
                $serviceContainer,
                EventEnum::VWO_VARIATION_SHOWN,
                $holdout->getId(),
                Constants::VARIATION_NOT_PART_OF_HOLDOUT,
                $context,
                $feature->getId()
            );

            // if gateway service is provided and payload is not null, send impression for variation shown
            if ($serviceContainer->getSettingsService()->isGatewayServiceProvided && $payload !== null) {
                ImpressionUtil::SendImpressionForVariationShown(
                    $serviceContainer,
                    $payload,
                    $context,
                    $feature->getKey()
                );
            } elseif ($payload !== null) {
                // if payload is not null, add to batch payload
                $batchPayload[] = $payload;
            }
        }

        if (count($updatedNotInHoldoutIds) > $initialNotInHoldoutCount) {
            // set data in storage
            (new StorageDecorator())->setDataInStorage(
                [
                    'featureKey' => $feature->getKey(),
                    'context' => $context,
                    'notInHoldoutId' => $updatedNotInHoldoutIds,
                ],
                $storageService,
                $serviceContainer
            );
        }
        // if batch payload is not empty, send impression for variation shown in batch
        if (!empty($batchPayload)) {
            // send impression for variation shown in batch
            ImpressionUtil::SendImpressionForVariationShownInBatch($batchPayload, $serviceContainer);
        }
    }
}