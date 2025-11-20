@echo off
setlocal

REM 你的项目目录
set PROJECT_DIR=H:\work\code\fun

REM 使用的端口
set PORT=8000

REM 如果 php 已经在环境变量里，就用 php
set PHP_EXE=php

REM 如果你有固定 php 路径，也可以改成这样：
REM set PHP_EXE="C:\laragon\bin\php\php-8.3.0-Win32-vs16-x64\php.exe"

cd /d "%PROJECT_DIR%"

echo ========================================
echo  启动 DoFun 本地开发服务器
echo  地址: http://localhost:%PORT%/
echo  关闭窗口即可停止服务
echo ========================================
echo.

%PHP_EXE% -S localhost:%PORT% router.php

echo.
echo PHP 内置服务器已停止，按任意键退出…
pause >nul
endlocal
