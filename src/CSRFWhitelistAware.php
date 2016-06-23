<?php
namespace Shopware\Components;

interface CSRFWhitelistAware
{
    public function getWhitelistedCSRFActions();
}