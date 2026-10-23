<?php
function captureUiPreview(string $name, string $html): void {
    if (getenv('CODEX_UI_PREVIEW') !== '1') return;
    $dir=WRITEPATH.'private/ui-operational/';
    if (!is_dir($dir)) mkdir($dir,0700,true);
    file_put_contents($dir.$name.'.html',$html);
}
