1.2.2 (unreleased)
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
