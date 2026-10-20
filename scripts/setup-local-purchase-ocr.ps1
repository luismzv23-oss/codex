param()
$ErrorActionPreference = 'Stop'
$taskRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$taskTools = Join-Path $taskRoot 'writable/tools'
$taskDownloads = Join-Path $taskTools 'downloads'
New-Item -ItemType Directory -Path $taskDownloads -Force | Out-Null

function Get-VerifiedPurchaseTool([string]$Url, [string]$Destination, [string]$Sha256) {
    if (-not (Test-Path -LiteralPath $Destination) -or (Get-FileHash -LiteralPath $Destination -Algorithm SHA256).Hash -ne $Sha256) {
        Invoke-WebRequest -Uri $Url -OutFile $Destination
    }
    if ((Get-FileHash -LiteralPath $Destination -Algorithm SHA256).Hash -ne $Sha256) {
        throw 'El archivo descargado no coincide con la integridad esperada.'
    }
}

$taskPopplerArchive = Join-Path $taskDownloads 'poppler.zip'
Get-VerifiedPurchaseTool 'https://github.com/oschwartz10612/poppler-windows/releases/download/v26.09.0-0/Release-26.09.0-0.zip' $taskPopplerArchive '7A6F256A0DDF7536182246A5733331BF4677CBCC34F4663774947AD34556C8D0'
if (-not (Test-Path -LiteralPath (Join-Path $taskTools 'poppler/poppler-26.09.0/Library/bin/pdftoppm.exe'))) {
    Expand-Archive -LiteralPath $taskPopplerArchive -DestinationPath (Join-Path $taskTools 'poppler') -Force
}

$taskOcr = Join-Path $taskTools 'tesseract'
if (-not (Test-Path -LiteralPath (Join-Path $taskOcr 'tesseract.exe'))) {
    $taskInstaller = Join-Path $taskDownloads 'tesseract-setup.exe'
    Get-VerifiedPurchaseTool 'https://github.com/tesseract-ocr/tesseract/releases/download/5.5.0/tesseract-ocr-w64-setup-5.5.0.20241111.exe' $taskInstaller 'F3FC4236425B690C8BE756F35793F77394EE004BE0A6460A440C754D892F68BC'
    $taskInstall = Start-Process -FilePath $taskInstaller -ArgumentList @('/S',('/D=' + $taskOcr)) -WindowStyle Hidden -PassThru -Wait
    if ($taskInstall.ExitCode -ne 0) { throw 'Falló la instalación de Tesseract.' }
    # The upstream installer can choose its per-user installation directory.
    if (-not (Test-Path -LiteralPath (Join-Path $taskOcr 'tesseract.exe'))) {
        $taskSource = Join-Path $env:LOCALAPPDATA 'Programs/Tesseract-OCR'
        if (-not (Test-Path -LiteralPath (Join-Path $taskSource 'tesseract.exe'))) { $taskSource = Join-Path $env:ProgramFiles 'Tesseract-OCR' }
        if (-not (Test-Path -LiteralPath (Join-Path $taskSource 'tesseract.exe'))) { throw 'No se pudo localizar Tesseract después de instalarlo.' }
        New-Item -ItemType Directory -Path (Join-Path $taskOcr 'tessdata') -Force | Out-Null
        Get-ChildItem -LiteralPath $taskSource -File |
            Where-Object { $_.Extension -in @('.dll','.exe','.txt') -and $_.Name -notmatch 'unins|uninstall' } |
            Copy-Item -Destination $taskOcr
        Copy-Item -LiteralPath (Join-Path $taskSource 'tessdata/configs') -Destination (Join-Path $taskOcr 'tessdata') -Recurse -Force
        Copy-Item -LiteralPath (Join-Path $taskSource 'tessdata/eng.traineddata') -Destination (Join-Path $taskOcr 'tessdata') -Force
    }
}
New-Item -ItemType Directory -Path (Join-Path $taskOcr 'tessdata') -Force | Out-Null
Get-VerifiedPurchaseTool 'https://raw.githubusercontent.com/tesseract-ocr/tessdata_fast/87416418657359cb625c412a48b6e1d6d41c29bd/spa.traineddata' (Join-Path $taskOcr 'tessdata/spa.traineddata') '6F2E04D02774A18F01BED44B1111F2CD7F3BA7AC9DC4373CD3F898A40EA6B464'
& (Join-Path $taskOcr 'tesseract.exe') --version
if ($LASTEXITCODE -ne 0) { throw 'No se pudo ejecutar Tesseract.' }
& (Join-Path $taskOcr 'tesseract.exe') --list-langs
if ($LASTEXITCODE -ne 0) { throw 'No se pudo comprobar los idiomas.' }
& (Join-Path $taskTools 'poppler/poppler-26.09.0/Library/bin/pdftoppm.exe') -v
if ($LASTEXITCODE -ne 0) { throw 'No se pudo ejecutar Poppler.' }
Write-Output 'Herramientas locales preparadas. Ejecute php spark compras:facturas-estado.'
