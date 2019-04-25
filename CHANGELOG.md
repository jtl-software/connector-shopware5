2.2.1.2
------
- Problems with product price during push fixed

2.2.1
------
- CO-447 Lower case to camel case transformation on product attributes during push fixed
- CO-452 Product price quicksync fixed

2.2.0.2
------
- Set configuratorOptions on article details fix
- Class alias typo corrected

2.2.0.1
------
- Hotfix for backward compatiblity with namespace aliases

2.2.0
------
- CO-286 Custom properties support added
- CO-310 Additional text support added
- CO-354 Translations from product variation child attributes will be imported into JTL-Wawi
- CO-359 Use same de-/activation logic for products as in shopware backend
- CO-433 Convert Shopware product DateTime attributes to string when importing into JTL-Wawi
- Convert Shopware product attribute names from camel case to underscore when importing into JTL-Wawi

2.1.21
------
- Image copy problem fixed
- Avoid warnings when cleaning unassigned product images

2.1.20
------
- Fixed plugin autoloading

2.1.19
------
- CO-340 Fixed problems with image upload
- Don't add the same image mapping multiple times
- Fixed customer import problem when creation date is set to 0000-00-00

2.1.18
------
- Backward compatiblity to SW 5.2 and 5.3 fixed
- Write category.mapping flag correctly during connector installation
- Compatiblity to Shopware composer installation fixed
- Default value of article detail preselection changed to false

2.1.17
------
- Fixed main article detail preselection logic
- Images from not existent products will be removed

2.1.16
------
- CO-288 Specifics assignment revised
- CO-306 Changing variation values afterwards fix
- Main article detail will be switched during push automatically if actual one has no stock
- Config file structure completely revised

2.1.15
------
- WAWI-25142 Order status mappings revised
- CO-222 Main article detail can be changed in Wawi
- CO-292 Account holder in PUI fixed
- CO-293 kind value of pseudo master article detail changed to 3
- CO-294 Changing main image of a product after push fix
- Category attributes with individual names will be pulled into Wawi
- Camel case product attribute names will be converted to underscore

2.1.14
------
- Fixed payment mapping for heidelpay
- CO-254 / CO-251 Added Support for new Paypal Plugin
- CO-255 Shorten vat number in case it is longer than 20 chars
- CO-283 CrossSelling events are available

2.1.13
------
- CO-269 Consider seo description, seo keywords and page title by manufacturer pull
- Config flags added for undefined attributes handling during push
- Deprecations replaced for Shopware 5.5 compatiblity
- Connector Core downgraded, due to compatiblity reasons

2.1.12
------
- CO-229 Fixed saving the customer order tracking code
- CO-232 Added customer order cleared date when payment is completed

2.1.11
------
- CO-215 Fixed missing customer order billing and shipping title
- CO-218 Fixed sw pre 5.2.25 access on protected property
- CO-226 Fixed sw pre 5.4 delivery note path access
- CO-184 Added customer order voucher support
- CO-196 Added product changed date support

2.1.10
------
- CO-212 Fixed customer address support

2.1.9
-----
- CO-186 Added full shopware delivery note support with pdf creation
- CO-195 Fixed compatibility issues with SW < 5.4.0

2.1.8
-----
- CO-185 Added shopware order document type support

2.1.7
-----
- Fixed missing default laststock value

2.1.6
-----
- Fixed wrong version string

2.1.5
-----
- CO-182 Added shopware 5.4.0 support
- CO-183 Added shopware variants in listing support

2.1.4
-----
- CO-157 Fixed missing ProductSpecialPriceItem class
- CO-158 Added shopware media service support

2.1.3
-----
- CO-120 Added customer note property
- CO-125 Added customer order status change to processing after pull
- CO-129 Fixed category mapping parents when moving to another level

2.1.2
-----
- CO-99 Added Heidelpay invoice fallback
- CO-106 Added product sku fallback detection
- CO-107 Removed customer order attribute limit

2.1.1
-----
- CO-92 Fixed supplier delivery time
- CO-99 Added Heidelpay invoice support
- CO-101 Added wawi float to shopware integer instock conversion
- CO-103 Added missing customer push validation

2.1
---
- Shopware 5.3 support
- Added connector core log control

2.0.18
------
- Added category mapping check for missing data
- Added feature product attributes must be storable in different languages regardless of other product informations
- Removed debug logging in image mapper
- Fixed category mapping missing image for languages

2.0.17
------
- Fixed minor product image titles translation bug
- Fixed parameter binding in pre php 7

2.0.16
------
- Fixed customer birthday property warning
- Added customer birthday property wawi exception workaround
- Added customer order pull start date to payments
- Added product attribute sw_image_config_ignores to configure product image assignments
- Added product image titles via wawi image alt text
- Removed deprecated method call

2.0.15
------
- Fixed customer birthday and title
- Fixed product purchasePrice push and pull
- Fixed temp directory and added fallback

2.0.14
------
- Added missing product specific group fail safe
- Added customer order pull start date (via config parameter customer_order_pull_start_date)
- Added new category attribute management support
- Fixed customer number
- Fixed category meta title in non default languages
- Fixed product filter group allocation after set removal
- Removed additional detail text

2.0.13
------
- Fixed PayPal Plus installment check
- Added temp directory fallback

2.0.12
------
- Fixed customer push salutation bug
- Fixed customer order status push
- Added new product attribute sw_pseudo_sales
- Added paypal plus installment

2.0.11
------
- Added customer mapping via email
- Added number format for billpay and paypal invoice
- Fixed missing cms text in mapped categories

2.0.10
------
- Added attribute value hard cast to string

2.0.9
-----
- Fixed PayPal Plus pui property
- Reverted product attribute change from version 2.0.8

2.0.8
-----
- Added PayPal Plus support
- Added product image description support (wawi name property)
- Fixed product attributes - will not override existing values

2.0.7
-----
- Fixed product price save return value (Event ProductPriceAfterPushEvent will be triggered again)
- Fixed attributes to support camel case and containing an _ in the name
- Fixed crossselling primary key mapping and added extended developer logging
- Fixed shopware customer entity setter usage
- Added String Helper class

2.0.6
-----
- Added product attribute method_exists check

2.0.5
-----
- Added customer hasCustomerAccount property
- Added new shopware 5.2 attribute management
- Added translated product attributes (pull)
- Added Wawi missing product default price work around
- Fixed product base price

2.0.4
-----
- Changing customer order item gross price to 4 decimal places
- Preparing for SW 5.2.4

2.0.3
-----
- Fixed currency problem
- Added additional customer informations

2.0.2
-----
- Added price work around for empty customer group wawi bug

2.0.1
-----
- Fixed installer issue

2.0.0
-----
- Removed debit support
- Added csrf support
- Removed fax support
- Added multiple customer addresses support
- Added Shopware 5.2 support

1.4.8
-----
- Added fixed (eg. attr6) category attributes
- Fixed Cross Selling identity mapping

1.4.7
-----
- Added new connector core 2.2.16

1.4.6
-----
- Added product id at delivery note item
- Added product attr for activating notifications
- Add product specifics only to parent and normal products
- Added new customer order item types (surcharge and coupon)
- Fixed customer order item gross and net prices

1.4.5
-----
- Fixed plugins autoload when using phar

1.4.4
-----
- Added php 7 error handling support
- Added product shipping free attribute
- Added customer order and customer order item gross prices
- Fixed product specific id mapping

1.4.3
-----
- Fixed bootstrap update management
- Added broken product data failsafe

1.4.2
-----
- Fixed crossselling reference to elements of a temporary array expression
- Changed crosselling find sql to select fixed columns

1.4.1
-----
- Fixed payment module code
- Changed product name helper to ignore variations
- Changed all db classes and added more safety

1.4.0
-----
- Added billsafe btn tansaction number
- Added product first import unit name
- Added product variation set default (standard) type
- Added crossselling group support (sw_related, sw_similar)
- Fixed wrong customer type hint in customer mapper class
- Fixed missing crossselling linking
- Fixed duplicate payments

1.3.2
-----
- Fixed customer missing or nulled birthday

1.3.1
-----
- Added customer payment sepa support
- Added specific value image support
- Added product delivery time manuell and fixed value
- Added customer additional text and birthday
- Added product sort
- Added specific filterable via isGlobal
- Fixed customer order shipping and billing additional text handling
- Changed payment pull error tolerance

1.3.0
-----
- Added connector install, phar and suhosin check
- Added category language locale check
- Added billsafe to payment types
- Added billsafe customer order support
- Added shopware 5.1 media service support
- Added file and mysql validation files
- Changed payment mysql trigger to trigger only at state 'payment completed'
- Changed customer order shipping item support

1.2.5
-----
- Fixed product attribute type
- Added product attribute multilanguage support

1.2.4
-----
- Fixed product attribute regex
- Fixed product specific pull mapping
- Fixed product pseudo and base price reset
- Fixed category sorting for different languages
- Added product variation and variation value sorting
- Changed product attribute start value from 3 to 4

1.2.3
-----
- Changed product image main and position handling

1.2.2
-----
- Fixed product attribute delete bug
- Fixed missing shop query
- Fixed customer order detail price net
- Added product variation child option name if additional text is empty
- Added product specific relation deletion
- Added product variation type fallback handling
- Changed product image pull and delete handling
- Changed product image push handling (total rework)
- Changed to core connector version 2.0
- Changed gitignore and other deprecated stuff
- Changed specific value duplicates handling

1.2.1
-----
- Fixed product meta description sync
- Fixed translation shop mapping with multiple same locale
- Changed parent dummy generation in plugin install routine
- Changed product attribute behavior
- Added currency sync

1.1.2
-----
- Fixed product variation language bug
- Fixed measurement language bug
- Fixed category loading by name
- Added product variation type (standard, swatches) support
- Added product child image relation support

1.1.1
-----
- Added category by name mapping
- Added product variation multi language support
- Added customer main language support
- Added manufacturer language support
- Fixed multi language bug
- Fixed specific language bug

1.1.0
-----
- Fixed customer order vat free support
- Fixed product child delete
- Fixed customer order language iso

1.0.12
------
- Fixed product attribute date type problem
- Fixed syntax error in customer order controller
- Changed price value restriction
- Changed category cms function management behavior
- Added global exception handler
- Added shopware link support
- Added customer order tax free support
- Added specific ci encoding support
- Added customer order vat number support

1.0.11
------
- Added more payment mapping codes
- Added payment name fallback
- Changed error handling

1.0.10
------
- Added floating point values support for php ini configurations
- Changed identify serverinfo byte values to megabyte

1.0.9
-----
- Added product activity support
- Added shipping method mapping
- Added product minimum quantity support
- Added base price (purchase price) support
- Added base price converting
- Added customer order status new
- Added customer / customer order shipping / billing department support
- Added category description to cms text mapping
- Added multiple image push support
- Changed pull value handling - stripping whitespaces from the beginning and end of a string
- Changed image save and delete error handling
- Changed specific value error tolerance
- Fixed payment price value to gross
- Fixed loop bug when pulling inconsistent customer orders
- Fixed cross selling save issue
- Fixed category moving problem
- Fixed payment trigger problem
- Fixed product doctrine delete problem
- Fixed category meta data problem

1.0.8
-----
- Added dhl postnumber, postoffice and packstation support
- Added customer order payment info support
- Added delivery note tracking code support
- Added paypal transaction id support
- Added and evaluate category active flag value
- Added and evaluate category isActive attribute value
- Fixed image delete result id
- Fixed cash-on-delivery (CoD) payments mapping
- Fixed image url path when using virtual paths
- Fixed linking with image relation types
- Fixed product variation images
- Fixed specific delete bug
- Fixed product variation value merging
- Fixed product image seo length
- Fixed product specific relation problem

1.0.7
-----
- Added empty billing and shipping information check in customer order controller
- Fixed single product child push and delete task
- Fixed checksum interface problems

1.0.6
-----
- Added category cms title support
- Added product uvp support
- Added seo support for product image filenames
- Added attribute for product active flag
- Added customer group key mapping in foreign tables
- Added exception for default EK customer group
- Fixed active flag for product parents to 0
- Fixed category and manufacturer image key mapping
- Fixed key mapping missing table bug
