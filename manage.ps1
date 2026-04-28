# LaetitiaPadilla.com - gestion local/GitHub/Servidor (CyberPanel)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $root

## ====== CONFIG (ajustalo 1 vez) ======
$Config = [ordered]@{
  GitRemoteUrl = "https://github.com/weblanzarote/laetitiapadilla.git"
  Branch       = "main"

  SshHost      = "82.223.161.189"
  SshPort      = 2222
  SshUser      = "laeti9089"

  # En CyberPanel suele ser algo como:
  # - /home/TUUSUARIO/public_html
  # - /home/TUUSUARIO/public_html/DOMINIO/public_html
  # Si al desplegar no ves cambios, lo unico que suele fallar es esta ruta.
  ServerPath   = "/home/laetitiapadilla.com/public_html"
}

function Show-Menu {
  Clear-Host
  Write-Host "========================================" -ForegroundColor Cyan
  Write-Host "  LAETITIAPADILLA.COM - MANAGE" -ForegroundColor Cyan
  Write-Host "========================================" -ForegroundColor Cyan
  Write-Host ""
  Write-Host "1a. Subir a GitHub + desplegar a servidor (todo en 1 paso)"
  Write-Host "1. Inicializar Git + conectar GitHub (solo 1a vez)"
  Write-Host "2. Subir cambios a GitHub (add/commit/push)"
  Write-Host "3. Actualizar desde GitHub (pull local)"
  Write-Host "4. Desplegar a servidor (SCP: subir web)"
  Write-Host "5. Descargar copia desde servidor (SCP: backup a .\_server_backup\)"
  Write-Host "6. Ver estado (git + servidor)"
  Write-Host "7. Configurar clave SSH (Windows -> servidor, sin contrasena)"
  Write-Host "0. Salir"
  Write-Host ""
}

function Pause {
  Write-Host ""
  Read-Host "Pulsa Enter para continuar"
}

function Require-Command($name) {
  if (-not (Get-Command $name -ErrorAction SilentlyContinue)) {
    throw "No encuentro el comando '$name'. Instala 'OpenSSH Client' en Windows (Configuracion -> Caracteristicas opcionales) y prueba de nuevo."
  }
}

function Ensure-GitRepo {
  $isRepo = $false
  try {
    $null = git rev-parse --is-inside-work-tree 2>$null
    $isRepo = $true
  } catch { $isRepo = $false }

  if (-not $isRepo) {
    git init | Out-Null
  }

  $currentBranch = ""
  try { $currentBranch = (git branch --show-current).Trim() } catch { $currentBranch = "" }
  if (-not $currentBranch) {
    git checkout -b $Config.Branch | Out-Null
  } elseif ($currentBranch -ne $Config.Branch) {
    try { git checkout $Config.Branch | Out-Null } catch { git checkout -b $Config.Branch | Out-Null }
  }
}

function Init-Git-FirstTime {
  Require-Command git
  Ensure-GitRepo

  $hasOrigin = $false
  try {
    $origin = (git remote get-url origin).Trim()
    if ($origin) { $hasOrigin = $true }
  } catch { $hasOrigin = $false }

  if (-not $hasOrigin) {
    git remote add origin $Config.GitRemoteUrl
    Write-Host "Remoto 'origin' conectado a $($Config.GitRemoteUrl)" -ForegroundColor Green
  } else {
    Write-Host "Ya existe 'origin': $(git remote get-url origin)" -ForegroundColor Gray
  }

  # Primer push (si el repo remoto está vacío, esto lo deja listo)
  git add .
  $staged = (git diff --cached --name-only)
  if ($staged) {
    git commit -m "Initial commit" | Out-Null
  }

  try {
    git push -u origin $Config.Branch
  } catch {
    Write-Host "No he podido hacer push aun. Si GitHub te pide login, inicia sesion o usa Git Credential Manager." -ForegroundColor Yellow
    throw
  }
}

function Git-Push {
  Require-Command git
  Ensure-GitRepo

  $msg = Read-Host "Mensaje del commit (Enter para default)"
  if (-not $msg) { $msg = "Update $(Get-Date -Format 'yyyy-MM-dd HH:mm')" }

  git add .
  $staged = (git diff --cached --name-only)
  if ($staged) {
    git commit -m $msg | Out-Null
  } else {
    Write-Host "Sin cambios para commitear." -ForegroundColor Gray
  }

  git push origin $Config.Branch
}

function Git-Pull {
  Require-Command git
  Ensure-GitRepo
  git pull origin $Config.Branch
}

function Deploy-Full {
  Require-Command git
  Require-Command ssh
  Require-Command scp

  Git-Push
  Deploy-To-Server
}

function Deploy-To-Server {
  Require-Command scp
  Require-Command ssh

  $server = "$($Config.SshUser)@$($Config.SshHost)"
  $port = $Config.SshPort
  $dest = $Config.ServerPath

  if (-not $dest) {
    $dest = Read-Host "Ruta destino en el servidor (ej: /home/$($Config.SshUser)/public_html)"
  }

  Write-Host "Probando conexión SSH..." -ForegroundColor Yellow
  ssh -p $port $server "echo OK" | Out-Null

  Write-Host "Subiendo archivos (esto puede tardar)..." -ForegroundColor Yellow

  $files = @(
    "index.html",
    "contacto.html",
    "clases.html",
    "interprete.html",
    "acompanamiento.html",
    "styles.css",
    "script.js",
    "logo.jpg",
    "background.jpg",
    "background_trans.png",
    "tarjeta_visita.jpg"
  ) | Where-Object { Test-Path $_ }

  if ($files.Count -eq 0) {
    throw "No encuentro archivos para desplegar en esta carpeta. Estas en la raiz del proyecto?"
  }

  # Asegura carpeta destino y sube solo lo necesario (sin .git)
  ssh -p $port $server "mkdir -p '$dest'" | Out-Null
  scp -P $port $files "${server}:$dest/"

  Write-Host "Despliegue terminado." -ForegroundColor Green
  Write-Host "Si no ves cambios, revisa ServerPath en manage.ps1 (suele ser lo unico que falla)." -ForegroundColor Gray
}

function Backup-From-Server {
  Require-Command scp
  Require-Command ssh

  $server = "$($Config.SshUser)@$($Config.SshHost)"
  $port = $Config.SshPort
  $src = $Config.ServerPath
  if (-not $src) { $src = Read-Host "Ruta origen en el servidor" }

  $backupDir = Join-Path $root "_server_backup"
  if (-not (Test-Path $backupDir)) { New-Item -ItemType Directory -Path $backupDir | Out-Null }

  $stamp = Get-Date -Format "yyyyMMdd_HHmmss"
  $target = Join-Path $backupDir $stamp
  New-Item -ItemType Directory -Path $target | Out-Null

  Write-Host "Descargando copia a $target ..." -ForegroundColor Yellow
  # Nota: -r porque puede haber más assets en servidor
  scp -P $port -r "${server}:$src/" "$target"

  Write-Host "Backup terminado." -ForegroundColor Green
}

function Status-All {
  Require-Command git
  Ensure-GitRepo
  Write-Host "---- GIT STATUS ----" -ForegroundColor Cyan
  git status
  Write-Host ""
  Write-Host "---- SERVER (ls destino) ----" -ForegroundColor Cyan
  Require-Command ssh
  $server = "$($Config.SshUser)@$($Config.SshHost)"
  $port = $Config.SshPort
  $dest = $Config.ServerPath
  ssh -p $port $server "ls -la '$dest' | head -n 30" 2>$null
}

function Setup-SSHKey {
  Require-Command ssh
  Require-Command ssh-keygen

  $server = "$($Config.SshUser)@$($Config.SshHost)"
  $port = $Config.SshPort

  $keyPath = Join-Path $env:USERPROFILE ".ssh\id_ed25519"
  $pubPath = "${keyPath}.pub"
  if (-not (Test-Path $keyPath)) {
    Write-Host "Generando clave SSH (ed25519)..." -ForegroundColor Yellow
    ssh-keygen -t ed25519 -f $keyPath -N '""' | Out-Null
  } else {
    Write-Host "Ya existe clave: $keyPath" -ForegroundColor Gray
  }

  Write-Host "Copiando clave al servidor..." -ForegroundColor Yellow
  if (-not (Test-Path $pubPath)) {
    throw "No encuentro la clave publica: $pubPath"
  }

  $pubKey = (Get-Content -Raw $pubPath).Trim()
  if (-not $pubKey) {
    throw "La clave publica esta vacia: $pubPath"
  }

  # Anade la clave a authorized_keys (sin depender de ssh-copy-id).
  # Ojo: escapamos comillas simples para que no rompa el comando remoto.
  $pubKeyEscaped = $pubKey.Replace("'", "''")
  $remoteCmd = "mkdir -p ~/.ssh && chmod 700 ~/.ssh && echo '$pubKeyEscaped' >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys"
  ssh -p $port $server $remoteCmd | Out-Null

  Write-Host "Listo. Deberias poder conectar sin contrasena:" -ForegroundColor Green
  Write-Host "ssh -p $port $server" -ForegroundColor Gray
}

do {
  Show-Menu
  $input = Read-Host "Selecciona opcion"
  switch ($input) {
    '1a' { Deploy-Full; Pause }
    '1' { Init-Git-FirstTime; Pause }
    '2' { Git-Push; Pause }
    '3' { Git-Pull; Pause }
    '4' { Deploy-To-Server; Pause }
    '5' { Backup-From-Server; Pause }
    '6' { Status-All; Pause }
    '7' { Setup-SSHKey; Pause }
    '0' { exit }
  }
} while ($true)

