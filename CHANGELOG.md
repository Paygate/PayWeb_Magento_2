# Changelog

## [[v2.5.6]]() - 2024-09-30

### Fixed
- Property Declared Dynamically PHP 8.2 Errors.
- Cron not running for orders with unique initial statuses.

## [[v2.5.5]]() - 2024-05-03

### Added
- Mask Paygate encryption key.

### Changed
- Improve debug logging.

### Fixed
- RCS payment type support.

## [[v2.5.4]]() - 2024-01-16

### Changed
- Return accepted Magento return object for the notify controller.

## [[v2.5.3]]() - 2023-12-07

### Fixed
- Fix issues with Apple Pay payment type not selecting as expected.

## [[v2.5.2]]() - 2023-11-15

### Added
- Apple Pay, Samsung Pay, and RCS Payment Types.
- Option to force Payment Type selection.

### Changed
- Tested on Magento 2.4.6.
- Refactor in keeping with Magento 2 PHP code standards.
- Update composer requirements.

### Fixed
- Redirection issues to payment page (CSP whitelist).

## [[v2.5.1]]() - 2022-08-15

### Changed
- Magento 2.4.5 and PHP 8.1 compatibility.
- Code quality improvements and bug fixes.

## [[v2.5.0]]() - 2022-06-30

### Added
- Ability to set Successful Order State in addition to Successful Order Status.

### Changed
- Remove `layout="1column"` from frontend.
- **BREAKING CHANGE:** Ensure Successful Order State is configured after update.

## [[v2.4.9]]() - 2022-01-05

### Changed
- Improve store scope handling.
- Update Masterpass to Scan to Pay.

## [[v2.4.8]]() - 2021-10-20

### Changed
- Improve fetch method to uncancel approved fetched orders.

## [[v2.4.7]]() - 2021-10-08

### Added
- Debug logging option for fetch method.

### Changed
- Only use real order ID in PayWeb reference field.

## [[v2.4.6]]() - 2021-09-15

### Changed
- Disable PayGate on unsupported multiship.
- Fix undefined offset on cron query method.
- Improve Payment Type styling on one-step checkout.
- Add additional logging on fetch method.

## [[v2.4.5]]() - 2021-08-12

### Added
- PayPal payment type.

### Fixed
- Fix Swagger API issue.
- Fix DATA_CHK issue on Payment Types.

## [[v2.4.4]]() - 2021-07-30

### Changed
- Consolidate Cron class into single file for Magento 2.4.x.
- Block double order processing on multiple requests.
- Add API support for PayGate payment gateway.
- Improve multisite scope handling for Fetch and Cron.
- Add support for Store Credit if available (`getTotalDue`).

## [[v2.4.3]]() - 2021-07-05

### Changed
- Increase cron schedule to query every 10 minutes.

## [[v2.4.2]]() - 2021-06-15

### Fixed
- Fix cron query method not firing as expected on some configurations.
- Fix 'Fetch' query method not updating order status from backend.
- Remove redirect button.
- Improve IPN reliability.

## [[v2.4.1]]() - 2021-05-14

### Added
- Transaction ID added for Pending orders after initiate.
- Payment Types radio block fix for some themes.

### Fixed
- Fix PayVault Card Delete.
- Fix undefined index error `$orderData['additional_information']`.
- Order status set to pending if checksum fails to pick up by cron.
- Don't update order status by IPN if already complete or processing.

## [[v2.4.0]]() - 2021-04-06

### Added
- Support for Magento 2.4.x.
- PayWeb Query function.
- PayVault feature.
- Payment Types feature.

### Changed
- Remove legacy iFrame code.
- Use Magento Payment Transactions instead of order comments.
- Improve handling of spaces in PayGate ID.

## [[v2.3.4]]() - 2020-09-23

### Fixed
- Fix order page redirect if successful transaction on some servers.

## [[v2.3.3]]() - 2020-09-16

### Fixed
- Fix sessions cleared in some browsers.

## [[v2.3.2]]() - 2020-01-24

### Changed
- Improve testing validation for existing invoices.
- Tested with Magento 2.3.3.

## [[v2.3.1]]() - 2019-12-14

### Added
- CSRF handling in notify script.
- Changelog.

### Changed
- Version check for CSRF - 2.3.0+ only.
- Use Grand Total only in PayGate.

## [[v2.3.0]]() - 2019-12-14

### Added
- Better compatibility with Magento 2.2.x.

### Changed
- Process order updates in IPN or redirect.
- Minor bug fixes.

## [[v2.2.9]]() - 2019-04-30

### Added
- Magento 2.3.1 compatibility.
- CSRF form validation.
- OneStepCheckout compatibility.

## [[v2.2.8]]() - 2018-09-18

### Fixed
- Fix children order cancelling issue.

## [[v2.2.7]]() - 2018-09-11

### Added
- Add order send and options.

## [[v2.2.6]]() - 2018-08-30

### Added
- Initial release.

## [[v2.2.5]]() - 2018-08-23

### Changed
- Hide close button.
- Disable close modal.

## [[v2.2.4]]() - 2018-06-28

### Fixed
- Fix invoices not automatically created.

## [[v2.2.3]]() - 2018-03-06

### Changed
- Apply rebranding.
- Fix redirect issue.

## [[v2.2.2]]() - 2017-11-09

### Added
- Setup instructions.

### Fixed
- Config bug fix.

## [[v2.2.1]]() - 2017-10-29

### Added
- Add system settings per store.
- Include ZAR as currency.

### Fixed
- Fix test mode bug.
- Fix errors.

## [[v2.2.0]]() - 2017-10-09

### Changed
- Change copyright 2015 -> 2017.
- Initial version.
