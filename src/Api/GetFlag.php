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

namespace vwo\Api;

use vwo\Decorators\StorageDecorator;
use vwo\Models\CampaignModel;
use vwo\Models\FeatureModel;
use vwo\Models\SettingsModel;
use vwo\Models\VariationModel;
use vwo\Models\User\ContextModel;
use vwo\Services\StorageService;
use vwo\Services\HooksService;
use vwo\Enums\ApiEnum;
use vwo\Enums\CampaignTypeEnum;
use vwo\Packages\Logger\Core\LogManager;
use vwo\Packages\SegmentationEvaluator\Core\SegmentationManager;
use vwo\Utils\CampaignUtil;
use vwo\Utils\DataTypeUtil;
use vwo\Utils\DecisionUtil;
use vwo\Utils\FunctionUtil;
use vwo\Utils\ImpressionUtil;
use vwo\Utils\GetFlagResultUtil;
use vwo\Utils\RuleEvaluationUtil;

class GetFlag
{
    public function get(
        string $featureKey,
        SettingsModel $settings,
        ContextModel $context,
        HooksService $hooksService
    ) {
        $ruleEvaluationUtil = new RuleEvaluationUtil();
        $isEnabled = false;
        $rolloutVariationToReturn = null;
        $experimentVariationToReturn = null;
        $shouldCheckForExperimentsRules = false;

        $passedRulesInformation = [];
        $evaluatedFeatureMap = [];
        $storageService = new StorageService();

        // Get feature object from feature key
        $feature = FunctionUtil::getFeatureFromKey($settings, $featureKey);
        $decision = [
            'featureName' => $feature ? $feature->getName() : null,
            'featureId' => $feature ? $feature->getId() : null,
            'featureKey' => $feature ? $feature->getKey() : null,
            'userId' => $context ? $context->getId() : null,
            'api' => ApiEnum::GET_FLAG,
        ];

        // Retrieve stored data
        $storedData = (new StorageDecorator())->getFeatureFromStorage(
            $featureKey,
            $context,
            $storageService
        );

        if (isset($storedData['experimentVariationId'])) {
            if (isset($storedData['experimentKey'])) {
                $variation = CampaignUtil::getVariationFromCampaignKey(
                    $settings,
                    $storedData['experimentKey'],
                    $storedData['experimentVariationId']
                );

                if ($variation) {
                    LogManager::instance()->info(sprintf(
                        "Variation %s found in storage for the user %s for the experiment: %s",
                        $variation->getKey(),
                        $context->getId(),
                        $storedData['experimentKey']
                    ));

                    return new GetFlagResultUtil(true, $variation->getVariables(), []);
                }
            }
        } elseif (isset($storedData['rolloutKey'], $storedData['rolloutId'])) {
            $variation = CampaignUtil::getVariationFromCampaignKey(
                $settings,
                $storedData['rolloutKey'],
                $storedData['rolloutVariationId']
            );

            if ($variation) {
                LogManager::instance()->info(sprintf(
                    "Variation %s found in storage for the user %s for the rollout experiment: %s",
                    $variation->getKey(),
                    $context->getId(),
                    $storedData['rolloutKey']
                ));

                LogManager::instance()->debug(sprintf(
                    "Rollout rule got passed for user %s. Hence, evaluating experiments",
                    $context->getId()
                ));

                $isEnabled = true;
                $shouldCheckForExperimentsRules = true;
                $rolloutVariationToReturn = $variation;
                $evaluatedFeatureMap[$featureKey] = [
                    'rolloutId' => $storedData['rolloutId'],
                    'rolloutKey' => $storedData['rolloutKey'],
                    'rolloutVariationId' => $storedData['rolloutVariationId']
                ];
                $passedRulesInformation = array_merge($passedRulesInformation, $evaluatedFeatureMap[$featureKey]);
            }
        }

        if (!DataTypeUtil::isObject($feature)) {
            LogManager::instance()->error(sprintf(
                "Feature not found for the key: %s",
                $featureKey
            ));

            return new GetFlagResultUtil(false, [], []);
        }

        SegmentationManager::instance()->setContextualData($settings, $feature, $context);

        // Evaluate Rollout Rules
        $rollOutRules = FunctionUtil::getSpecificRulesBasedOnType($feature, CampaignTypeEnum::ROLLOUT);
        if (count($rollOutRules) > 0 && !$isEnabled) {
            $megGroupWinnerCampaigns = [];
            foreach ($rollOutRules as $rule) {
                $evaluateRuleResult = $ruleEvaluationUtil->evaluateRule(
                    $settings,
                    $feature,
                    $rule,
                    $context,
                    $evaluatedFeatureMap,
                    $megGroupWinnerCampaigns,
                    $storageService,
                    $decision
                );

                if ($evaluateRuleResult['preSegmentationResult']) {
                    $evaluatedFeatureMap[$featureKey] = [
                        'rolloutId' => $rule->getId(),
                        'rolloutKey' => $rule->getKey(),
                        'rolloutVariationId' => $rule->getVariations()[0]->getId()
                    ];
                    $ruleStatus[$rule->getRuleKey()] = "Passed";
                    break;
                } else {
                    $ruleStatus[$rule->getRuleKey()] = "Failed";
                }
            }

            if (isset($evaluatedFeatureMap[$featureKey])) {
                $passedRolloutCampaign = new CampaignModel();
                $passedRolloutCampaign->modelFromDictionary($rule);
                $variation = DecisionUtil::evaluateTrafficAndGetVariation($settings, $passedRolloutCampaign, $context->getId());

                if (DataTypeUtil::isObject($variation) && !empty($variation)) {
                    $isEnabled = true;
                    $shouldCheckForExperimentsRules = true;
                    $rolloutVariationToReturn = $variation;

                    $this->updateIntegrationsDecisionObject($passedRolloutCampaign, $variation, $passedRulesInformation, $decision);

                    ImpressionUtil::createAndSendImpressionForVariationShown(
                        $settings,
                        $passedRolloutCampaign->getId(),
                        $variation->getId(),
                        $context
                    );
                }
            }
        } else if (count($rollOutRules) === 0) {
            LogManager::instance()->debug("No Rollout rules present for the feature. Hence, checking experiment rules");
            $shouldCheckForExperimentsRules = true;
        }

        // Evaluate Experiment Rules
        if ($shouldCheckForExperimentsRules) {
            $experimentRules = FunctionUtil::getAllExperimentRules($feature);
            $experimentRulesToEvaluate = [];

            $megGroupWinnerCampaigns = [];
            foreach ($experimentRules as $rule) {
                $evaluateRuleResult = $ruleEvaluationUtil->evaluateRule(
                    $settings,
                    $feature,
                    $rule,
                    $context,
                    $evaluatedFeatureMap,
                    $megGroupWinnerCampaigns,
                    $storageService,
                    $decision
                );

                if ($evaluateRuleResult['preSegmentationResult']) {
                    if ($evaluateRuleResult['whitelistedObject'] === null) {
                        $experimentRulesToEvaluate[] = $rule;
                    } else {
                        $isEnabled = true;
                        $experimentVariationToReturn = $evaluateRuleResult['whitelistedObject']['variation'];

                        $passedRulesInformation = array_merge($passedRulesInformation, [
                            'experimentId' => $rule->getId(),
                            'experimentKey' => $rule->getKey(),
                            'experimentVariationId' => $evaluateRuleResult['whitelistedObject']['variationId'],
                        ]);
                    }
                    $ruleStatus[$rule->getRuleKey()] = "Passed";
                    break;
                } else {
                    $ruleStatus[$rule->getRuleKey()] = "Failed";
                }
            }

            if (isset($experimentRulesToEvaluate[0])) {
                $campaign = new CampaignModel();
                $campaign->modelFromDictionary($experimentRulesToEvaluate[0]);
                $variation = DecisionUtil::evaluateTrafficAndGetVariation($settings, $campaign, $context->getId());

                if (DataTypeUtil::isObject($variation) && !empty($variation)) {
                    $isEnabled = true;
                    $experimentVariationToReturn = $variation;

                    $this->updateIntegrationsDecisionObject($campaign, $variation, $passedRulesInformation, $decision);

                    ImpressionUtil::createAndSendImpressionForVariationShown(
                        $settings,
                        $campaign->getId(),
                        $variation->getId(),
                        $context
                    );
                }
            }
        }

        // If flag is enabled, store it in data
        if ($isEnabled) {
            (new StorageDecorator())->setDataInStorage(
                array_merge([
                    'featureKey' => $featureKey,
                    'context' => $context
                ], $passedRulesInformation),
                $storageService
            );
        }

        // Call integration callback, if defined
        $hooksService->set($decision);
        $hooksService->execute($hooksService->get());

        // Send data for Impact Campaign, if defined
        if ($feature->getImpactCampaign()->getCampaignId()) {
            $status = $isEnabled ? 'enabled' : 'disabled';
            LogManager::instance()->info(sprintf(
                "Tracking feature: %s being %s for Impact Analysis Campaign for the user %s",
                $featureKey,
                $status,
                $context->getId()
            ));

            ImpressionUtil::createAndSendImpressionForVariationShown(
                $settings,
                $feature->getImpactCampaign()->getCampaignId(),
                $isEnabled ? 2 : 1,
                $context
            );
        }

        $variablesForEvaluatedFlag = [];

        if ($experimentVariationToReturn !== null) {
            $variablesForEvaluatedFlag = $experimentVariationToReturn->getVariables();
        } elseif ($rolloutVariationToReturn !== null) {
            $variablesForEvaluatedFlag = $rolloutVariationToReturn->getVariables();
        }

        return new GetFlagResultUtil($isEnabled, $variablesForEvaluatedFlag, []);
    }

    private function updateIntegrationsDecisionObject(CampaignModel $campaign, VariationModel $variation, array &$passedRulesInformation, array &$decision)
    {
        if ($campaign->getType() === CampaignTypeEnum::ROLLOUT) {
            $passedRulesInformation = array_merge($passedRulesInformation, [
                'rolloutId' => $campaign->getId(),
                'rolloutKey' => $campaign->getKey(),
                'rolloutVariationId' => $variation->getId(),
            ]);
        } else {
            $passedRulesInformation = array_merge($passedRulesInformation, [
                'experimentId' => $campaign->getId(),
                'experimentKey' => $campaign->getKey(),
                'experimentVariationId' => $variation->getId(),
            ]);
        }

        $decision = array_merge($decision, $passedRulesInformation);
    }
}
