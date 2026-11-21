# Complete Web Push Removal Script
$ErrorActionPreference = "Stop"

Write-Host "=== Removing Web Push from faculty-dashboard.php ===" -ForegroundColor Cyan
$file = "c:\xampp\htdocs\Equipment-Lending-Website\faculty-dashboard.php"
$content = Get-Content $file -Raw
$content = $content -replace '\<\?php require __DIR__ \. ''/shared/views/push-permission-modal\.php''; \?\>\r?\n', ''
$content = $content -replace '\<script src="shared/assets/js/push-init\.js\?v=\<\?php echo @filemtime\(PS_ROOT \. ''/shared/assets/js/push-init\.js''\); \?\>"\>\</script\>\r?\n', ''
$content | Out-File $file -Encoding UTF8 -NoNewline
Write-Host "✓ faculty-dashboard.php cleaned" -ForegroundColor Green

Write-Host "`n=== Removing Web Push from admin-dashboard.php ===" -ForegroundColor Cyan
$file = "c:\xampp\htdocs\Equipment-Lending-Website\admin-dashboard.php"
$content = Get-Content $file -Raw
$content = $content -replace '\<\?php require __DIR__ \. ''/shared/views/push-permission-modal\.php''; \?\>\r?\n', ''
$content = $content -replace '\<script src="shared/assets/js/push-init\.js\?v=\<\?php echo @filemtime\(PS_ROOT \. ''/shared/assets/js/push-init\.js''\); \?\>"\>\</script\>\r?\n', ''
$content | Out-File $file -Encoding UTF8 -NoNewline
Write-Host "✓ admin-dashboard.php cleaned" -ForegroundColor Green

Write-Host "`n=== Complete! ===" -ForegroundColor Green
Write-Host "Files remaining to modify: 14 (PHP files with ps_push_send/ps_receipts_sweep calls)"
Write-Host "Next: Run the detailed modification script..."
