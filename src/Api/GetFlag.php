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

namespace vwo\Api;

use vwo\Decorators\StorageDecorator;
use vwo\Models\CampaignModel;
use vwo\Models\FeatureModel;
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
use vwo\Services\ServiceContainer;
use vwo\Utils\NetworkUtil;
use vwo\Enums\EventEnum;
use vwo\Services\SettingsService;
use vwo\Utils\DebuggerServiceUtil;
use vwo\Enums\DebuggerCategoryEnum;
use vwo\Services\LoggerService;
use vwo\Packages\Logger\Enums\LogLevelEnum;
use vwo\Constants\Constants;

class GetFlag
{
    public function get(
        string $featureKey,
        ContextModel $context,
        ServiceContainer $serviceContainer,
        bool $isDebuggerUsed = false
    ) {
        $ruleEvaluationUtil = new RuleEvaluationUtil();
        $isEnabled = false;
        $rolloutVariationToReturn = null;
        $experimentVariationToReturn = null;
        $shouldCheckForExperimentsRules = false;
        $batchPayload = [];

        $passedRulesInformation = [];
        $evaluatedFeatureMap = [];
        $storageService = new StorageService();
        $ruleStatus = [];
        $batchPayload = [];

        $hooksService = $serviceContainer->getHooksService();
        $logManager = $serviceContainer->getLogManager();
        $loggerService = $serviceContainer->getLoggerService();

        // Get feature object from feature key
        $feature = FunctionUtil::getFeatureFromKey($serviceContainer->getSettings(), $featureKey);
        $decision = [
            'featureName' => $feature ? $feature->getName() : null,
            'featureId' => $feature ? $feature->getId() : null,
            'featureKey' => $feature ? $feature->getKey() : null,
            'userId' => $context ? $context->getId() : null,
            'api' => ApiEnum::GET_FLAG,
        ];

        // create debug event props
        $debugEventProps = [
            'an' => ApiEnum::GET_FLAG,
            'uuid' => $context ? $context->getUUID() : null,
            'fk' => $feature ? $feature->getKey() : null,
            'sId' => $context ? $context->getSessionId() : null,
        ];

        // Retrieve stored data
        $storedData = (new StorageDecorator())->getFeatureFromStorage(
            $featureKey,
            $context,
            $storageService,
            $serviceContainer
        );
        
    // Check if stored data has featureId and if feature still exists in settings
    if (isset($storedData['featureId']) && FunctionUtil::isFeatureIdPresentInSettings($serviceContainer->getSettings(), $storedData['featureId'])) {
        if (isset($storedData['experimentVariationId'])) {
            if (isset($storedData['experimentKey'])) {
                $variation = CampaignUtil::getVariationFromCampaignKey(
                    $serviceContainer->getSettings(),
                    $storedData['experimentKey'],
                    $storedData['experimentVariationId']
                );
                if ($variation) {
                    $logManager->info(sprintf(
                        "Variation %s found in storage for the user %s for the experiment: %s",
                        $variation->getKey(),
                        $context->getId(),
                        $storedData['experimentKey']
                    ));
                    return new GetFlagResultUtil(true, $variation->getVariables(), $ruleStatus, $context->getSessionId(), $context->getUUID());
                }
            }
        } elseif (isset($storedData['rolloutKey']) && isset($storedData['rolloutId'])) {
            $variation = CampaignUtil::getVariationFromCampaignKey(
                $serviceContainer->getSettings(),
                $storedData['rolloutKey'],
                $storedData['rolloutVariationId']
            );

            if ($variation) {
                $logManager->info(sprintf(
                    "Variation %s found in storage for the user %s for the rollout experiment: %s",
                    $variation->getKey(),
                    $context->getId(),
                    $storedData['rolloutKey']
                ));
                $logManager->debug(sprintf(
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
    }

        if (!DataTypeUtil::isObject($feature)) {
            $loggerService->error('FEATURE_NOT_FOUND', 
            array_merge([
                'featureKey' => $featureKey,
            ], $debugEventProps));

            return new GetFlagResultUtil(false, [], $ruleStatus, $context->getSessionId(), $context->getUUID());
        }

        // Set session ID if not present
        if ($context->getSessionId() === null) {
            $context->setSessionId(FunctionUtil::getCurrentUnixTimestamp());
        }

        $segmentationManager = $serviceContainer->getSegmentationManager();
        $segmentationManager->setContextualData($serviceContainer, $feature, $context);

        // Evaluate Rollout Rules
        $rollOutRules = FunctionUtil::getSpecificRulesBasedOnType($feature, CampaignTypeEnum::ROLLOUT);
        if (count($rollOutRules) > 0 && !$isEnabled) {
            $megGroupWinnerCampaigns = [];
            foreach ($rollOutRules as $rule) {
                $evaluateRuleResult = $ruleEvaluationUtil->evaluateRule(
                    $serviceContainer,
                    $feature,
                    $rule,
                    $context,
                    $evaluatedFeatureMap,
                    $megGroupWinnerCampaigns,
                    $storageService,
                    $decision,
                    $isDebuggerUsed
                );

                if ($evaluateRuleResult['preSegmentationResult']) {
                    $payload = $evaluateRuleResult['payload'];

                    if(!$isDebuggerUsed) {
                        if(($serviceContainer->getSettingsService()->isGatewayServiceProvided || $serviceContainer->getSettingsService()->isProxyUrlProvided) && $payload !== null) {
                            ImpressionUtil::SendImpressionForVariationShown($serviceContainer, $payload, $context, $featureKey);
                        } else {
                            if($payload !== null) {
                                $batchPayload[] = $payload;
                            }
                        }   
                    }

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
                $variation = DecisionUtil::evaluateTrafficAndGetVariation($serviceContainer, $passedRolloutCampaign, $context->getId());

                if (DataTypeUtil::isObject($variation) && !empty($variation)) {
                    $isEnabled = true;
                    $shouldCheckForExperimentsRules = true;
                    $rolloutVariationToReturn = $variation;

                    $this->updateIntegrationsDecisionObject($passedRolloutCampaign, $variation, $passedRulesInformation, $decision);
                    
                    if(!$isDebuggerUsed) {
                        //push this payload to the batch payload
                        $networkUtil = new NetworkUtil($serviceContainer);
                        $payload = $networkUtil->getTrackUserPayloadData(
                            $serviceContainer->getSettings(),
                            EventEnum::VWO_VARIATION_SHOWN,
                            $passedRolloutCampaign->getId(),
                            $variation->getId(),
                            $context
                        );

                        if(($serviceContainer->getSettingsService()->isGatewayServiceProvided || $serviceContainer->getSettingsService()->isProxyUrlProvided) && $payload !== null) {
                            ImpressionUtil::SendImpressionForVariationShown($serviceContainer, $payload, $context, $featureKey);
                        } else {
                            //push this payload to the batch payload
                            if($payload !== null) {
                                $batchPayload[] = $payload;
                            }
                        }
                    }
                }
            }
        } else if (count($rollOutRules) === 0) {
            $logManager->debug("No Rollout rules present for the feature. Hence, checking experiment rules");
            $shouldCheckForExperimentsRules = true;
        }

        // Evaluate Experiment Rules
        if ($shouldCheckForExperimentsRules) {
            $experimentRules = FunctionUtil::getAllExperimentRules($feature);
            $experimentRulesToEvaluate = [];

            $megGroupWinnerCampaigns = [];
            foreach ($experimentRules as $rule) {
                $evaluateRuleResult = $ruleEvaluationUtil->evaluateRule(
                    $serviceContainer,
                    $feature,
                    $rule,
                    $context,
                    $evaluatedFeatureMap,
                    $megGroupWinnerCampaigns,
                    $storageService,
                    $decision,
                    $isDebuggerUsed
                );

                if ($evaluateRuleResult['preSegmentationResult']) {
                    if ($evaluateRuleResult['whitelistedObject'] === null) {
                        $experimentRulesToEvaluate[] = $rule;
                    } else {
                        $isEnabled = true;
                        $payload = $evaluateRuleResult['payload'];
                        if(($serviceContainer->getSettingsService()->isGatewayServiceProvided || $serviceContainer->getSettingsService()->isProxyUrlProvided) && $payload !== null) {
                            ImpressionUtil::SendImpressionForVariationShown($serviceContainer, $payload, $context, $featureKey);
                        } else {
                            if($payload !== null) {
                                $batchPayload[] = $payload;
                            }
                        }
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
                $variation = DecisionUtil::evaluateTrafficAndGetVariation($serviceContainer, $campaign, $context->getId());

                if (DataTypeUtil::isObject($variation) && !empty($variation)) {
                    $isEnabled = true;
                    $experimentVariationToReturn = $variation;

                    $this->updateIntegrationsDecisionObject($campaign, $variation, $passedRulesInformation, $decision);

                    if(!$isDebuggerUsed) {
                         // Construct payload data for tracking the user
                         $networkUtil = new NetworkUtil($serviceContainer);
                         $payload = $networkUtil->getTrackUserPayloadData(
                             $serviceContainer->getSettings(),
                             EventEnum::VWO_VARIATION_SHOWN,
                             $campaign->getId(),
                             $variation->getId(),
                             $context
                         );

                        if(($serviceContainer->getSettingsService()->isGatewayServiceProvided || $serviceContainer->getSettingsService()->isProxyUrlProvided) && $payload !== null) {
                            ImpressionUtil::SendImpressionForVariationShown($serviceContainer, $payload, $context, $featureKey);
                        } else {
                            //push this payload to the batch payload
                            if($payload !== null) {
                                $batchPayload[] = $payload;
                            }
                        }
                    }
                }
            }
        }

        // If flag is enabled, store it in data
        if ($isEnabled) {
            (new StorageDecorator())->setDataInStorage(
                array_merge([
                    'featureKey' => $featureKey,
                    'featureId' => $feature->getId(),
                    'context' => $context
                ], $passedRulesInformation),
                $storageService,
                $serviceContainer
            );
        }

        // Call integration callback, if defined
        $hooksService->set($decision);
        $hooksService->execute($hooksService->get());

         // send debug event, if debugger is enabled
        if ($feature->getIsDebuggerEnabled()) {
            
            $debugEventProps['cg'] = DebuggerCategoryEnum::DECISION;
            // debugEventProps.msg_t = Constants.FLAG_DECISION;
            $debugEventProps['msg_t'] = Constants::FLAG_DECISION_GIVEN;

            $debugEventProps['lt'] = LogLevelEnum::INFO;

            // Update debug event props with decision keys
            $this->updateDebugEventPropsWithDecisionKeys($debugEventProps, $decision);

            // Send debug event
            DebuggerServiceUtil::sendDebugEventToVWO($debugEventProps);
       }

        // Send data for Impact Campaign, if defined
        if ($feature->getImpactCampaign()->getCampaignId()) {
            $status = $isEnabled ? 'enabled' : 'disabled';
            $logManager->info(sprintf(
                "Tracking feature: %s being %s for Impact Analysis Campaign for the user %s",
                $featureKey,
                $status,
                $context->getId()
            ));

            if(!$isDebuggerUsed) {
                // Construct payload data for tracking the user
                $networkUtil = new NetworkUtil($serviceContainer);
                $payload = $networkUtil->getTrackUserPayloadData(
                    $serviceContainer->getSettings(),
                    EventEnum::VWO_VARIATION_SHOWN,
                    $feature->getImpactCampaign()->getCampaignId(),
                    $isEnabled ? 2 : 1,
                    $context
                );

                if(($serviceContainer->getSettingsService()->isGatewayServiceProvided || $serviceContainer->getSettingsService()->isProxyUrlProvided) && $payload !== null) {
                    ImpressionUtil::SendImpressionForVariationShown($serviceContainer, $payload, $context, $featureKey);
                } else {
                    //push this payload to the batch payload
                    if($payload !== null) {
                        $batchPayload[] = $payload;
                    }
                }
            }
        }

        $variablesForEvaluatedFlag = [];

        if ($experimentVariationToReturn !== null) {
            $variablesForEvaluatedFlag = $experimentVariationToReturn->getVariables();
        } elseif ($rolloutVariationToReturn !== null) {
            $variablesForEvaluatedFlag = $rolloutVariationToReturn->getVariables();
        }
        
        if(!$serviceContainer->getSettingsService()->isGatewayServiceProvided && !$serviceContainer->getSettingsService()->isProxyUrlProvided) {
            ImpressionUtil::SendImpressionForVariationShownInBatch($batchPayload, $serviceContainer);
        }
    
        return new GetFlagResultUtil($isEnabled, $variablesForEvaluatedFlag, $ruleStatus, $context->getSessionId(), $context->getUUID());
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

    /**
     * Update debug event props with decision keys.
     *
     * @param array &$debugEventProps Debug event props (passed by reference)
     * @param array $decision Decision array
     * @return void
     */
    private function updateDebugEventPropsWithDecisionKeys(array &$debugEventProps, array $decision)
    {
        $decisionKeys = DebuggerServiceUtil::extractDecisionKeys($decision);
        $message = "Flag decision given for feature:{$decision['featureKey']}.";
        
        if (isset($decision['rolloutKey']) && isset($decision['rolloutVariationId'])) {
            $rolloutKeySuffix = substr($decision['rolloutKey'], strlen($decision['featureKey'] . '_'));
            $message .= " Got rollout:{$rolloutKeySuffix} with variation:{$decision['rolloutVariationId']}";
        }
        
        if (isset($decision['experimentKey']) && isset($decision['experimentVariationId'])) {
            $experimentKeySuffix = substr($decision['experimentKey'], strlen($decision['featureKey'] . '_'));
            $message .= " and experiment:{$experimentKeySuffix} with variation:{$decision['experimentVariationId']}";
        }
        
        $debugEventProps['msg'] = $message;
        $debugEventProps = array_merge($debugEventProps, $decisionKeys);
    }
}
