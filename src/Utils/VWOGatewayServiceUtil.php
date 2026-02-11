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

use vwo\Packages\NetworkLayer\Manager\NetworkManager;
use vwo\Packages\NetworkLayer\Models\RequestModel;
use vwo\Packages\NetworkLayer\Models\ResponseModel;
use vwo\Enums\UrlEnum;
use vwo\Services\UrlService;
use vwo\Packages\Logger\Core\LogManager;

class VWOGatewayServiceUtil {
    public static function getFromVWOGatewayService($queryParams, $endpoint, $serviceContainer = null) {

        $networkInstance = $serviceContainer ? $serviceContainer->getNetworkManager() : NetworkManager::Instance();

        if ($serviceContainer->getSettingsService()->hostname === UrlEnum::BASE_URL) {
            $serviceContainer->getLogManager()->info('Invalid URL. Please provide a valid URL for vwo helper VWOGatewayService');
            return false;
        }

        try {
            $request = new RequestModel(
                $serviceContainer->getSettingsService()->hostname,
                'GET',
                UrlService::getEndpointWithCollectionPrefix($endpoint),
                $queryParams,
                null,
                null,
                $serviceContainer->getSettingsService()->protocol,
                $serviceContainer->getSettingsService()->port
            );

            $response = $networkInstance->get($request);

            if ($response instanceof ResponseModel) {
                return $response->getData();
            } else {
                $serviceContainer->getLogManager()->error('Failed to get a valid response from the network request.');
                return false;
            }
        } catch (\Exception $err) {
            $serviceContainer->getLogManager()->error('Error occurred while sending GET request: ' . $err->getMessage());
            return false;
        }
    }

    public static function getQueryParamForLocationPreSegment($ipAddress) {
        return [
            'ipAddress' => $ipAddress
        ];
    }

    public static function getQueryParamForUaParser($userAgent) {
        return [
            'userAgent' => urlencode($userAgent)
        ];
    }
}
