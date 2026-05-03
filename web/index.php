<?php
/**
 * index.php — ExeShield Upload Page
 *
 * This is the main page visitors see.  It contains:
 *   - A heading and tagline describing the service.
 *   - A form to upload a .exe file.
 *   - A radio group to choose between auto-generated key or password protection.
 *   - A password input field (always present in the HTML; JS shows/hides it visually).
 *   - An ARIA live region that JavaScript will use to announce progress to screen readers.
 *   - A "How it works" section for sighted and non-sighted visitors.
 *   - A footer with antivirus and legal notices.
 *
 * ACCESSIBILITY NOTES:
 *   - Every form control has a <label> tied with for= / id=.
 *   - The radio group is wrapped in a <fieldset> with a <legend>.
 *   - The password field uses aria-disabled to signal its state without hiding it
 *     from assistive technology.
 *   - The #status div uses role="status" and aria-live="polite" so screen readers
 *     announce changes made by JavaScript without interrupting the user.
 *   - All structural sections use appropriate heading levels (h1, h2, h3).
 *   - The page works correctly with JavaScript disabled — the form still submits.
 *
 * SCREEN-READER NOTE:
 *   PHP comments on every block describe what it produces in the HTML output.
 */

// This PHP file only outputs HTML; there is no PHP logic here.
// All processing is done in protect.php when the form is submitted.
?>
<!doctype html>
<!-- lang="en" tells screen readers and browsers that the page language is English. -->
<html lang="en">
<head>
  <!-- Character encoding: UTF-8 supports all characters. -->
  <meta charset="UTF-8">

  <!-- Viewport: ensures the page scales correctly on phones and tablets. -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Page title shown in the browser tab and announced by screen readers when
       the page first loads. -->
  <title>ExeShield — Protect Your Windows EXE</title>

  <!-- Link to our CSS stylesheet for visual styling. -->
  <link rel="stylesheet" href="assets/style.css">

  <!-- Description for search engines and social media previews. -->
  <meta name="description" content="ExeShield lets you upload a Windows .exe and download
    a version whose bytes are AES-256 encrypted. The protected exe self-decrypts at runtime.">
</head>

<body>
  <!-- =====================================================================
       SKIP LINK — keyboard / screen-reader users can jump straight to main content.
       ===================================================================== -->
  <a class="skip-link" href="#main-content">Skip to main content</a>

  <!-- =====================================================================
       HEADER — site name and tagline.
       ===================================================================== -->
  <header role="banner">
    <!-- h1 is the single most important heading on the page.
         Screen readers announce it when navigating by headings. -->
    <h1>ExeShield</h1>
    <!-- The tagline is a short description of the service. -->
    <p class="tagline">AES-256 protection for your Windows executables — hosted on cPanel PHP</p>
  </header>

  <!-- =====================================================================
       MAIN — the primary content area.
       ===================================================================== -->
  <main id="main-content" role="main">

    <!-- =================================================================
         ARIA LIVE REGION — JavaScript updates this div's text content to
         announce progress messages ("Uploading…", "Encrypting…", etc.).
         role="status" and aria-live="polite" ensure screen readers read the
         new text without interrupting whatever the user is currently hearing.
         ================================================================= -->
    <div
      id="status"
      role="status"
      aria-live="polite"
      aria-atomic="true"
      class="status-region"
    >
      <!-- JavaScript will insert messages here.
           The div is empty on initial page load. -->
    </div>

    <!-- =================================================================
         UPLOAD FORM
         action="protect.php": the form data is sent to protect.php.
         method="post": large binary files require POST, not GET.
         enctype="multipart/form-data": required for file uploads.
         aria-labelledby="form-heading": links the form to its heading for
           assistive technology that supports this attribute.
         ================================================================= -->
    <section class="upload-section" aria-labelledby="form-heading">
      <!-- Section heading — h2 because h1 is used for the site name above. -->
      <h2 id="form-heading">Upload and Protect Your EXE</h2>

      <form
        id="upload-form"
        method="post"
        enctype="multipart/form-data"
        action="protect.php"
        aria-describedby="form-desc"
        novalidate
      >
        <!-- Brief description of the form, referenced by aria-describedby above. -->
        <p id="form-desc">
          Choose a Windows <code>.exe</code> file (up to 50 MB), select a
          protection mode, then click <strong>Protect my EXE</strong>.
          Your browser will download the protected file.
        </p>

        <!-- -----------------------------------------------------------
             FILE INPUT
             The label text "Choose .exe file" is tied to the input by
             for="exefile" matching id="exefile".
             accept=".exe" hints to the file-picker to show only .exe files,
             but we validate server-side too.
             ----------------------------------------------------------- -->
        <div class="field-group">
          <label for="exefile">Choose your Windows .exe file (required):</label>
          <input
            type="file"
            id="exefile"
            name="exefile"
            accept=".exe"
            required
            aria-required="true"
            aria-describedby="file-hint"
          >
          <!-- Hint text tied to the input via aria-describedby. -->
          <span id="file-hint" class="field-hint">
            Maximum file size: 50 MB. Only .exe files are accepted.
          </span>
        </div>

        <!-- -----------------------------------------------------------
             PROTECTION MODE RADIO GROUP
             A <fieldset> groups related controls; <legend> names the group.
             Screen readers announce the legend before each radio option,
             so the user always knows which group they are in.
             ----------------------------------------------------------- -->
        <fieldset class="mode-fieldset" id="mode-fieldset">
          <legend>Protection mode (required):</legend>

          <!-- Option 1: auto-generated key — no password needed at runtime. -->
          <div class="radio-option">
            <input
              type="radio"
              id="mode-auto"
              name="mode"
              value="auto"
              checked
              aria-describedby="mode-auto-desc"
            >
            <label for="mode-auto">
              Auto-generated key (no password needed)
            </label>
            <span id="mode-auto-desc" class="field-hint">
              A random key is embedded in the protected exe. Anyone who has the
              file can run it without typing a password.
            </span>
          </div>

          <!-- Option 2: user-supplied password — runtime will prompt for it. -->
          <div class="radio-option">
            <input
              type="radio"
              id="mode-password"
              name="mode"
              value="password"
              aria-describedby="mode-password-desc"
            >
            <label for="mode-password">
              Set my own password (exe will ask for it at runtime)
            </label>
            <span id="mode-password-desc" class="field-hint">
              You choose a password now. The protected exe will show a console
              prompt asking for the password before it runs.
            </span>
          </div>
        </fieldset>

        <!-- -----------------------------------------------------------
             PASSWORD INPUT
             This field is ALWAYS present in the HTML so it works without JS.
             When "auto" mode is selected:
               - aria-disabled="true" is set by JS to signal it is inactive.
               - The CSS class "field-inactive" de-emphasises it visually.
             When "password" mode is selected:
               - aria-disabled="false" and class "field-inactive" is removed.
             The label clearly explains when this field is needed.
             ----------------------------------------------------------- -->
        <div class="field-group" id="password-group">
          <label for="password">
            Password for runtime decryption
            <span class="label-conditional">(only needed if you chose "Set my own password" above)</span>:
          </label>
          <input
            type="password"
            id="password"
            name="password"
            autocomplete="new-password"
            aria-disabled="true"
            aria-describedby="password-hint"
            class="field-inactive"
          >
          <span id="password-hint" class="field-hint">
            Leave blank if you chose the auto-generated key mode.
            If you set a password, anyone who runs the protected exe must type
            this exact password or it will not start.
          </span>
        </div>

        <!-- -----------------------------------------------------------
             SUBMIT BUTTON
             min-width / min-height are enforced in CSS (48×48 px minimum
             touch target per WCAG 2.5.5).
             ----------------------------------------------------------- -->
        <div class="field-group">
          <button type="submit" class="btn-primary" id="submit-btn">
            Protect my EXE
          </button>
        </div>

      </form><!-- end #upload-form -->
    </section><!-- end .upload-section -->

    <!-- =================================================================
         HOW IT WORKS — informational section.
         h2 heading lets screen-reader users navigate to this section by
         pressing H (heading navigation shortcut).
         ================================================================= -->
    <section class="info-section" aria-labelledby="how-heading">
      <h2 id="how-heading">How it works</h2>

      <!-- Each step is a paragraph so the screen reader reads them naturally. -->
      <ol class="steps-list">
        <li>
          <strong>You upload your .exe.</strong>
          The file is sent directly to this server over HTTPS and is never
          stored on disk after processing.
        </li>
        <li>
          <strong>The server encrypts it.</strong>
          PHP generates a random 16-byte initialisation vector and either a
          random 256-bit key (auto mode) or derives one from your password
          using PBKDF2-HMAC-SHA256 with 200,000 iterations.
          Your exe bytes are encrypted with AES-256-CBC.
        </li>
        <li>
          <strong>The encrypted payload is appended to the stub.</strong>
          A small Windows launcher (<code>stub.exe</code>) is prepended to
          the ciphertext.  The key or password salt is stored in a 73-byte
          header, followed by an 8-byte magic footer.
        </li>
        <li>
          <strong>Your browser downloads the protected file.</strong>
          The result is named <code>Protected_yourfile.exe</code>.
          No copy is kept on the server.
        </li>
        <li>
          <strong>When run on Windows, the protected exe decrypts itself.</strong>
          It reads its own encrypted payload, decrypts it (optionally asking
          for your password), writes the original exe to a temporary folder,
          runs it, then deletes the temp file.
        </li>
      </ol>

      <!-- Sub-section: technical details -->
      <h3>Technical details</h3>
      <ul>
        <li>Encryption: AES-256-CBC with PKCS7 padding.</li>
        <li>Key derivation (password mode): PBKDF2-HMAC-SHA256, 200,000 iterations, 32-byte output.</li>
        <li>IV and salt: randomly generated per upload using PHP's <code>random_bytes()</code>.</li>
        <li>The stub is a PyInstaller-built Windows exe that uses the <code>cryptography</code> library.</li>
        <li>No data is logged or stored server-side after the response is sent.</li>
      </ul>
    </section><!-- end .info-section -->

  </main><!-- end #main-content -->

  <!-- =====================================================================
       FOOTER
       ===================================================================== -->
  <footer role="contentinfo">
    <!-- Antivirus warning — important for users so they are not alarmed. -->
    <p class="footer-note">
      <strong>Antivirus notice:</strong>
      Self-decrypting executables are commonly flagged by antivirus software
      because they share structural characteristics with malware.
      This is a false positive.
      You may need to add the protected exe to your antivirus whitelist,
      or sign the file with a code-signing certificate to reduce detections.
      See the README for details.
    </p>

    <!-- Legal note. -->
    <p class="footer-note">
      <strong>Legal notice:</strong>
      Only use this tool on software you own or have explicit rights to modify.
      ExeShield is provided as-is without warranty.
      The site operator is not responsible for misuse.
    </p>

    <!-- Copyright. -->
    <p class="footer-copy">
      &copy; <?php echo date('Y'); ?> dpunk12 &mdash; ExeShield &mdash;
      <a href="https://github.com/dpunk12/exe-protector-web">Source on GitHub</a>
    </p>
  </footer>

  <!-- =====================================================================
       JAVASCRIPT — loaded at end of body so the page content renders first.
       The page works fully without JS; this script is progressive enhancement.
       ===================================================================== -->
  <script src="assets/script.js"></script>

</body>
</html>
