<?php

$wsdl = 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL';
$client = new SoapClient($wsdl, [
    'stream_context' => stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
]);
file_put_contents('types.txt', print_r($client->__getTypes(), true));
