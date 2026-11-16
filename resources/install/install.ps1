# Installation de kory sous Windows (K05, TRD §18.1) : irm https://koryhub.dev/install.ps1 | iex
# Télécharge kory_windows_<arch>.exe et checksums.txt, vérifie l'empreinte SHA-256, installe dans
# %LOCALAPPDATA%\Programs\kory (sans droit d'administrateur) et ajoute ce dossier au PATH de
# l'utilisateur, puis règle l'adresse de l'API du site qui sert le script. Empreinte différente : rien
# n'est installé. Le site remplace __KORY_VERSION__, __KORY_DOWNLOAD_URL__ et __KORY_API_URL__ en servant
# ce fichier. Pas de « exit » : avec « iex », il fermerait la fenêtre.

& {
    $ErrorActionPreference = 'Stop'
    $ProgressPreference = 'SilentlyContinue'
    $Version = '__KORY_VERSION__'
    $BaseUrl = '__KORY_DOWNLOAD_URL__'
    $ApiUrl = '__KORY_API_URL__'

    $texts = @{
        en = @{ download = 'Downloading...'; verified = 'SHA-256 checksum verified'; mismatch = 'Checksum mismatch: the downloaded file is not the published one.'; nothing = 'Nothing was installed.'; installed = 'Installed in'; path = 'Folder added to your PATH.'; next = 'Open a new terminal, then type: kory version'; failed = 'Installation failed:' }
        fr = @{ download = 'Téléchargement…'; verified = 'Empreinte SHA-256 vérifiée'; mismatch = "Empreinte différente : le fichier téléchargé n'est pas celui publié."; nothing = "Rien n'a été installé."; installed = 'Installé dans'; path = 'Dossier ajouté à votre PATH.'; next = 'Ouvrez un nouveau terminal, puis tapez : kory version'; failed = "L'installation a échoué :" }
        ar = @{ download = 'جارٍ التنزيل…'; verified = 'تم التحقق من بصمة SHA-256'; mismatch = 'البصمة مختلفة: الملف المنزّل ليس الملف المنشور.'; nothing = 'لم يُثبَّت أي شيء.'; installed = 'ثُبّت في'; path = 'أُضيف المجلد إلى PATH.'; next = 'افتح طرفية جديدة ثم اكتب: kory version'; failed = 'فشل التثبيت:' }
        pt = @{ download = 'A transferir…'; verified = 'Impressão digital SHA-256 verificada'; mismatch = 'Impressão digital diferente: o ficheiro transferido não é o publicado.'; nothing = 'Nada foi instalado.'; installed = 'Instalado em'; path = 'Pasta adicionada ao seu PATH.'; next = 'Abra um novo terminal e escreva: kory version'; failed = 'A instalação falhou:' }
    }
    $lang = (Get-Culture).TwoLetterISOLanguageName
    $t = if ($texts.ContainsKey($lang)) { $texts[$lang] } else { $texts['en'] }

    $arch = if ($env:PROCESSOR_ARCHITECTURE -eq 'ARM64' -or $env:PROCESSOR_ARCHITEW6432 -eq 'ARM64') { 'arm64' } else { 'amd64' }
    $name = "kory_windows_$arch.exe"
    $dir = Join-Path $env:LOCALAPPDATA 'Programs\kory'
    $tmp = Join-Path ([IO.Path]::GetTempPath()) ('kory-' + [Guid]::NewGuid().ToString('N'))

    Write-Host "kory $Version · windows/$arch"
    New-Item -ItemType Directory -Force -Path $tmp | Out-Null
    try {
        Write-Host $t.download
        $file = Join-Path $tmp $name
        Invoke-WebRequest -Uri "$BaseUrl/$Version/$name" -OutFile $file -UseBasicParsing
        $sums = (Invoke-WebRequest -Uri "$BaseUrl/$Version/checksums.txt" -UseBasicParsing).Content
        if ($sums -is [byte[]]) { $sums = [Text.Encoding]::UTF8.GetString($sums) }

        $expected = $null
        foreach ($line in ($sums -split "`r?`n")) {
            $parts = $line.Trim() -split '\s+'
            if ($parts.Count -eq 2 -and $parts[1] -eq $name) { $expected = $parts[0].ToLowerInvariant() }
        }
        $actual = (Get-FileHash -Algorithm SHA256 -Path $file).Hash.ToLowerInvariant()
        if ($null -eq $expected -or $expected -ne $actual) {
            Write-Host $t.mismatch -ForegroundColor Red
            Write-Host $t.nothing -ForegroundColor Red
            return
        }
        Write-Host $t.verified -ForegroundColor Green

        New-Item -ItemType Directory -Force -Path $dir | Out-Null
        $target = Join-Path $dir 'kory.exe'
        Move-Item -Force -Path $file -Destination $target
        Write-Host "$($t.installed) $target"

        $userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
        $entries = if ($userPath) { $userPath -split ';' } else { @() }
        if ($entries -notcontains $dir) {
            [Environment]::SetEnvironmentVariable('Path', (($entries + $dir) | Where-Object { $_ }) -join ';', 'User')
            Write-Host $t.path
        }
        & $target config set api-url $ApiUrl
        Write-Host $t.next -ForegroundColor Yellow
    } catch {
        Write-Host "$($t.failed) $($_.Exception.Message)" -ForegroundColor Red
        Write-Host $t.nothing -ForegroundColor Red
    } finally {
        Remove-Item -Recurse -Force -Path $tmp -ErrorAction SilentlyContinue
    }
}
