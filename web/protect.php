<?php
/**
 * protect.php — ExeShield Backend: Encrypt and Build the Protected EXE
 *
 * This script is called when the visitor submits the upload form in index.php.
 * It performs the following steps in order:
 *
 *   1. Validates the uploaded file (size, extension, MIME type).
 *   2. Reads stub.exe from web/stub/stub.exe.  Returns an error page if missing.
 *   3. Reads the uploaded exe bytes into memory.
 *   4. Generates a random 16-byte IV and 16-byte salt using random_bytes().
 *   5. Determines the AES key:
 *        - Mode "auto":     generates a random 32-byte key; mode_flag = 0.
 *        - Mode "password": derives a 32-byte key from the user's password
 *                           using PBKDF2-HMAC-SHA256 (200,000 iterations);
 *                           mode_flag = 1.
 *   6. Encrypts the uploaded exe with AES-256-CBC (PKCS7 padding via openssl_encrypt).
 *   7. Builds the 73-byte binary header + 8-byte magic footer:
 *        Bytes  0 –  7 : pack('P', strlen($ciphertext))  — uint64 LE payload length
 *        Bytes  8 – 23 : $iv                              — 16 bytes
 *        Bytes 24 – 39 : $salt                            — 16 bytes
 *        Byte  40      : chr($mode_flag)                  — 1 byte (0 or 1)
 *        Bytes 41 – 72 : $key or 32 zero bytes            — 32 bytes (key slot)
 *        Bytes 73 – 80 : 'EXESHLD1'                       — 8-byte magic
 *        Total trailer  = 81 bytes
 *   8. Concatenates: stub_bytes . ciphertext . header . magic.
 *   9. Streams the result to the browser as a file download.
 *  10. Cleans up the temporary upload file.
 *
 * CRITICAL BYTE-LAYOUT NOTE:
 *   The header layout here MUST exactly match what stub.py reads.
 *   Both use: AES-256-CBC, PKCS7 padding, PBKDF2-HMAC-SHA256 with 200,000 iterations.
 *   Header is always exactly 73 bytes (8+16+16+1+32) regardless of mode.
 *   Trailer (header + magic) is always 81 bytes.
 *
 * SECURITY:
 *   - Uses random_bytes() (CSPRNG) for IV, salt, and auto key.
 *   - Does not store any uploaded or generated data after the response is sent.
 *   - File extension and MIME type are both checked.
 *   - Stub path uses __DIR__ so it works regardless of cPanel directory layout.
 *   - All values interpolated into the error page HTML are passed through
 *     htmlspecialchars() inside output_error_page() so callers cannot
 *     accidentally introduce a Cross-Site Scripting (XSS) vulnerability.
 *
 * PHP VERSION:
 *   Requires PHP 7.0 or newer.  The heredoc closer below is left-aligned
 *   (not indented) so it works on every PHP 7+ release without relying on
 *   the PHP 7.3 "flexible heredoc" feature.
 *
 * SCREEN-READER NOTE:
 *   Error pages returned by this script use semantic HTML with a clear h1
 *   and descriptive paragraph text so screen readers announce them clearly.
 */

// =============================================================================
// Configuration constants
// =============================================================================

// Maximum allowed upload size in bytes (50 MB).
// This matches the php_value upload_max_filesize in .htaccess.
define('MAX_UPLOAD_BYTES', 50 * 1024 * 1024);

// Path to stub.exe, relative to THIS file's directory.
// __DIR__ is cPanel-portable; it resolves to the absolute path of protect.php's folder.
define('STUB_PATH', __DIR__ . '/stub/stub.exe');

// The 8-byte magic footer that marks the end of every protected exe.
// MUST match MAGIC_FOOTER in stub.py.
define('MAGIC_FOOTER', 'EXESHLD1');

// PBKDF2 iterations — MUST match PBKDF2_ITERATIONS in stub.py.
define('PBKDF2_ITERATIONS', 200000);

// AES key length in bytes (256-bit key).
define('KEY_LEN', 32);

// AES IV length in bytes (128-bit IV for CBC mode).
define('IV_LEN', 16);

// Salt length in bytes for PBKDF2.
define('SALT_LEN', 16);


// =============================================================================
// Helper: output_error_page($title, $message, $back_link)
// =============================================================================
/**
 * Terminates the script and sends a fully accessible HTML error page.
 *
 * SECURITY:
 *   $title, $message, and $back_link are HTML-escaped inside this function
 *   using htmlspecialchars() with ENT_QUOTES so callers cannot accidentally
 *   introduce a Cross-Site Scripting (XSS) issue by including a value that
 *   came from user input (for example, an uploaded filename or extension).
 *
 * @param string $title     Short heading describing the error (will be escaped).
 * @param string $message   Full explanation in plain language (will be escaped).
 * @param string $back_link URL for the "Go back" link (will be escaped; defaults to index.php).
 */
function output_error_page(string $title, string $message, string $back_link = 'index.php'): void
{
    // Set HTTP status to 400 Bad Request so the browser knows this is an error.
    http_response_code(400);

    // Send the Content-Type header so the browser renders HTML correctly.
    header('Content-Type: text/html; charset=UTF-8');

    // Escape every value that gets interpolated into HTML below.
    // ENT_QUOTES escapes BOTH single and double quotes, so attribute values
    // are also safe.  'UTF-8' must match the page encoding declared in <meta>.
    $safe_title   = htmlspecialchars($title,     ENT_QUOTES, 'UTF-8');
    $safe_message = htmlspecialchars($message,   ENT_QUOTES, 'UTF-8');
    $safe_back    = htmlspecialchars($back_link, ENT_QUOTES, 'UTF-8');

    // Output a minimal but fully accessible HTML error page.
    // The closing "HTML;" marker is left-aligned (not indented) for
    // compatibility with PHP 7.0–7.2 which do not support indented heredoc closers.
    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Error — ExeShield</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <header role="banner"><h1>ExeShield</h1></header>
  <main id="main-content" role="main">
    <section aria-labelledby="error-heading" class="error-section">
      <h2 id="error-heading">Error: {$safe_title}</h2>
      <p>{$safe_message}</p>
      <p><a href="{$safe_back}" class="btn-primary">Go back</a></p>
    </section>
  </main>
</body>
</html>
HTML;

    // Stop all further script execution.
    exit;
}


// =============================================================================
// Step 0: Only accept POST requests.
// =============================================================================

// If someone visits protect.php directly via GET, redirect them to index.php.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // 303 See Other redirects the browser to index.php after a non-POST visit.
    header('Location: index.php', true, 303);
    exit;
}


// =============================================================================
// Step 1: Validate the uploaded file.
// =============================================================================

// Check that the file input was present in the form submission.
if (!isset($_FILES['exefile'])) {
    output_error_page(
        'No file uploaded',
        'The form was submitted without a file. Please go back and choose a .exe file to upload.'
    );
}

// Capture the uploaded file information from $_FILES.
$upload = $_FILES['exefile'];

// Check for PHP upload errors.
// UPLOAD_ERR_OK (0) means the upload succeeded.
// Other codes indicate specific problems.
if ($upload['error'] !== UPLOAD_ERR_OK) {
    // Map PHP upload error codes to human-readable messages.
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE   => 'The file exceeds the server\'s maximum upload size (upload_max_filesize in php.ini).',
        UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the maximum size specified in the HTML form.',
        UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded. Please try again.',
        UPLOAD_ERR_NO_FILE    => 'No file was selected for upload.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server is missing a temporary folder. Please contact the site administrator.',
        UPLOAD_ERR_CANT_WRITE => 'The server failed to write the upload to disk. Please contact the site administrator.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload. Please contact the site administrator.',
    ];

    // Retrieve the message for this error code, or use a generic fallback.
    // $upload['error'] is an integer constant so it is safe; output_error_page
    // will HTML-escape it anyway as a defence-in-depth measure.
    $err_msg = $upload_errors[$upload['error']] ?? "Unknown upload error (code {$upload['error']}).";
    output_error_page('Upload failed', $err_msg);
}

// Check the file size (from the upload array — not the size on disk, to avoid race conditions).
if ($upload['size'] > MAX_UPLOAD_BYTES) {
    // Format the limit in MB for human-readable output.
    $limit_mb = MAX_UPLOAD_BYTES / 1024 / 1024;
    output_error_page(
        'File too large',
        "The uploaded file is " . round($upload['size'] / 1024 / 1024, 1) . " MB, "
        . "but the maximum allowed size is {$limit_mb} MB. "
        . "Please use a smaller file."
    );
}

// Check the file extension — it must end in .exe (case-insensitive).
$original_name = basename($upload['name']);
$extension     = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

if ($extension !== 'exe') {
    // Note: $extension is HTML-escaped by output_error_page; no need to escape here.
    output_error_page(
        'Invalid file type',
        "Only Windows .exe files are accepted. "
        . "The file you uploaded has the extension '." . $extension . "'. "
        . "Please go back and select a .exe file."
    );
}

// MIME type check: inspect the actual file content.
// Windows PE executables begin with the bytes "MZ" (0x4D 0x5A).
// We check for the "MZ" magic bytes directly for maximum reliability.
$tmp_path = $upload['tmp_name'];

// Open the uploaded file and read the first two bytes.
$fh = fopen($tmp_path, 'rb');
if ($fh === false) {
    output_error_page(
        'Server error',
        'The server could not read the uploaded file. Please try again or contact the administrator.'
    );
}
$magic_bytes = fread($fh, 2);
fclose($fh);

if ($magic_bytes !== 'MZ') {
    output_error_page(
        'Invalid file content',
        'The uploaded file does not appear to be a valid Windows executable. '
        . 'Windows .exe files must begin with the "MZ" signature bytes. '
        . 'Please check that you selected the correct file.'
    );
}


// =============================================================================
// Step 2: Read stub.exe.
// =============================================================================

// Check that stub.exe exists in the expected location.
if (!file_exists(STUB_PATH)) {
    // STUB_PATH is escaped by output_error_page; no need to escape here.
    output_error_page(
        'stub.exe not found',
        'The server cannot find stub.exe at: ' . STUB_PATH . '. '
        . 'You must build stub.exe on your Windows PC using the build_stub.bat script '
        . 'in the stub-builder/ folder, then upload it to web/stub/stub.exe. '
        . 'See README.md for detailed instructions.',
        'https://github.com/dpunk12/exe-protector-web#step-1-build-the-stub'
    );
}

// Read all bytes of stub.exe into a PHP string (binary-safe).
$stub_bytes = file_get_contents(STUB_PATH);

if ($stub_bytes === false) {
    output_error_page(
        'Could not read stub.exe',
        'The server found stub.exe but could not read it. '
        . 'Please check that the file permission is set to 644 in cPanel File Manager.'
    );
}


// =============================================================================
// Step 3: Read the uploaded exe bytes.
// =============================================================================

// file_get_contents on the tmp_name reads the uploaded file as a binary string.
$payload_bytes = file_get_contents($tmp_path);

if ($payload_bytes === false) {
    output_error_page(
        'Could not read uploaded file',
        'The server could not read the uploaded file from the temporary directory. '
        . 'Please try uploading again.'
    );
}


// =============================================================================
// Step 4: Generate random IV and salt.
// =============================================================================

// random_bytes() uses the OS CSPRNG (cryptographically secure).
// IV_LEN = 16 bytes for AES-CBC.
$iv   = random_bytes(IV_LEN);

// SALT_LEN = 16 bytes for PBKDF2.
$salt = random_bytes(SALT_LEN);


// =============================================================================
// Step 5: Determine the AES key and mode_flag.
// =============================================================================

// Read the "mode" field from the POST form data.
// Sanitise it to prevent unexpected values; default to "auto" if missing.
$mode = isset($_POST['mode']) ? trim($_POST['mode']) : 'auto';

if ($mode === 'password') {
    // --- Password mode ---

    // Read the password from the POST data.
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Reject empty passwords in password mode.
    if ($password === '') {
        output_error_page(
            'Password required',
            'You selected "Set my own password" but did not enter a password. '
            . 'Please go back and type a password in the password field.'
        );
    }

    // Derive a 32-byte key from the password using PBKDF2-HMAC-SHA256.
    // Parameters MUST match stub.py's PBKDF2HMAC call:
    //   hash_pbkdf2('sha256', $password, $salt, 200000, 32, true)
    //   - 'sha256'         = SHA-256 hash algorithm
    //   - $password        = the user's password string
    //   - $salt            = the 16-byte random salt we generated above
    //   - 200000           = PBKDF2_ITERATIONS (200,000 rounds)
    //   - 32               = output key length in bytes (256 bits)
    //   - true             = return raw binary bytes (not hex string)
    $key = hash_pbkdf2('sha256', $password, $salt, PBKDF2_ITERATIONS, KEY_LEN, true);

    // mode_flag 1 means "password-derived key; key_slot in header is zero-filled".
    $mode_flag = 1;

    // The key_slot in the header is 32 zero bytes when mode is password.
    // (The actual key is re-derived from the password at runtime; we don't store it.)
    $key_slot = str_repeat("\x00", KEY_LEN);

} else {
    // --- Auto mode (default) ---

    // Generate a random 32-byte key.
    $key = random_bytes(KEY_LEN);

    // mode_flag 0 means "embedded key; key_slot in header contains the real key".
    $mode_flag = 0;

    // The key_slot in the header IS the actual key so stub.exe can read it directly.
    $key_slot = $key;
}


// =============================================================================
// Step 6: Encrypt the uploaded exe with AES-256-CBC.
// =============================================================================

// openssl_encrypt parameters:
//   $data        = the raw bytes of the uploaded exe
//   'aes-256-cbc'= cipher algorithm (AES with 256-bit key, CBC mode)
//   $key         = the 32-byte key determined in Step 5
//   OPENSSL_RAW_DATA = return raw ciphertext (no base64); also enables PKCS7 padding
//   $iv          = the 16-byte random IV generated in Step 4
$ciphertext = openssl_encrypt($payload_bytes, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

if ($ciphertext === false) {
    output_error_page(
        'Encryption failed',
        'The server could not encrypt your file. '
        . 'This usually means the openssl PHP extension is not enabled. '
        . 'Please ask your hosting provider to enable the openssl extension, '
        . 'or check the PHP error log for details.'
    );
}


// =============================================================================
// Step 7: Build the 73-byte header + 8-byte magic footer.
// =============================================================================

// --- payload_length (bytes 0–7) ---
// pack('P', n) packs n as an unsigned 64-bit integer in machine byte order.
// PHP's 'P' format is little-endian on x86/x64 (the only platforms where
// stub.exe will run), matching struct.unpack_from("<Q", ...) in stub.py.
$payload_length_packed = pack('P', strlen($ciphertext));

// --- header assembly ---
// Concatenate all header fields in the exact order stub.py expects them:
//   8 bytes : payload_length (little-endian uint64)
//  16 bytes : iv
//  16 bytes : salt
//   1 byte  : mode_flag (0 or 1)
//  32 bytes : key_slot (actual key or zeros)
// Total    = 73 bytes
$header = $payload_length_packed   // bytes  0 –  7
        . $iv                      // bytes  8 – 23
        . $salt                    // bytes 24 – 39
        . chr($mode_flag)          // byte  40
        . $key_slot;               // bytes 41 – 72

// Verify header size at runtime (development safety check).
$header_len = strlen($header);
if ($header_len !== 73) {
    // This should never happen if the constants above are correct.
    output_error_page(
        'Internal error',
        "Header is {$header_len} bytes instead of expected 73 bytes. "
        . 'Please report this to the site administrator.'
    );
}

// Magic footer (8 bytes).
$magic = MAGIC_FOOTER;


// =============================================================================
// Step 8: Assemble and stream the final protected exe.
// =============================================================================

// Final layout:
//   [stub_bytes][ciphertext][header 73 bytes][magic 8 bytes]
//
// This matches what stub.py reads:
//   last 8 bytes         = magic
//   next 73 bytes back   = header (contains payload_length, iv, salt, mode_flag, key_slot)
//   next payload_length bytes back = ciphertext
//   everything before that = stub's own code (ignored at read time)

// Sanitise the original filename for use in the Content-Disposition header.
// Remove anything that isn't alphanumeric, underscore, hyphen, or dot.
$safe_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $original_name);

// Build the output filename.
$output_filename = 'Protected_' . $safe_name;

// Set response headers BEFORE outputting any bytes.
// These headers tell the browser this is a downloadable binary file.
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $output_filename . '"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output the total size so the browser can show a progress bar.
$total_size = strlen($stub_bytes) + strlen($ciphertext) + strlen($header) + strlen($magic);
header('Content-Length: ' . $total_size);

// Stream the assembled file directly to the browser output buffer.
// We do NOT write to disk — the entire output lives in memory.
echo $stub_bytes;    // Step 1 of 4: stub exe bytes
echo $ciphertext;    // Step 2 of 4: AES-256-CBC ciphertext
echo $header;        // Step 3 of 4: 73-byte binary header
echo $magic;         // Step 4 of 4: 8-byte magic footer "EXESHLD1"


// =============================================================================
// Step 9: Cleanup — delete the temporary upload file.
// =============================================================================

// PHP normally deletes tmp_name automatically at script end, but we delete it
// explicitly here to ensure the uploaded bytes are gone immediately.
if (file_exists($tmp_path)) {
    unlink($tmp_path);
}

// Script ends here.  PHP will flush the output buffer and close the connection.
