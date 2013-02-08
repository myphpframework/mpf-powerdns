<?php

use \MPF\REST;

\MPF\ENV::bootstrap(\MPF\ENV::DATABASE);
\MPF\ENV::paths()->addAll(PATH_SITE.'buckets/mpf-powerdns/');
\MPF\ENV::paths()->addAll(PATH_SITE.'buckets/mpf-rest/');

if (REST::basicAuth(@$_SERVER['PHP_AUTH_USER'], @$_SERVER['PHP_AUTH_PW'], 'PowderDNS Service')) {
    REST::execute('/');
}
