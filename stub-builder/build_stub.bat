@echo off
REM =============================================================================
REM build_stub.bat — ExeShield Stub Builder
REM =============================================================================
REM
REM PURPOSE:
REM   Run this script ONCE on your Windows PC to compile stub.py into stub.exe.
REM   After the build completes, stub.exe is copied to ..\web\stub\stub.exe so
REM   it is ready to upload to your cPanel hosting.
REM
REM REQUIREMENTS:
REM   - Python 3.8 or newer must be installed and on your PATH.
REM     Download from: https://www.python.org/downloads/
REM   - Internet connection (to download PyInstaller and cryptography from PyPI).
REM
REM HOW TO RUN:
REM   1. Open Command Prompt (press Windows key, type "cmd", press Enter).
REM   2. Navigate to this folder:
REM        cd path\to\exe-protector-web\stub-builder
REM   3. Type the script name and press Enter:
REM        build_stub.bat
REM   4. Wait for the "BUILD COMPLETE" message.
REM
REM SCREEN-READER NOTE:
REM   Each step prints a clear message so you can follow along.
REM
REM =============================================================================

echo.
echo ============================================================
echo  ExeShield Stub Builder
echo ============================================================
echo.

REM --- Step 1: Check that Python is available on PATH ---
echo [Step 1 of 5] Checking for Python installation ...
python --version >nul 2>&1
IF ERRORLEVEL 1 (
    echo ERROR: Python was not found on your PATH.
    echo Please install Python 3.8 or newer from https://www.python.org/downloads/
    echo Make sure to tick "Add Python to PATH" during installation.
    pause
    exit /b 1
)
echo Python found.
echo.

REM --- Step 2: Create a virtual environment named "venv" ---
echo [Step 2 of 5] Creating a Python virtual environment in .\venv\ ...
python -m venv venv
IF ERRORLEVEL 1 (
    echo ERROR: Failed to create virtual environment.
    echo This usually means "venv" is not available. Try: python -m pip install virtualenv
    pause
    exit /b 1
)
echo Virtual environment created.
echo.

REM --- Step 3: Install dependencies into the virtual environment ---
echo [Step 3 of 5] Installing PyInstaller and cryptography ...
echo (This may take a minute or two while packages download from the internet.)
call venv\Scripts\activate.bat
pip install --upgrade pip --quiet
pip install -r requirements.txt
IF ERRORLEVEL 1 (
    echo ERROR: Failed to install dependencies.
    echo Check your internet connection and try again.
    call venv\Scripts\deactivate.bat
    pause
    exit /b 1
)
echo Dependencies installed.
echo.

REM --- Step 4: Build stub.exe with PyInstaller ---
echo [Step 4 of 5] Compiling stub.py into stub.exe with PyInstaller ...
echo (This will take one to three minutes. Please wait.)
pyinstaller --onefile --noconfirm --name stub stub.py
IF ERRORLEVEL 1 (
    echo ERROR: PyInstaller build failed.
    echo Look at the error messages above for details.
    call venv\Scripts\deactivate.bat
    pause
    exit /b 1
)
echo Compilation complete.
echo.

REM --- Step 5: Copy the finished stub.exe to the web folder ---
echo [Step 5 of 5] Copying dist\stub.exe to ..\web\stub\stub.exe ...

REM Make sure the destination directory exists.
IF NOT EXIST "..\web\stub\" (
    mkdir "..\web\stub\"
)

copy /Y "dist\stub.exe" "..\web\stub\stub.exe"
IF ERRORLEVEL 1 (
    echo ERROR: Could not copy stub.exe to the web folder.
    echo You can copy it manually: copy dist\stub.exe ..\web\stub\stub.exe
    call venv\Scripts\deactivate.bat
    pause
    exit /b 1
)
echo stub.exe copied to ..\web\stub\stub.exe

REM --- Deactivate the virtual environment ---
call venv\Scripts\deactivate.bat

echo.
echo ============================================================
echo  BUILD COMPLETE
echo  stub.exe is at: stub-builder\dist\stub.exe
echo  Web copy is at: web\stub\stub.exe
echo.
echo  Next step: upload the contents of the web\ folder to your
echo  cPanel public_html directory.  See README.md for details.
echo ============================================================
echo.
pause
