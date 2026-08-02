<?php

return [
    'root_domain' => env('SAAS_ROOT_DOMAIN', 'staging-saas-mubledesk.mueble-playground.cc'),
    'central_domain' => env('SAAS_CENTRAL_DOMAIN', env('SAAS_ROOT_DOMAIN', 'staging-saas-mubledesk.mueble-playground.cc')),
    'scheme' => env('SAAS_SCHEME', 'https'),
];
