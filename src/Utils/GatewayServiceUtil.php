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

use vwo\Enums\CampaignTypeEnum;
use vwo\Enums\ErrorLogMessagesEnum;
use vwo\Models\SettingsModel;
use vwo\Packages\NetworkLayer\Manager\NetworkManager;
use vwo\Packages\NetworkLayer\Models\RequestModel;
use vwo\Packages\NetworkLayer\Models\ResponseModel;
use vwo\Services\SettingsService;
use vwo\Services\UrlService;
use vwo\Packages\Logger\Core\LogManager;

class GatewayServiceUtil {

    /**
     * Retrieves data from the VWO Gateway Service.
     * @param array $queryParams The parameters to be used in the query string of the request.
     * @param string $endpoint The endpoint URL to which the request is sent.
     * @return mixed The response data or false if an error occurs.
     */
    public static function getFromGatewayService($queryParams, $endpoint) {
        $networkInstance = NetworkManager::instance();

        // Check if the base URL is correctly set
        if (!SettingsService::instance()->isGatewayServiceProvided) {
            LogManager::instance()->error('Invalid URL. Please provide a valid URL for vwo helper gatewayService');
            return false;
        }

        try {
            $request = new RequestModel(
                UrlService::getBaseUrl(),
                'GET',
                $endpoint,
                $queryParams,
                null,
                null,
                SettingsService::instance()->protocol,
                SettingsService::instance()->port
            );

            $response = $networkInstance->get($request);

            if ($response instanceof ResponseModel) {
                return $response->getData();
            } else {
                LogManager::instance()->error('Failed to get a valid response from the network request.');
                return false;
            }
        } catch (\Exception $err) {
            LogManager::instance()->error('Error occurred while sending GET request: ' . $err->getMessage());
            return false;
        }
    }

    /**
     * Encodes the query parameters to ensure they are URL-safe.
     * @param array $queryParams The query parameters to be encoded.
     * @return array An array containing the encoded query parameters.
     */
    public static function getQueryParams($queryParams) {
        $encodedParams = [];

        foreach ($queryParams as $key => $value) {
            $encodedParams[$key] = urlencode((string)$value);
        }

        return $encodedParams;
    }

    /**
     * Adds the isGatewayServiceRequired flag to each feature in the settings based on pre-segmentation.
     * @param SettingsModel $settings The settings file to modify.
     */
    public static function addIsGatewayServiceRequiredFlag($settings) {
    
        // Regular expression pattern to identify relevant segments (without lookbehind)
        $pattern = '/\b(country|region|city|os|device_type|browser_string|ua)\b|inlist\([^)]*\)/';
    
        foreach ($settings->getFeatures() as $feature) {
            $rules = $feature->getRulesLinkedCampaign();
    
            foreach ($rules as $rule) {
                $segments = [];
    
                if ($rule->getType() === CampaignTypeEnum::PERSONALIZE || $rule->getType() === CampaignTypeEnum::ROLLOUT) {
                    $segments = $rule->getVariations()[0]->getSegments();
                } else {
                    $segments = $rule->getSegments();
                }
    
                if ($segments) {
                    // Convert segments to JSON
                    $jsonSegments = json_encode($segments);
    
                    $matches = [];
                    preg_match_all($pattern, $jsonSegments, $matches);

                    if (!empty($matches[0])) {
                        $feature->setIsGatewayServiceRequired(true);
                        break;
                    }
                }
            }
        }
    }     
}
