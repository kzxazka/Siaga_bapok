# Backup script for Siaga Bapok database
$backupDir = "$PSScriptRoot\backups"
$date = Get-Date -Format "yyyyMMdd_HHmmss"
$backupFile = "$backupDir\siagabapok_backup_$date.sql"

# Create backup directory if it doesn't exist
if (-not (Test-Path -Path $backupDir)) {
    New-Item -ItemType Directory -Path $backupDir
}

# Database credentials
$dbUser = "root"
$dbPass = ""
$dbName = "siagabapok_db"

# Run mysqldump
& "C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe" --user=$dbUser --password=$dbPass --databases $dbName --result-file=$backupFile

# Compress the backup
Compress-Archive -Path $backupFile -DestinationPath "$backupFile.zip" -Force
Remove-Item $backupFile

# Delete backups older than 30 days
Get-ChildItem -Path $backupDir -Filter "*.zip" | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-30) } | Remove-Item -Force
