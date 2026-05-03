# ExeShield

ExeShield is a cPanel-hostable web application that lets you upload a Windows executable file (`.exe`) and download a new version whose original bytes are encrypted with AES-256. The downloaded file self-decrypts at runtime and runs the original program exactly as it would have without protection.

This project was built with full accessibility in mind for blind and low-vision developers and users. Every file contains heavy line-by-line comments so a screen reader can read the code aloud and the listener can follow what it does.

---

## What this project is

When you run the original program your `.exe` bytes sit on disk in plain form and can be read, copied, or reverse-engineered by anyone with the file. ExeShield wraps those bytes in AES-256-CBC encryption so that opening the protected file in a hex editor shows only random-looking data.

When the protected file is run on any Windows computer it silently decrypts itself, writes the original program to the Windows temporary folder, runs it, and then deletes the temp copy.

---

## Two-part architecture explained

ExeShield has two separate pieces that work together. Understanding both is important before you start.

### Part one: The stub executable

The stub is a small Windows program, written in Python, that contains the decryption logic. You compile it once on your Windows PC using PyInstaller. The result is a file called `stub.exe` that lives in the `web/stub/` folder on your server.

The stub does not contain your encrypted program. It is an empty shell. When the PHP backend processes an upload, it reads `stub.exe`, appends the encrypted bytes of your program to the end of the stub, and adds a small binary header containing the encryption key and initialisation vector. The combined file is what the visitor downloads.

When the visitor runs the downloaded file, Windows runs the stub code first. The stub opens its own file, seeks to the end, reads the header to find the key, decrypts the payload, writes the result to a temporary file, and launches it.

### Part two: The web application

The web application is a set of PHP files that you upload to your cPanel hosting account. Visitors go to your website, choose their `.exe` file, choose a protection mode (automatic key or password), and click the Protect button. The PHP script encrypts the file on the server and immediately sends the protected `.exe` back as a download. Nothing is stored on the server after the response is sent.

---

## Step 1: Build the stub

These instructions are for your Windows PC. You only need to do this once.

Before you start, make sure Python 3.8 or newer is installed. Download it from the official Python website at `https://www.python.org/downloads/`. During installation, tick the checkbox that says "Add Python to PATH".

Open a Command Prompt window. Press the Windows key, type "cmd", and press Enter.

Navigate to the `stub-builder` folder inside the project:

    cd path\to\exe-protector-web\stub-builder

Run the build script:

    build_stub.bat

The script will print a message for each of its five steps:

- Step 1: Checks that Python is available.
- Step 2: Creates a Python virtual environment.
- Step 3: Downloads and installs PyInstaller and the cryptography library.
- Step 4: Compiles `stub.py` into `stub.exe` using PyInstaller. This step takes one to three minutes.
- Step 5: Copies `stub.exe` to the `web/stub/stub.exe` location in this project.

When you see the message "BUILD COMPLETE", the stub is ready.

If you see an error at any step, read the error message printed above it. The most common problems are Python not being on PATH (see the installation note above) or no internet connection when installing packages.

---

## Step 2: Upload to cPanel

Log in to your cPanel account. Open the File Manager and navigate to `public_html`.

Upload the entire contents of the `web/` folder to `public_html`. After uploading you should see these files and folders inside `public_html`:

    index.php
    protect.php
    .htaccess
    assets/
        style.css
        script.js
    stub/
        stub.exe
        README.txt

Make sure `stub.exe` was uploaded. If you do not upload it, the protect page will show an error explaining what to do.

After uploading, set the permissions on `stub.exe` to 644 so the web server can read it. In cPanel File Manager, right-click `stub.exe`, choose Change Permissions, and set it to 644.

If your cPanel has a PHP version selector, choose PHP 7.4 or newer. ExeShield uses `random_bytes()` and `hash_pbkdf2()` which require PHP 7.0 at minimum.

---

## Step 3: Use the site

Visit your domain in a web browser. You will land on the main ExeShield page.

The page contains a file upload form with the following elements:

- A file picker labeled "Choose your Windows .exe file". Click it (or press Space or Enter when it has focus) to open a file browser and select your `.exe`.
- A radio button group labeled "Protection mode". You have two choices:
  - "Auto-generated key (no password needed)": a random key is embedded in the protected exe. Anyone who has the file can run it without a password.
  - "Set my own password (exe will ask for it at runtime)": you type a password. When the protected exe is run on another computer, a console window opens and asks for the password before launching.
- A password field that activates when you choose the password option.
- A button labeled "Protect my EXE".

Click the Protect button. The page announces "Uploading file. Please wait." then "Encrypting your file on the server." then "Building protected exe. Your download will start shortly." Your browser will then prompt you to save a file named `Protected_yourfile.exe`.

---

## Antivirus warning

Self-decrypting executables are commonly flagged as malware by antivirus software. This happens because the behaviour of reading its own bytes, decrypting them, writing a new executable, and running it is structurally identical to how some real malware works. This is a false positive; ExeShield does not do anything malicious.

If your antivirus flags the protected exe, you have several options:

- Add the file to your antivirus exclusion list. The exact steps depend on your antivirus product; most have a "Whitelist" or "Exclusions" section in their settings.
- Purchase a code-signing certificate and sign the protected exe with it. A trusted signature significantly reduces antivirus detections. Signing tools are available from Microsoft (signtool.exe) and certificate authorities such as DigiCert and Sectigo.
- Use Windows Defender's "Submit a sample" feature to report the detection as a false positive.

---

## Legal notice

Only use ExeShield on software you own or have explicit permission to modify. Encrypting or obfuscating someone else's software without their permission may violate copyright law, software license agreements, or computer fraud laws in your country.

ExeShield is provided as-is, without any warranty. The project contributors are not responsible for how you use this tool.

---

## Troubleshooting

### Error: openssl extension missing

The error page says "Encryption failed" and mentions the openssl extension. PHP's OpenSSL extension is required by `protect.php`. On some cPanel hosts it is disabled by default.

To fix this, log in to cPanel, open the "Select PHP Version" or "MultiPHP INI Editor" tool, find the `openssl` extension in the list, and enable it. Save the changes. If you do not see this option, contact your hosting provider.

### Error: upload size exceeded

The error page says the file is too large. By default ExeShield allows files up to 50 MB. If your file is larger, edit the `.htaccess` file and change the values of `upload_max_filesize` and `post_max_size`.

Shared hosting providers sometimes set a hard server-level limit that overrides `.htaccess`. If changing `.htaccess` does not work, contact your hosting provider and ask them to increase the PHP upload limit.

### Error: stub.exe not found

The protect page shows an error saying stub.exe is missing. Follow the instructions in Step 1 (build the stub) and Step 2 (upload to cPanel) above to build and upload `stub.exe`.

### File permissions error

If PHP cannot read `stub.exe` you will see a generic server error. Make sure the file permission is set to 644 (read-only for web server). Set it using cPanel File Manager: right-click the file, choose Change Permissions, enter 644.

### Protected exe crashes immediately

If the protected exe opens a console window and shows an error message, the most common causes are:

- Wrong password: you entered the wrong password at the runtime prompt.
- Corrupt download: re-download the protected exe and try again.
- Antivirus interference: your antivirus deleted or quarantined the temporary file before the stub could run it. Add the protected exe to your exclusion list.

### Rate limiting and abuse prevention

ExeShield does not include rate limiting by default. If your site receives abusive traffic, consider adding Cloudflare (free tier available), or configuring fail2ban on a VPS, or enabling cPanel's built-in ModSecurity rules.

---

## License

MIT License. Copyright 2024 dpunk12. See the `LICENSE` file for the full text.
