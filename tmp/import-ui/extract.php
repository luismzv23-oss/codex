<?php
require getcwd().'/vendor/codeigniter4/framework/system/Test/bootstrap.php';
$result=(new App\Libraries\PurchasePdfExtractor())->extract(file_get_contents('tmp/import-ui/fixture.png'));
echo json_encode(['methods'=>$result['methods'],'read_total'=>str_contains($result['text'],'242')]);
