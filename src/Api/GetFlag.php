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
use vwo\Models\FeatureModel as FeatureModel;
use vwo\Models\SettingsModel as SettingsModel;
use vwo\Models\CampaignModel as CampaignModel;
use vwo\Utils\FunctionUtil as FunctionUtil;
use vwo\Utils\DecisionUtil as DecisionUtil;
use vwo\Utils\DataTypeUtil as DataTypeUtil;
use vwo\Enums\CampaignTypeEnum;
use vwo\Enums\EventEnum;
use vwo\Enums\ApiEnum;
use vwo\Utils\NetworkUtil as NetworkUtil;
use vwo\Packages\Logger\Core\LogManager as LogManager;
use vwo\Services\HooksManager as HookManager;
use vwo\Services\StorageService;
use vwo\Utils\CampaignUtil as CampaignUtil;
use vwo\Models\VariationModel as VariationModel;
use vwo\Utils\MegUtil as MegUtil;


interface IGetFlag
{
    public function get($featureKey, $settings, $context, $hookManager);
}

class GetFlag implements IGetFlag
{
    public function get($featureKey, $settings, $context, $hookManager)
    {
        // initialize contextUtil object
        $decision = $this->createDecision($settings, $featureKey, $context);
        $isEnabled = false;
        $rolloutVariationToReturn = null;
        $experimentVariationToReturn = null;
        $rulesInformation = []; // for storing and integration callback
        $evaluatedFeatureMap = array();
        $shouldCheckForAbPersonalise = false;


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
                    return [
                        'isEnabled' => true,
                        'getVariables' => function () use ($variation) {
                            return $variation->getVariables();
                        },
                        'getVariable' => function ($key, $defaultValue) use ($variation) {
                            foreach ($variation->getVariables() as $variable) {
                                if ($variable->getKey() === $key) {
                                    return $variable->getValue();
                                }
                            }
                            return $defaultValue;
                        }
                    ];
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
            return [
                'isEnabled' => false,
                'getVariables' => function () {
                    return [];
                },
                'getVariable' => function ($key, $defaultValue) {
                    return $defaultValue;
                }
            ];
        }

        $rollOutRules = FunctionUtil::getSpecificRulesBasedOnType($settings, $featureKey, CampaignTypeEnum::ROLLOUT);

        if (count($rollOutRules) > 0 && !$isEnabled) {
            foreach ($rollOutRules as $rule) {
                $rule = FunctionUtil::convertObjectToArray($rule);
                $evaluateRuleResult = $this->evaluateRule($settings, $feature, $rule, $context, false, $decision);
                if ($evaluateRuleResult[0]) {
                    $ruleToTrack[] = $rule;
                    $evaluatedFeatureMap[$featureKey] = [
                        'rolloutId' => $rule['id'],
                        'rolloutKey' => $rule['key'],
                        'rolloutVariationId' => $rule['variations'][0]['id']
                    ];
                    break;
                }
                
                continue;
            }
        } elseif (count($rollOutRules) === 0) {
            LogManager::instance()->info('No Rollout rules present for the feature, checking rules for AB/Personalize');
            $shouldCheckForAbPersonalise = true;
        }
        if (count($ruleToTrack) > 0) {
            $ruleElement = array_pop($ruleToTrack);
            $campaign = new CampaignModel();
            $campaign->modelFromDictionary($ruleElement);
            $variation = $this->trafficCheckAndReturnVariation($settings, $feature, $campaign, $context, $rulesInformation, $decision);
            if ($variation !== null && count((array)$variation) > 0) {
                $isEnabled = true;
                $shouldCheckForAbPersonalise = true;
                $rolloutVariationToReturn = $variation;
            }
            $ruleToTrack = [];
        }

        if ($shouldCheckForAbPersonalise) {
            $allRules = FunctionUtil::getAllAbAndPersonaliseRules($settings, $featureKey);
            $allRules = FunctionUtil::convertObjectToArray($allRules);
            $listOfMegCampaignsGroups = [];
            $campaignToSkip = [];
            $ruleIndex = 0;
            foreach ($allRules as $rule) {
                $ruleIndex++;
                $group = CampaignUtil::isPartOfGroup($settings, $rule['id']);
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
                                $winnerCampaignToPush = array_filter($allRules, function ($r) use ($winnerCampaign) {
                                    return $r['id'] === $winnerCampaign['id'];
                                });
                                $ruleToTrack[] = $winnerCampaignToPush;
                            } else {
                                $isEnabled = true;
                                $experimentVariationToReturn = $whitelistedVariationInfoWithCampaign['variation'];
                                $rulesInformation = array_merge($rulesInformation, [
                                    'experimentId' => $whitelistedVariationInfoWithCampaign['experimentId'],
                                    'experimentKey' => $whitelistedVariationInfoWithCampaign['experimentKey'],
                                    'experimentVariationId' => $whitelistedVariationInfoWithCampaign['variationId']
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
                        $winnerCampaign = FunctionUtil::convertObjectToArray($winnerCampaign);
                        if ($winnerCampaign !== null) {
                            $winnerCampaignToPush = array_filter($allRules, function ($r) use ($winnerCampaign) {
                                return $r['id'] === $winnerCampaign['id'];
                            });
                            $ruleToTrack[] = $winnerCampaignToPush;
                        } else {
                            $isEnabled = true;
                            $experimentVariationToReturn = $whitelistedVariationInfoWithCampaign['variation'];
                            $rulesInformation = array_merge($rulesInformation, [
                                'experimentId' => $whitelistedVariationInfoWithCampaign['experimentId'],
                                'experimentKey' => $whitelistedVariationInfoWithCampaign['experimentKey'],
                                'experimentVariationId' => $whitelistedVariationInfoWithCampaign['variationId']
                            ]);
                        }
                        break;
                    }
                    $campaignToSkip[] = $rule['id'];
                }

                [$abPersonalizeResult, $whitelistedVariation] = $this->evaluateRule(
                    $settings,
                    $feature,
                    $rule,
                    $context,
                    false,
                    $decision
                );
    
                if ($abPersonalizeResult) {
                    if ($whitelistedVariation === null) {
                        $ruleToTrack[] = $rule;
                    } else {
                        $isEnabled = true;
                        $experimentVariationToReturn = $whitelistedVariation['variation'];
                        $rulesInformation = array_merge($rulesInformation, [
                            'experimentId' => $rule['id'],
                            'experimentKey' => $rule['key'],
                            'experimentVariationId' => $whitelistedVariation['variationId']
                        ]);
                    }
                    break;
                }
                $campaignToSkip[] = $rule['id'];
                continue;
            }
        }

        if (count($ruleToTrack) > 0) {
            $ruleElement = array_pop($ruleToTrack);
            if (is_array($ruleElement) && is_array(reset($ruleElement))) {
                $ruleElement = array_pop($ruleElement);
            }
            $campaign = new CampaignModel();
            $campaign->modelFromDictionary($ruleElement);
            $variation = $this->trafficCheckAndReturnVariation($settings, $feature, $campaign, $context, $rulesInformation, $decision);
            if ($variation !== null && count((array)$variation) > 0) {
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

        if ($feature->getImpactCampaign()->getcampaignId()) {
            $this->createImpressionForVariationShown(
                $settings,
                $feature,
                ['id' => $feature->impactCampaign->campaignId],
                $context['user'],
                ['id' => $isEnabled ? 2 : 1],
                true
            );
        }

        $experimentVariationToReturn = FunctionUtil::convertObjectToArray($experimentVariationToReturn);
        $rolloutVariationToReturn = FunctionUtil::convertObjectToArray($rolloutVariationToReturn);


        return [
            'isEnabled' => $isEnabled,
            'getVariables' => function () use ($experimentVariationToReturn, $rolloutVariationToReturn) {
                $variables = null;
                if ($experimentVariationToReturn !== null) {
                    $variables = $experimentVariationToReturn['variables'] ?? null;
                } elseif ($rolloutVariationToReturn !== null) {
                    $variables = $rolloutVariationToReturn['variables'] ?? null;
                }
                return $variables;
            },
            'getVariable' => function ($key, $defaultValue) use ($experimentVariationToReturn, $rolloutVariationToReturn) {
                $variables = null;
                if ($experimentVariationToReturn !== null) {
                    $variables = $experimentVariationToReturn['variables'] ?? null;
                } elseif ($rolloutVariationToReturn !== null) {
                    $variables = $rolloutVariationToReturn['variables'] ?? null;
                }
                if ($variables !== null) {
                    foreach ($variables as $variable) {
                        if ($variable['key'] === $key) {
                            return $variable['value'];
                        }
                    }
                }
                return $defaultValue;
            }
        ];
        
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

    private function trafficCheckAndReturnVariation($settings, $feature, $campaign, $context, &$rulesInformation, &$decision)
    {
        $variation = DecisionUtil::evaluateTrafficAndGetVariation($settings, $campaign, $context['user']['id']);
        $campaign = FunctionUtil::convertObjectToArray($campaign);
        if (DataTypeUtil::isObject($variation) && count((array)$variation) > 0) {
            if ($campaign['type'] === CampaignTypeEnum::ROLLOUT) {
                $rulesInformation = array_merge($rulesInformation, [
                    'rolloutId' => $campaign['id'],
                    'rolloutKey' => $campaign['key'],
                    'rolloutVariationId' => $variation['id']
                ]);
            } else {
                $rulesInformation = array_merge($rulesInformation, [
                    'experimentId' => $campaign['id'],
                    'experimentKey' => $campaign['key'],
                    'experimentVariationId' => $variation['id']
                ]);
            }
            $decision = array_merge($decision, $rulesInformation);
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
    public function evaluateRule($settings, $feature, $rule, $context, $isMegWinnerRule, &$decision)
    {
        // evaluate the dsl
        $campaign = new CampaignModel();
        $campaign->modelFromDictionary($rule);
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
        $campaign = FunctionUtil::convertObjectToArray($campaign);
        // if pre segmentation result is true and whitelisted object is present, then send post call
        if ($preSegmentationResult && count((array)$whitelistedObject) > 0) {
            $decision = array_merge($decision, [
                'experimentId' => $campaign['id'],
                'experimentKey' => $campaign['key'],
                'experimentVariationId' => $whitelistedObject['variationId'],
            ]);
            $this->createImpressionForVariationShown($settings, $feature, $campaign, $context['user'], $whitelistedObject['variation']);
        }
        return [$preSegmentationResult, $whitelistedObject];
    }

    function createImpressionForVariationShown($settings, $feature, $campaign, $user, $variation, $isImpactCampaign = false)
    {
        if(isset($user['userAgent'])){
            $userAgent = $user['userAgent'];
        } else {
            $userAgent = '';
        }

        if(isset($user['ipAddress'])){
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
        $campaign = FunctionUtil::convertObjectToArray($campaign);
        $variation = FunctionUtil::convertObjectToArray($variation);
        $payload = $networkUtil->getTrackUserPayloadData(
            $settings,
            $user['id'],
            EventEnum::VWO_VARIATION_SHOWN,
            $campaign['id'],
            $variation['id'],
            $userAgent,
            $userIpAddress
        );
        $networkUtil->sendPostApiRequest($properties, $payload);
    }
}
