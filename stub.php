<?php
Phar::mapPhar('connector.phar');
Phar::interceptFileFuncs();
include_once('phar://connector.phar/src/boostrap.php');

__HALT_COMPILER();