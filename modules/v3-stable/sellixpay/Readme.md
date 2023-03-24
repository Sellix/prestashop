Install the Package via Upload Module Dialog:
---------------------------------------------
1. Navigate into Modules => Module Manager => Click Upload Module button => Select the package (prestashop-sellixpay.zip)
2. Click the Configure link to configure the module settings.

Install the Package via FTP upload:
------------------------------------
1. Extract the package (prestashop-sellixpay.zip)
2. Upload the sellixpay folder to /modules/ folder 
3. Navigate into Modules => Module Catalog => Enter the 'sellixpay' in the search field to locate the module.
4. Click the install button to install the module.
5. Click the Configure link to configure the module settings.

Locate installed module:
------------------------------------
1. Navigate into Modules => Module Manager => Enter the 'sellixpay' in the search field to locate the module.
2. Click the Configure link to configure the module settings.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.2 =
- Added a new gateway: Cash App.
- Added a new option: merchant can enable their own branded sellix pay checked url to their customers.
- And updated Bitcoin Cash gateway value
- And updated perfectmoney gateway value
- Updated webhook to handle the 'PROCESSING' status received from sellix pay

= 1.0.3 =
- Removed layout selection, confirmations, sellix payment gateways enable/disable, and email configuration fields
- Removed sellix payment gateway selection UI in the frontend.