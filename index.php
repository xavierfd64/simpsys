<?php

/**
 * Shared-hosting shim. Some hosts (including lower shared-hosting tiers)
 * only let an account's DocumentRoot point at the account root, not at a
 * /public subfolder — so the whole app can be uploaded there as-is and this
 * file hands off to public/index.php, avoiding any need for the
 * installer/user to change DocumentRoot or move folders themselves. Hosts
 * that DO allow pointing DocumentRoot at /public simply never load this
 * file at all.
 */
$publicPath = __DIR__.'/public';

chdir($publicPath);

require $publicPath.'/index.php';
