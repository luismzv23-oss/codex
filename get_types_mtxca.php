<?php

$wsdl = 'https://fwshomo.afip.gov.ar/wsmtxca/services/MTXCAService?WSDL';
$client = new SoapClient($wsdl, [
    'stream_context' => stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
]);
file_put_contents('types_mtxca.txt', print_r($client->__getTypes(), true));
