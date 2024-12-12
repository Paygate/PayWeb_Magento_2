# Changelog

## [[v2.6.0]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.6.0)

### Added

- Compatibility update for Magento 2.4.7 and PHP 8.2.
- Support for **BWP** and **NAD** currencies.

### Fixed

- Resolved issue where orders with unique order IDs were not being located.
- Fixed duplicate invoice generation for certain orders.
- Query method for **Cron** and **Fetch** reliability.

## [[v2.5.6]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.5.6)

### Fixed

- Property Declared Dynamically PHP 8.2 Errors.
- Cron not running for orders with unique initial statuses.

## [[v2.5.5]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.5.5)

### Added

- Mask Paygate encryption key.

### Changed

- Improve debug logging.

### Fixed

- RCS payment type support.

## [[v2.5.4]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.5.4)

### Changed

- Return accepted Magento return object for the notify controller.

## [[v2.5.3]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.5.3)

### Fixed

- Fix issues with Apple Pay payment type not selecting as expected.

## [[v2.5.2]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.5.2)

### Added

- Apple Pay, Samsung Pay, and RCS Payment Types.
- Option to force Payment Type selection.

### Changed

- Tested on Magento 2.4.6.
- Refactor in keeping with Magento 2 PHP code standards.
- Update composer requirements.

### Fixed

- Redirection issues to payment page (CSP whitelist).

## [[v2.5.1]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.5.1)

### Changed

- Magento 2.4.5 and PHP 8.1 compatibility.
- Code quality improvements and bug fixes.

## [[v2.5.0]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.5.0)

### Added

- Ability to set Successful Order State in addition to Successful Order Status.

### Changed

- Remove `layout="1column"` from frontend.
- **BREAKING CHANGE:** Ensure Successful Order State is configured after update.

## [[v2.4.9]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.4.9)

### Changed

- Improve store scope handling.
- Update Masterpass to Scan to Pay.

## [[v2.4.8]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.4.8)

### Changed

- Improve fetch method to uncancel approved fetched orders.

## [[v2.4.7]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.4.7)

### Added

- Debug logging option for fetch method.

### Changed

- Only use real order ID in PayWeb reference field.

## [[v2.4.6]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.4.6)

### Changed

- Disable PayGate on unsupported multiship.
- Fix undefined offset on cron query method.
- Improve Payment Type styling on one-step checkout.
- Add additional logging on fetch method.

## [[v2.4.5]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.4.5)

### Added

- PayPal payment type.

### Fixed

- Fix Swagger API issue.
- Fix DATA_CHK issue on Payment Types.

## [[v2.4.4]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.4.4)

### Changed

- Consolidate Cron class into single file for Magento 2.4.x.
- Block double order processing on multiple requests.
- Add API support for PayGate payment gateway.
- Improve multisite scope handling for Fetch and Cron.
- Add support for Store Credit if available (`getTotalDue`).

## [[v2.4.3]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.4.3)

### Changed

- Increase cron schedule to query every 10 minutes.

## [[v2.4.2]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.4.2)

### Fixed

- Fix cron query method not firing as expected on some configurations.
- Fix 'Fetch' query method not updating order status from backend.
- Remove redirect button.
- Improve IPN reliability.

## [[v2.4.1]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.4.1)

### Added

- Transaction ID added for Pending orders after initiate.
- Payment Types radio block fix for some themes.

### Fixed

- Fix PayVault Card Delete.
- Fix undefined index error `$orderData['additional_information']`.
- Order status set to pending if checksum fails to pick up by cron.
- Don't update order status by IPN if already complete or processing.

## [[v2.4.0]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.4.0)

### Added

- Support for Magento 2.4.x.
- PayWeb Query function.
- PayVault feature.
- Payment Types feature.

### Changed

- Remove legacy iFrame code.
- Use Magento Payment Transactions instead of order comments.
- Improve handling of spaces in PayGate ID.

## [[v2.3.4]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.3.4i)

### Fixed

- Fix order page redirect if successful transaction on some servers.

## [[v2.3.3]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.3.3i)

### Fixed

- Fix sessions cleared in some browsers.

## [[v2.3.2]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.3.2i)

### Changed

- Improve testing validation for existing invoices.
- Tested with Magento 2.3.3.

## [[v2.3.1]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.3.1i)

### Added

- CSRF handling in notify script.
- Changelog.

### Changed

- Version check for CSRF - 2.3.0+ only.
- Use Grand Total only in PayGate.

## [[v2.3.0]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.3.0i)

### Added

- Better compatibility with Magento 2.2.x.

### Changed

- Process order updates in IPN or redirect.
- Minor bug fixes.

## [[v2.2.9]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.2.9i)

### Added

- Magento 2.3.1 compatibility.
- CSRF form validation.
- OneStepCheckout compatibility.

## [[v2.2.8]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.2.8i)

### Fixed

- Fix children order cancelling issue.

## [[v2.2.7]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.2.7i)

### Added

- Add order send and options.

## [[v2.2.6]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.2.6)

### Added

- Initial release.

## [[v2.2.5]](https://github.com/Paygate/PayWeb_Magento_2/releases/tag/v2.2.5)

### Changed

- Hide close button.
- Disable close modal.

## [v2.2.4]

### Fixed

- Fix invoices not automatically created.

## [v2.2.3]

### Changed

- Apply rebranding.
- Fix redirect issue.

## [v2.2.2]

### Added

- Setup instructions.

### Fixed

- Config bug fix.

## [v2.2.1]

### Added

- Add system settings per store.
- Include ZAR as currency.

### Fixed

- Fix test mode bug.
- Fix errors.

## [v2.2.0]

### Changed

- Change copyright 2015 -> 2017.
- Initial version.
