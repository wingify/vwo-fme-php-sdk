# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

[1.3.0] - 2024-10-03

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

[1.2.5] - 2024-07-29

### Fixed

- Removed unnecessary vendor/autoload from `UuidUtil.php`

[1.2.1] - 2024-07-17

### Added

- Support for optional parameter SettingsFile in init
- Support for gt,gte,lt,lte in Custom Variable pre-segmentation


[1.1.1] - 2024-07-04

### Changed

- **Testing** - PHPUnit version changed to support in lower php versions


[1.1.0] - 2024-07-02

### Changed

- **Refactoring**

    - Redesigned models to use private properties instead of public properties.
    - Implemented object notation with getter and setter functions for all models.

<<<<<<< HEAD
- **Unit and E2E Testing**
=======
- **Unit and E2E Testing**
>>>>>>> origin/feat/personalize-meg

    - Set up Test framework using `Jest`
    - Wrote unit and E2E tests to ensure nothing breaks while pushing new code
    - Ensure criticla components are working properly on every build


[1.0.0] - 2024-06-11

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
