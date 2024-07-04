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

use vwo\Decorators\StorageDecorator as StorageDecorator;
use vwo\Models\CampaignModel as CampaignModel;
use vwo\Utils\FunctionUtil as FunctionUtil;
use vwo\Utils\DecisionUtil as DecisionUtil;
use vwo\Utils\DataTypeUtil as DataTypeUtil;
use vwo\Enums\CampaignTypeEnum;
use vwo\Enums\EventEnum;
use vwo\Enums\ApiEnum;
use vwo\Utils\NetworkUtil as NetworkUtil;
use vwo\Packages\Logger\Core\LogManager as LogManager;
use vwo\Services\StorageService;
use vwo\Utils\CampaignUtil as CampaignUtil;
use vwo\Utils\MegUtil as MegUtil;
use vwo\Utils\GetFlagResultUtil;


interface IGetFlag
{
    public function get($featureKey, $settings, $context, $hookManager, $settingsFilePassedInOptions = false);
}

class GetFlag implements IGetFlag
{
    public function get($featureKey, $settings, $context, $hookManager, $settingsFilePassedInOptions = false)
    {
        // initialize contextUtil object
        $decision = $this->createDecision($settings, $featureKey, $context);
        $isEnabled = false;
        $rolloutVariationToReturn = null;
        $experimentVariationToReturn = null;
        $rulesInformation = []; // for storing and integration callback
        $evaluatedFeatureMap = array();
        $shouldCheckForAbPersonalise = false;
        $ruleStatus = [];

        $storageService = new StorageService();
        $storedData = (new StorageDecorator())->getFeatureFromStorage(
            $featureKey,
            $context['user'],
            $storageService
        );

        if (isset($storedData['experimentVariationId'])) {
            if (isset($storedData['experimentKey'])) {
                $variation = CampaignUtil::getCampaignVariation(
                    $settings,
                    $storedData['experimentKey'],
                    $storedData['experimentVariationId']
                );

                if ($variation !== null) {
                    LogManager::instance()->info(
                        "Variation {$variation->getKey()} found in storage for the user {$context['user']['id']} for the experiment campaign {$storedData['experimentKey']}"
                    );
                    return new GetFlagResultUtil(
                        true,
                        $variation->getVariables(),
                        $ruleStatus
                    );
                }
            }
        } elseif (isset($storedData['rolloutKey']) && isset($storedData['rolloutId'])) {
            $variation = CampaignUtil::getRolloutVariation(
                $settings,
                $storedData['rolloutKey'],
                $storedData['rolloutVariationId']
            );
            if ($variation !== null) {
                LogManager::instance()->info(
                    "Variation {$variation->getKey()} found in storage for the user {$context['user']['id']} for the rollout campaign {$storedData['rolloutKey']}"
                );
                LogManager::instance()->info("Evaluation experiement campaigns now for the user {$context['user']['id']}");
                $isEnabled = true;
                $rolloutVariationToReturn = $variation;
                $featureInfo = [
                    'rolloutId' => $storedData['rolloutId'],
                    'rolloutKey' => $storedData['rolloutKey'],
                    'rolloutVariationId' => $storedData['rolloutVariationId']
                ];
                $rulesInformation = array_merge($rulesInformation, $featureInfo);
            }
        }

        $ruleToTrack = [];


        $feature = FunctionUtil::getFeatureFromKey($settings, $featureKey);
        if (!is_object($feature) || $feature === null) {
            LogManager::instance()->info("Feature not found for the key {$featureKey}");
            return new GetFlagResultUtil(false, [], $ruleStatus);
        }

        $rollOutRules = FunctionUtil::getSpecificRulesBasedOnType($settings, $featureKey, CampaignTypeEnum::ROLLOUT);

        if (count($rollOutRules) > 0 && !$isEnabled) {
            foreach ($rollOutRules as $rule) {
                
                $evaluateRuleResult = $this->evaluateRule($settings, $feature, $rule, $context, false, $decision, $settingsFilePassedInOptions);
                if ($evaluateRuleResult[0]) {
                    $ruleToTrack[] = $rule;
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
                continue;
            }
        } elseif (count($rollOutRules) === 0) {
            LogManager::instance()->info('No Rollout rules present for the feature, checking rules for AB/Personalize');
            $shouldCheckForAbPersonalise = true;
        }

        if (count($ruleToTrack) > 0) {
            $ruleElement = array_pop($ruleToTrack);
            $campaign = $ruleElement;
            $variation = $this->trafficCheckAndReturnVariation($settings, $feature, $campaign, $context, $rulesInformation, $decision, $settingsFilePassedInOptions);

            if (DataTypeUtil::isObject($variation)) {
                $isEnabled = true;
                $shouldCheckForAbPersonalise = true;
                $rolloutVariationToReturn = $variation;
            }
            $ruleToTrack = [];
        }

        if ($shouldCheckForAbPersonalise) {

            $allRules = FunctionUtil::getAllAbAndPersonaliseRules($settings, $featureKey);
            $listOfMegCampaignsGroups = [];
            $campaignToSkip = [];
            $ruleIndex = 0;

            foreach ($allRules as $rule) {
                $ruleIndex++;
                $group = CampaignUtil::isPartOfGroup($settings, $rule->getId());

                if (is_array($group) && count($group) > 0) {
                    if (!in_array($group['groupId'], $listOfMegCampaignsGroups)) {
                        $listOfMegCampaignsGroups[] = $group['groupId'];
                    }

                    if ($ruleIndex === count($allRules)) {
                        LogManager::instance()->debug("Evaluating MEG campaigns for the user {$context['user']['id']}");

                        [$megResult, $whitelistedVariationInfoWithCampaign, $winnerCampaign] = MegUtil::evaluateGroups(
                            $settings,
                            $featureKey,
                            $feature,
                            $listOfMegCampaignsGroups,
                            $evaluatedFeatureMap,
                            $context,
                            $storageService,
                            $campaignToSkip,
                            $decision
                        );


                        if ($megResult) {
                            if ($winnerCampaign !== null) {

                                $winnerCampaignToPush = null;

                                // Iterate over allRules to find the matching campaign
                                foreach ($allRules as $rule) {
                                    if ($rule->getId() === $winnerCampaign->getId()) {
                                        $winnerCampaignToPush = $rule;
                                        break; // Exit the loop once the matching campaign is found
                                    }
                                }

                                // If a matching campaign was found, add it to ruleToTrack
                                if ($winnerCampaignToPush !== null) {
                                    $ruleToTrack[] = $winnerCampaignToPush;
                                }
                            } else {
                                $isEnabled = true;
                                $experimentVariationToReturn = $whitelistedVariationInfoWithCampaign->variation;
                                $rulesInformation = array_merge($rulesInformation, [
                                    'experimentId' => $whitelistedVariationInfoWithCampaign->experimentId,
                                    'experimentKey' => $whitelistedVariationInfoWithCampaign->experimentKey,
                                    'experimentVariationId' => $whitelistedVariationInfoWithCampaign->variationId
                                ]);
                            }
                        }
                        break;
                    }
                    continue;
                } elseif (count($listOfMegCampaignsGroups) > 0) {
                    LogManager::instance()->debug("Evaluating MEG campaigns for the user {$context['user']['id']}");

                    [$megResult, $whitelistedVariationInfoWithCampaign, $winnerCampaign] = MegUtil::evaluateGroups(
                        $settings,
                        $featureKey,
                        $feature,
                        $listOfMegCampaignsGroups,
                        $evaluatedFeatureMap,
                        $context,
                        $storageService,
                        $campaignToSkip,
                        $decision
                    );

                    if ($megResult) {
                        if ($winnerCampaign !== null) {

                            $winnerCampaignToPush = null;

                            // Iterate over allRules to find the matching campaign
                            foreach ($allRules as $rule) {
                                if ($rule->getId() === $winnerCampaign->getId()) {
                                    $winnerCampaignToPush = $rule;
                                    break; // Exit the loop once the matching campaign is found
                                }
                            }

                            // If a matching campaign was found, add it to ruleToTrack
                            if ($winnerCampaignToPush !== null) {
                                $ruleToTrack[] = $winnerCampaignToPush;
                            }
                        } else {
                            $isEnabled = true;
                            $experimentVariationToReturn = $whitelistedVariationInfoWithCampaign->variation;
                            $rulesInformation = array_merge($rulesInformation, [
                                'experimentId' => $whitelistedVariationInfoWithCampaign->experimentId,
                                'experimentKey' => $whitelistedVariationInfoWithCampaign->experimentKey,
                                'experimentVariationId' => $whitelistedVariationInfoWithCampaign->variationId
                            ]);
                        }
                        break;
                    }
                    $campaignToSkip[] = $rule->getId();
                }

                [$abPersonalizeResult, $whitelistedVariation] = $this->evaluateRule(
                    $settings,
                    $feature,
                    $rule,
                    $context,
                    false,
                    $decision,
                    $settingsFilePassedInOptions
                );

                if ($abPersonalizeResult) {
                    if ($whitelistedVariation === null) {
                        $ruleToTrack[] = $rule;
                    } else {
                        $isEnabled = true;
                        $experimentVariationToReturn = $whitelistedVariation['variation'];
                        $rulesInformation = array_merge($rulesInformation, [
                            'experimentId' => $rule->getId(),
                            'experimentKey' => $rule->getKey(),
                            'experimentVariationId' => $whitelistedVariation['variationId']
                        ]);
                    }
                    $ruleStatus[$rule->getRuleKey()] = "Passed";
                    break;
                } else {
                    $ruleStatus[$rule->getRuleKey()] = "Failed";
                }
                $campaignToSkip[] = $rule->getId();
                continue;
            }
        }

        if (count($ruleToTrack) > 0) {
            $ruleElement = array_pop($ruleToTrack);
            $campaign = $ruleElement;
            $variation = $this->trafficCheckAndReturnVariation($settings, $feature, $campaign, $context, $rulesInformation, $decision, $settingsFilePassedInOptions);

            if ($variation !== null) {
                $isEnabled = true;
                $experimentVariationToReturn = $variation;
            }
        }

        if ($isEnabled) {
            (new StorageDecorator())->setDataInStorage(array_merge([
                'featureKey' => $featureKey,
                'user' => $context['user']
            ], $rulesInformation), $storageService);
            $hookManager->set($decision);
            $hookManager->execute($hookManager->get());
        }

        if ($feature->getImpactCampaign()->getcampaignId() && !$settingsFilePassedInOptions) {
            $campaign = new \vwo\Models\CampaignModel();
            $campaign->setId($feature->getImpactCampaign()->getCampaignId());

            $variation = new \vwo\Models\VariationModel();
            $variation->setId($isEnabled ? 2 : 1);  

            $this->createImpressionForVariationShown(
                $settings,
                $feature,
                $campaign,
                $context['user'],
                $variation,
                true
            );
        }

        $variables = [];
        if ($experimentVariationToReturn !== null) {
            $variables = $experimentVariationToReturn->getVariables() ?? null;
        } elseif ($rolloutVariationToReturn !== null) {
            $variables = $rolloutVariationToReturn->getVariables() ?? null;
        }

        return new GetFlagResultUtil($isEnabled, $variables, $ruleStatus);
    }

    private function createDecision($settings, $featureKey, $context)
    {
        return [
            'featureName' => FunctionUtil::getFeatureNameFromKey($settings, $featureKey),
            'featureId' => FunctionUtil::getFeatureIdFromKey($settings, $featureKey),
            'featureKey' => $featureKey,
            'userId' => $context['user']['id'],
            'api' => ApiEnum::GET_FLAG
        ];
    }

    private function trafficCheckAndReturnVariation($settings, $feature, $campaign, $context, &$rulesInformation, &$decision, $settingsFilePassedInOptions)
    {
        $variation = DecisionUtil::evaluateTrafficAndGetVariation($settings, $campaign, $context['user']['id']);

        if (DataTypeUtil::isObject($variation) && method_exists($variation, 'getId')) {
            $campaignType = $campaign->getType();
            if ($campaignType === CampaignTypeEnum::ROLLOUT) {
                $rulesInformation = array_merge($rulesInformation, [
                    'rolloutId' => $campaign->getId(),
                    'rolloutKey' => $campaign->getKey(),
                    'rolloutVariationId' => $variation->getId()
                ]);
            } else {
                $rulesInformation = array_merge($rulesInformation, [
                    'experimentId' => $campaign->getId(),
                    'experimentKey' => $campaign->getKey(),
                    'experimentVariationId' => $variation->getId()
                ]);
            }

            // Merge rulesInformation into decision
            $decision = array_merge($decision, $rulesInformation);

            // Assuming createImpressionForVariationShown() accepts objects for $campaign and $variation
            if (!$settingsFilePassedInOptions)
                $this->createImpressionForVariationShown($settings, $feature, $campaign, $context['user'], $variation);

            return $variation;
        }

        return null;
    }

    /**
     * Evaluate the rule
     * @param rule    rule to evaluate
     * @param user    user object
     * @returns
     */
    public function evaluateRule($settings, $feature, $campaign, $context, $isMegWinnerRule, &$decision, $settingsFilePassedInOptions = false)
    {
        // check for whitelisting and pre segmentation
        $result = DecisionUtil::checkWhitelistingAndPreSeg(
            $settings,
            $campaign,
            $context,
            $isMegWinnerRule,
            $decision
        );

        $preSegmentationResult = $result[0];
        $whitelistedObject = $result[1];
        
        if ($preSegmentationResult && is_object($whitelistedObject) && count(get_object_vars($whitelistedObject)) > 0) {
            $decision = array_merge($decision, [
                'experimentId' => $campaign->getId(),
                'experimentKey' => $campaign->getKey(),
                'experimentVariationId' => $whitelistedObject->variationId,
            ]);
            if (!$settingsFilePassedInOptions)
                $this->createImpressionForVariationShown($settings, $feature, $campaign, $context['user'], $whitelistedObject['variation']);
        }
        return [$preSegmentationResult, $whitelistedObject];
    }

    function createImpressionForVariationShown($settings, $feature, $campaign, $user, $variation, $isImpactCampaign = false, $settingsFilePassedInOptions = false)
    {
        if (isset($user['userAgent'])) {
            $userAgent = $user['userAgent'];
        } else {
            $userAgent = '';
        }

        if (isset($user['ipAddress'])) {
            $userIpAddress = $user['ipAddress'];
        } else {
            $userIpAddress = '';
        }
        $networkUtil = new NetworkUtil();
        $properties = $networkUtil->getEventsBaseProperties(
            $settings,
            EventEnum::VWO_VARIATION_SHOWN,
            urlencode($userAgent),
            $userIpAddress
        );
        $payload = $networkUtil->getTrackUserPayloadData(
            $settings,
            $user['id'],
            EventEnum::VWO_VARIATION_SHOWN,
            $campaign->getId(),
            $variation->getId(),
            $userAgent,
            $userIpAddress
        );
        $networkUtil->sendPostApiRequest($properties, $payload);
    }
}
