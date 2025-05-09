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

namespace vwo\Enums;

class UrlEnum {
    const BASE_URL = 'dev.visualwebsiteoptimizer.com';
    const SETTINGS_URL = '/server-side/settings';
    const WEBHOOK_SETTINGS_URL = '/server-side/pull';
    const TRACK_USER = '/server-side/track-user';
    const TRACK_GOAL = '/server-side/track-goal';
    const PUSH = '/server-side/push';
    const BATCH_EVENTS = '/server-side/batch-events';
    const EVENTS = '/events/t';
    const ATTRIBUTE_CHECK = '/check-attribute';
    const GET_USER_DATA = '/get-user-details';
}
?>