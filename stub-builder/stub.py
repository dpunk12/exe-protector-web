# =============================================================================
# stub.py — ExeShield Self-Decrypting Stub
# =============================================================================
#
# PURPOSE:
#   This file is compiled ONCE by the user on a Windows PC using PyInstaller.
#   The resulting stub.exe is uploaded to the web server.  The PHP backend
#   appends an encrypted payload (plus a small header) to stub.exe, producing
#   a "Protected_*.exe" that visitors download.
#
#   When the protected exe is run on any Windows machine it:
#     1.  Opens ITSELF for binary reading.
#     2.  Seeks to the end of the file and verifies the 8-byte magic footer
#         that the PHP backend placed there ("EXESHLD1").
#     3.  Reads the 73-byte fixed-size header that sits just before the magic.
#         Header layout (always 73 bytes regardless of mode):
#           offset  0 ..  7 :  payload_length  (uint64, little-endian)
#           offset  8 .. 23 :  iv              (16 bytes, AES initialisation vector)
#           offset 24 .. 39 :  salt            (16 bytes, PBKDF2 salt)
#           offset 40       :  mode_flag       (1 byte:  0 = embedded key,
#                                                         1 = password-derived key)
#           offset 41 .. 72 :  key_slot        (32 bytes: actual key if mode 0,
#                                                          all zeros if mode 1)
#         Total header = 73 bytes.  Adding the 8-byte magic = 81 bytes trailer.
#     4.  Reads payload_length bytes of ciphertext sitting just before the header.
#     5.  If mode_flag == 1, asks the user to type a password on the console and
#         derives the AES key using PBKDF2-HMAC-SHA256 (200 000 iterations, 32-byte
#         output, salt from the header).
#     6.  Decrypts the ciphertext with AES-256-CBC + PKCS7 unpadding.
#     7.  Writes the decrypted bytes to a uniquely named file in %TEMP%, e.g.
#         exeshield_<uuid>.exe
#     8.  Launches that file with subprocess.Popen, waits for it to finish, then
#         deletes the temp file.
#     9.  On any error, prints a plain-English message and exits with code 1.
#
# SCREEN-READER NOTE:
#   Every meaningful line of code below has a comment so you can listen through
#   it with a screen reader and understand what is happening without needing to
#   see visual structure.
#
# =============================================================================

# --- Standard-library imports ------------------------------------------------

# os: used to get the %TEMP% directory path and to delete the temp file.
import os

# sys: gives us sys.executable (path to THIS running exe) and sys.exit().
import sys

# struct: used to unpack the binary header fields (e.g. little-endian uint64).
import struct

# uuid: generates a unique identifier so two runs don't collide in %TEMP%.
import uuid

# subprocess: launches the decrypted exe as a child process.
import subprocess

# getpass: reads a password from the console without echoing characters.
# Note: on Windows with a screen reader, getpass still works — the reader will
# announce the prompt text, then silence while you type.
import getpass

# --- Third-party imports (installed via requirements.txt) --------------------

# cryptography: AES-256-CBC decryption and PBKDF2 key derivation.
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from cryptography.hazmat.primitives import padding as crypto_padding
from cryptography.hazmat.primitives.kdf.pbkdf2 import PBKDF2HMAC
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.backends import default_backend

# =============================================================================
# Constants — these MUST match the values used in protect.php exactly.
# =============================================================================

# MAGIC_FOOTER: the 8-byte sentinel that marks the very end of the file.
# PHP writes this last; the stub checks for it first.
MAGIC_FOOTER = b"EXESHLD1"

# MAGIC_LEN: length of the magic footer in bytes.
MAGIC_LEN = 8

# HEADER_LEN: fixed size of the binary header in bytes.
# Breakdown: 8 (payload_length) + 16 (iv) + 16 (salt) + 1 (mode_flag) + 32 (key_slot)
HEADER_LEN = 73

# TRAILER_LEN: total bytes at the end of the file that are NOT the payload.
# This equals the header plus the magic footer.
TRAILER_LEN = HEADER_LEN + MAGIC_LEN  # = 81

# PBKDF2_ITERATIONS: number of hashing rounds used to derive a key from a password.
# MUST match the value in protect.php (200 000).
PBKDF2_ITERATIONS = 200_000

# KEY_LEN: AES-256 requires a 32-byte (256-bit) key.
KEY_LEN = 32

# IV_LEN: AES-CBC uses a 16-byte (128-bit) initialisation vector.
IV_LEN = 16

# SALT_LEN: the PBKDF2 salt is 16 bytes.
SALT_LEN = 16


# =============================================================================
# Helper function: read_own_file
# =============================================================================

def read_own_file():
    """
    Open this executable (sys.executable) in binary read mode and return its
    full content as a bytes object.

    Returns:
        bytes: the complete binary content of the running executable.

    Raises:
        SystemExit: if the file cannot be opened.
    """

    # sys.executable is the path to the currently running Python executable.
    # When frozen with PyInstaller, this is the path to stub.exe itself.
    exe_path = sys.executable

    # Print a status line — useful for screen readers and for debugging.
    print(f"[ExeShield] Reading self: {exe_path}")

    try:
        # Open the file in binary mode ('rb') — no text decoding.
        with open(exe_path, "rb") as fh:
            # Read the entire file into memory.
            data = fh.read()
    except OSError as err:
        # OSError covers permission denied, file not found, etc.
        print(f"[ExeShield] ERROR: Could not open own executable. Reason: {err}")
        sys.exit(1)

    # Return the raw bytes.
    return data


# =============================================================================
# Helper function: verify_magic
# =============================================================================

def verify_magic(data):
    """
    Check that the last MAGIC_LEN bytes of 'data' equal MAGIC_FOOTER.

    If the magic is missing it means this exe was NOT processed by ExeShield's
    PHP backend, and the user is running the raw stub by mistake.

    Args:
        data (bytes): the full file content.

    Raises:
        SystemExit: if the magic is absent.
    """

    # Slice the last MAGIC_LEN bytes from the end of the file.
    tail = data[-MAGIC_LEN:]

    if tail != MAGIC_FOOTER:
        # This is not a protected exe — the user may have run stub.exe directly.
        print("[ExeShield] ERROR: This file was not processed by ExeShield.")
        print("            The magic footer 'EXESHLD1' was not found at the end.")
        print("            Please use the website to create a protected exe first.")
        sys.exit(1)

    # Magic found — proceed.
    print("[ExeShield] Magic footer verified.")


# =============================================================================
# Helper function: parse_header
# =============================================================================

def parse_header(data):
    """
    Extract the 73-byte binary header from just before the magic footer.

    Header byte layout (matching protect.php):
      Bytes  0 –  7 : payload_length  uint64 little-endian
      Bytes  8 – 23 : iv              16 raw bytes
      Bytes 24 – 39 : salt            16 raw bytes
      Byte  40      : mode_flag       uint8 (0 = embedded key, 1 = password)
      Bytes 41 – 72 : key_slot        32 raw bytes

    Args:
        data (bytes): the full file content.

    Returns:
        dict with keys: payload_length, iv, salt, mode_flag, key_slot
    """

    # The header sits between the payload and the magic footer.
    # We read backwards from the end: magic is the last 8 bytes, header is the
    # 73 bytes before that.
    header_end   = len(data) - MAGIC_LEN          # index where magic starts
    header_start = header_end - HEADER_LEN         # index where header starts

    # Safety check: the file must be at least TRAILER_LEN bytes long.
    if header_start < 0:
        print("[ExeShield] ERROR: File is too small to contain a valid header.")
        sys.exit(1)

    # Slice out exactly HEADER_LEN bytes.
    header_bytes = data[header_start:header_end]

    # --- Unpack payload_length (bytes 0–7, little-endian unsigned 64-bit int) ---
    # struct.unpack_from returns a tuple; we take the first element [0].
    # '<Q' means: < = little-endian, Q = unsigned 64-bit integer.
    payload_length = struct.unpack_from("<Q", header_bytes, 0)[0]

    # --- Extract iv (bytes 8–23) ---
    iv = header_bytes[8 : 8 + IV_LEN]

    # --- Extract salt (bytes 24–39) ---
    salt = header_bytes[24 : 24 + SALT_LEN]

    # --- Extract mode_flag (byte 40) ---
    # Convert the single byte to an integer with ord() or indexing.
    mode_flag = header_bytes[40]  # Python 3: indexing a bytes object gives int

    # --- Extract key_slot (bytes 41–72) ---
    key_slot = header_bytes[41 : 41 + KEY_LEN]

    # Print parsed values for screen-reader-friendly status messages.
    print(f"[ExeShield] Header parsed. Payload length: {payload_length} bytes.")
    print(f"[ExeShield] Mode: {'password-protected' if mode_flag == 1 else 'auto-key (no password needed)'}")

    # Return all parsed values as a dictionary.
    return {
        "payload_length": payload_length,
        "iv":             iv,
        "salt":           salt,
        "mode_flag":      mode_flag,
        "key_slot":       key_slot,
    }


# =============================================================================
# Helper function: extract_ciphertext
# =============================================================================

def extract_ciphertext(data, payload_length):
    """
    Slice the encrypted payload bytes from the file content.

    The layout of the file (from the end) is:
      [... original stub bytes ...][ciphertext][header 73 bytes][magic 8 bytes]

    So the ciphertext ends at (len(data) - TRAILER_LEN) and starts
    payload_length bytes before that.

    Args:
        data           (bytes): the full file content.
        payload_length (int):   number of ciphertext bytes, from the header.

    Returns:
        bytes: the raw AES ciphertext.
    """

    # The ciphertext ends exactly where the header begins.
    cipher_end   = len(data) - TRAILER_LEN
    cipher_start = cipher_end - payload_length

    # Sanity check: ciphertext must not overlap the stub's own bytes negatively.
    if cipher_start < 0:
        print("[ExeShield] ERROR: payload_length in header is larger than the file.")
        print("            The file may be corrupt or tampered with.")
        sys.exit(1)

    print(f"[ExeShield] Extracting ciphertext from byte {cipher_start} to {cipher_end}.")
    return data[cipher_start:cipher_end]


# =============================================================================
# Helper function: derive_key_from_password
# =============================================================================

def derive_key_from_password(salt):
    """
    Ask the user for a password on the console, then derive a 32-byte AES key
    using PBKDF2-HMAC-SHA256.

    Args:
        salt (bytes): the 16-byte salt read from the header.

    Returns:
        bytes: the 32-byte derived key.
    """

    print()
    print("[ExeShield] This file is password protected.")
    print("            Please type the password and press Enter.")
    print("            (Characters will not be shown as you type.)")
    print()

    # getpass.getpass prompts the user and returns the typed string.
    # It does NOT echo characters to the terminal, which is secure but still
    # works with screen readers (the reader announces the prompt text).
    try:
        password_str = getpass.getpass(prompt="Password: ")
    except (EOFError, KeyboardInterrupt):
        # User pressed Ctrl+C or piped no input.
        print("\n[ExeShield] Password entry cancelled. Exiting.")
        sys.exit(1)

    # Convert the password string to UTF-8 bytes for the KDF.
    password_bytes = password_str.encode("utf-8")

    print("[ExeShield] Deriving encryption key from password (this may take a moment) ...")

    # Build a PBKDF2HMAC object configured to match protect.php's call to
    # hash_pbkdf2('sha256', $password, $salt, 200000, 32, true).
    kdf = PBKDF2HMAC(
        algorithm  = hashes.SHA256(),    # hash algorithm: SHA-256
        length     = KEY_LEN,            # output key length: 32 bytes
        salt       = salt,               # salt from the header
        iterations = PBKDF2_ITERATIONS,  # 200 000 iterations
        backend    = default_backend(),  # use the default cryptography backend
    )

    # Derive the key.  kdf.derive() returns raw bytes.
    key = kdf.derive(password_bytes)

    print("[ExeShield] Key derived successfully.")
    return key


# =============================================================================
# Helper function: decrypt_payload
# =============================================================================

def decrypt_payload(ciphertext, key, iv):
    """
    Decrypt the AES-256-CBC ciphertext using the given key and IV, then remove
    PKCS7 padding.

    Args:
        ciphertext (bytes): the encrypted payload extracted from the file.
        key        (bytes): 32-byte AES key.
        iv         (bytes): 16-byte initialisation vector.

    Returns:
        bytes: the original, decrypted executable bytes.

    Raises:
        SystemExit: if decryption or unpadding fails (wrong key / corrupt data).
    """

    print("[ExeShield] Decrypting payload ...")

    try:
        # Create an AES-256-CBC cipher object with the given key and IV.
        cipher = Cipher(
            algorithm = algorithms.AES(key),  # AES cipher, key length = 256 bits
            mode      = modes.CBC(iv),         # CBC mode with the given IV
            backend   = default_backend(),
        )

        # Create a decryptor instance.
        decryptor = cipher.decryptor()

        # Feed the ciphertext through the decryptor.
        # update() processes the bulk of the data; finalize() flushes the last block.
        padded_plaintext = decryptor.update(ciphertext) + decryptor.finalize()

    except Exception as err:
        print(f"[ExeShield] ERROR: Decryption failed. Reason: {err}")
        print("            If this is a password-protected file, check that you")
        print("            typed the correct password.")
        sys.exit(1)

    # Remove PKCS7 padding that was added during encryption.
    # PKCS7 with block size 128 bits = 16 bytes, which matches AES block size.
    try:
        unpadder = crypto_padding.PKCS7(128).unpadder()
        plaintext = unpadder.update(padded_plaintext) + unpadder.finalize()
    except Exception as err:
        print(f"[ExeShield] ERROR: Padding removal failed. Reason: {err}")
        print("            The decrypted data has invalid padding — the key or IV")
        print("            may be wrong, or the file is corrupt.")
        sys.exit(1)

    print(f"[ExeShield] Decryption successful. Decrypted size: {len(plaintext)} bytes.")
    return plaintext


# =============================================================================
# Helper function: write_temp_exe
# =============================================================================

def write_temp_exe(decrypted_bytes):
    """
    Write the decrypted executable bytes to a uniquely named file inside the
    system's temporary directory (%TEMP% on Windows).

    Args:
        decrypted_bytes (bytes): the original executable bytes.

    Returns:
        str: the full path to the temporary file that was written.

    Raises:
        SystemExit: if the file cannot be written.
    """

    # os.environ.get("TEMP") returns the %TEMP% path on Windows.
    # Fall back to os.path.join(os.path.expanduser("~"), "AppData", "Local", "Temp")
    # if %TEMP% is not set, or just use the current directory as a last resort.
    temp_dir = os.environ.get("TEMP") or os.environ.get("TMP") or os.getcwd()

    # Build a unique file name using uuid4 (random UUID).
    unique_name = f"exeshield_{uuid.uuid4().hex}.exe"

    # Combine the directory and the unique file name.
    temp_path = os.path.join(temp_dir, unique_name)

    print(f"[ExeShield] Writing decrypted exe to: {temp_path}")

    try:
        # Open in binary-write mode ('wb') and write all bytes at once.
        with open(temp_path, "wb") as fh:
            fh.write(decrypted_bytes)
    except OSError as err:
        print(f"[ExeShield] ERROR: Could not write temp file. Reason: {err}")
        sys.exit(1)

    print("[ExeShield] Temp file written successfully.")
    return temp_path


# =============================================================================
# Helper function: launch_and_cleanup
# =============================================================================

def launch_and_cleanup(temp_path):
    """
    Launch the temporary exe, wait for it to finish, then delete it.

    Args:
        temp_path (str): full path to the temporary executable to run.
    """

    print(f"[ExeShield] Launching: {temp_path}")

    try:
        # subprocess.Popen starts the process.  We pass the path as a list so
        # Python does not interpret spaces in the path as argument separators.
        process = subprocess.Popen([temp_path])

        # Wait for the child process to finish before attempting cleanup.
        # This prevents "file in use" errors when we try to delete it.
        process.wait()

        print(f"[ExeShield] Process exited with code: {process.returncode}")

    except OSError as err:
        print(f"[ExeShield] ERROR: Could not launch temp file. Reason: {err}")
        # Fall through to cleanup even if launch failed.

    # --- Cleanup: delete the temp file ---
    print(f"[ExeShield] Deleting temp file: {temp_path}")
    try:
        os.remove(temp_path)
        print("[ExeShield] Temp file deleted.")
    except OSError as err:
        # Deletion failure is non-fatal — the OS will clean up %TEMP% eventually.
        print(f"[ExeShield] WARNING: Could not delete temp file. Reason: {err}")
        print(f"            You can manually delete: {temp_path}")


# =============================================================================
# Main entry point
# =============================================================================

def main():
    """
    Orchestrates all steps:
      1. Read own file bytes.
      2. Verify magic footer.
      3. Parse header.
      4. Extract ciphertext.
      5. Obtain AES key (from header or password prompt).
      6. Decrypt payload.
      7. Write to %TEMP%.
      8. Launch and clean up.
    """

    # Print a welcome message — helpful for screen readers.
    print("=" * 60)
    print("ExeShield Self-Decrypting Launcher")
    print("=" * 60)

    # Step 1: Read own file content into memory.
    data = read_own_file()

    # Step 2: Check the magic footer to confirm this is a protected exe.
    verify_magic(data)

    # Step 3: Parse the binary header to get IV, salt, mode, key slot.
    header = parse_header(data)

    # Step 4: Extract the encrypted payload bytes.
    ciphertext = extract_ciphertext(data, header["payload_length"])

    # Step 5: Determine the AES key.
    if header["mode_flag"] == 0:
        # Mode 0: the key is embedded directly in the header's key_slot field.
        print("[ExeShield] Using embedded key (no password required).")
        key = header["key_slot"]
    elif header["mode_flag"] == 1:
        # Mode 1: derive the key from a user-supplied password using PBKDF2.
        key = derive_key_from_password(header["salt"])
    else:
        # Unknown mode — something is wrong with the file.
        print(f"[ExeShield] ERROR: Unknown mode_flag value: {header['mode_flag']}")
        print("            The file may be corrupt or from an incompatible version.")
        sys.exit(1)

    # Step 6: Decrypt the payload.
    decrypted_bytes = decrypt_payload(ciphertext, key, header["iv"])

    # Step 7: Write the decrypted bytes to a temp file.
    temp_path = write_temp_exe(decrypted_bytes)

    # Step 8: Launch the temp exe and clean up afterward.
    launch_and_cleanup(temp_path)

    # All done — exit cleanly.
    print("[ExeShield] Done.")
    sys.exit(0)


# =============================================================================
# Script entry guard
# =============================================================================

# When PyInstaller freezes this file into stub.exe, this block ensures that
# main() is called when the exe is run.
if __name__ == "__main__":
    main()
