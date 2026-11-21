@echo off
REM ============================================================
REM  Menjalankan AVSEC Agent (LangGraph.js) di http://127.0.0.1:8787
REM  Biarkan jendela ini tetap terbuka selama simulasi berjalan.
REM ============================================================
cd /d "%~dp0"

where node >nul 2>nul
if %errorlevel%==0 (
    set "NODE_CMD=node"
) else if exist "%APPDATA%\Antigravity\bin\agy-node.cmd" (
    set "NODE_CMD=%APPDATA%\Antigravity\bin\agy-node.cmd"
) else (
    echo [ERROR] Node.js tidak ditemukan. Instal Node.js LTS dari https://nodejs.org lalu jalankan ulang.
    pause
    exit /b 1
)

if not exist node_modules (
    echo [INFO] Folder node_modules belum ada. Jalankan "npm install" di folder avsec_agent terlebih dahulu.
    pause
    exit /b 1
)

call "%NODE_CMD%" src\server.mjs
pause
