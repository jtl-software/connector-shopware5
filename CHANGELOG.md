1.4.0
-----
- Added billsafe btn tansaction number
- Added product first import unit name
- Added product variation set default (standard) type
- Added crossselling group support (sw_related, sw_similar)
- Fixed wrong customer type hint in customer mapper class
- Fixed missing crossselling linking

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
