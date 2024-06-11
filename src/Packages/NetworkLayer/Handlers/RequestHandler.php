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

namespace vwo\Packages\NetworkLayer\Handlers;

use vwo\Packages\NetworkLayer\Models\RequestModel;

class RequestHandler {
    public function createRequest($request, $config): RequestModel {
        if (($config->getBaseUrl() === null || $config->getBaseUrl() === '') &&
            ($request->getUrl() === null || $request->getUrl() === '')) {
            return null;
        }

        $request->setUrl($request->getUrl() ?: $config->getBaseUrl());
        $request->setTimeout($request->getTimeout() ?: $config->getTimeout());
        $request->setBody($request->getBody() ?: $config->getBody());
        $request->setHeaders($request->getHeaders() ?: $config->getHeaders());

        $requestQueryParams = $request->getQuery() ?: [];
        $configQueryParams = $config->getQuery() ?: [];

        foreach ($configQueryParams as $queryKey => $queryValue) {
            if (!array_key_exists($queryKey, $requestQueryParams)) {
                $requestQueryParams[$queryKey] = $queryValue;
            }
        }

        $request->setQuery($requestQueryParams);

        return $request;
    }
}

?>
