stub/README.txt — ExeShield Stub Placeholder
=============================================

This directory is intentionally almost empty in the Git repository.

WHY IS stub.exe NOT INCLUDED?
  stub.exe is a Windows binary compiled by PyInstaller on your local Windows PC.
  Distributing pre-built binaries in a public repository is a security risk, and
  the binary must be built fresh anyway so it contains YOUR copy of the Python
  interpreter and libraries.

HOW TO PUT stub.exe HERE:
  1. On your Windows PC, open a Command Prompt window.
  2. Navigate to the stub-builder/ folder inside this repository:
       cd path\to\exe-protector-web\stub-builder
  3. Run the build script:
       build_stub.bat
  4. The script will create stub.exe and automatically copy it to THIS folder
     (web/stub/stub.exe).

AFTER BUILDING:
  Upload the entire web/ folder to your cPanel public_html directory.
  Make sure web/stub/stub.exe is uploaded because the PHP backend reads it.

PERMISSIONS ON cPanel:
  Set stub.exe to permission 644 (readable by the web server, not executable
  by the web server user — PHP reads it as a file, not runs it).

  In cPanel File Manager: right-click stub.exe → Change Permissions → 644.

WHAT HAPPENS IF stub.exe IS MISSING?
  The protect.php script detects the missing file and returns a clear error
  page telling the user (you) to build and upload stub.exe first.
