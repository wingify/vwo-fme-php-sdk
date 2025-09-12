# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.11.1] - 2025-09-12

### Fixed

- Implemented schema validation fixes and improvements

## [1.11.0] - 2025-09-02

### Added

- Post-segmentation variables are now automatically included as unregistered attributes, enabling post-segmentation without requiring manual setup.
- Added support for built-in targeting conditions, including browser version, OS version, and IP address, with advanced operator support (greaterThan, lessThan, regex).

## [1.10.0] - 2025-09-02

### Added

- Sends usage statistics to VWO servers automatically during SDK initialization

## [1.9.1] - 2025-08-06

### Fixed

- Fixed compatibility for PHP 7.0 version.

## [1.9.0] - 2025-08-05

### Added

- Added support for sending a one-time initialization event to the server to verify correct SDK setup.
- Added support for sending error logs to VWO server for better debugging.

## [1.8.1] - 2025-07-24

### Added

- Send the SDK name and version in the settings call to VWO as query parameters.


## [1.8.0] - 2025-07-01

### Changed

- Replaced `GuzzleHttp` with native PHP socket implementation for improved network performance.
- Achieved 90% reduction in `getFlag` API response time through optimized socket-based impression calls.

## [1.7.6] - 2025-05-28

### Added

- Support for VWO Internal Debugger

## [1.7.5] - 2025-05-13

### Added

- Added a feature to track and collect usage statistics related to various SDK features and configurations which can be useful for analytics, and gathering insights into how different features are being utilized by end users.

## [1.7.2] - 2025-05-06

### Fixed

- Update option name for passing `settings` while initializing the SDK.

## [1.7.1] - 2025-04-25

### Fixed

- Fixed the base URL issue for `EU` and `Asia` regions.

## [1.7.0] - 2024-04-14

### Added

- Lowered minimum PHP version requirement from 7.4 to 7.0

## [1.6.0] - 2024-04-09

### Added

- Support for `Map` in `setAttribute` method to send multiple attributes data.

## [1.5.0] - 2025-03-24

### Fixed

- Fixed the issue where the base URL was not being set correctly for `EU` and `Asia` regions.

## [1.4.0] - 2024-12-20

### Added

- Added the support for using salt for bucketing if provided in the rule configuration.


## [1.3.1] - 2024-10-04

### Fixed

- Improved Pre-segmentation result comparison logic to handle numeric values more accurately by trimming trailing zeroes and decimal points across various operand types


## [1.3.0] - 2024-10-03

### Added

- **Personalize MEG** - Add support for Personalize rules within `Mutually Exclusive Groups`

- **Segmentation module**

  - Modify how context and settings are being used inside modular segmentor code
  - Cache location / User-Agent data per `getFlag` API
  - Single endpoint for location and User-Agent at gateway-service so that at max one call will be required to fetch data from gateway service
  - If userAgent and ipAddress both are null, then no need to send call to gatewayService
  - Add support for DSL where featureIdValue could be `off`

- **Context refactoring** - context is now flattened out

    ```php
    [
        'id' => $userId,
        'ipAddress' => '',
        'userAgent' => '',
        // For pre-segmentation in campaigns
        'customVariables' => [ 'variable' => 'value' ]
    ]
    ```

- **Code refactoring** - source code is refactored to have error-handling, model-driven code, and inline documentation.

## [1.2.5] - 2024-07-29

### Fixed

- Removed unnecessary vendor/autoload from `UuidUtil.php`

## [1.2.1] - 2024-07-17

### Added

- Support for optional parameter Settings in init
- Support for gt,gte,lt,lte in Custom Variable pre-segmentation


## [1.1.1] - 2024-07-04

### Changed

- **Testing** - PHPUnit version changed to support in lower php versions


## [1.1.0] - 2024-07-02

### Changed

- **Refactoring**

    - Redesigned models to use private properties instead of public properties.
    - Implemented object notation with getter and setter functions for all models.

- **Unit and E2E Testing**

    - Wrote unit and E2E tests to ensure nothing breaks while pushing new code
    - Ensure critical components are working properly on every build


## [1.0.0] - 2024-06-11

### Added

- First release of VWO Feature Management and Experimentation capabilities.

    ```php
    use vwo\VWO;

    $vwoClient = VWO::init([
        'sdkKey' => 'vwo_sdk_key',
        'accountId' => 'vwo_account_id',
    ]);

    // set user context
    $userContext = [ 'id' => 'unique_user_id'];

    // returns a flag object
    $getFlag = $vwoClient->getFlag('feature_key', $userContext);

    // check if flag is enabled
    $isFlagEnabled = $getFlag['isEnabled'];

    // get variable
    $variableValue = $getFlag->getVariable('stringVar', 'default-value');

    // track event
    $trackRes = $vwoClient->trackEvent('event-name', $userContext);

    // set Attribute
    $setAttribute = $vwoClient->setAttribute('attribute-name', 'attribute-value', $userContext);

    ```
