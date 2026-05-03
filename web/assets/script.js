/**
 * script.js — ExeShield Progressive Enhancement JavaScript
 *
 * PURPOSE:
 *   This file adds two enhancements to the ExeShield upload page:
 *     1. Password field toggling based on the selected radio button.
 *     2. ARIA live-region status messages during form submission.
 *
 * IMPORTANT: The page works fully WITHOUT JavaScript.
 *   - The form still submits correctly.
 *   - The password field is always visible in the HTML (just de-emphasised).
 *   - Screen readers can interact with the form without JavaScript.
 *
 * SCREEN-READER NOTES:
 *   - The #status element uses role="status" and aria-live="polite".
 *     Updating its textContent triggers an announcement without interrupting
 *     whatever the screen reader is currently speaking.
 *   - When the password field is not needed, we set aria-disabled="true"
 *     (NOT disabled="disabled") so screen readers still discover the field
 *     and can announce "grayed out" or "unavailable" depending on the reader.
 *   - We toggle the CSS class "field-inactive" for visual de-emphasis.
 *
 * LINE-BY-LINE COMMENTS:
 *   Every meaningful action is commented so a screen reader can read through
 *   this file and the developer can follow along without visual scanning.
 */

// =============================================================================
// Wait for the DOM to be fully loaded before attaching event listeners.
// "DOMContentLoaded" fires when the HTML is parsed, before images load.
// =============================================================================
document.addEventListener('DOMContentLoaded', function () {

  // ---------------------------------------------------------------------------
  // Grab references to the key DOM elements.
  // If any element is missing (e.g., JS is running on a different page),
  // the null checks below will prevent errors.
  // ---------------------------------------------------------------------------

  // The radio button for "auto-generated key" mode.
  var radioAuto = document.getElementById('mode-auto');

  // The radio button for "password" mode.
  var radioPassword = document.getElementById('mode-password');

  // The password <input> element.
  var passwordInput = document.getElementById('password');

  // The container div wrapping the password field (for group-level styling).
  var passwordGroup = document.getElementById('password-group');

  // The ARIA live region where we announce status messages.
  var statusRegion = document.getElementById('status');

  // The upload form element.
  var uploadForm = document.getElementById('upload-form');

  // The submit button (so we can disable it during submission).
  var submitBtn = document.getElementById('submit-btn');


  // ---------------------------------------------------------------------------
  // Helper function: setStatus(message)
  // Updates the ARIA live region text.
  // Screen readers will announce the new message politely (when the user is idle).
  // ---------------------------------------------------------------------------
  function setStatus(message) {
    // Guard: do nothing if the status region doesn't exist.
    if (!statusRegion) { return; }

    // Setting textContent triggers the aria-live="polite" announcement.
    // We set it to an empty string first, then the message, so the screen reader
    // re-announces even if the message text is the same as before.
    statusRegion.textContent = '';

    // Use a short timeout so the DOM change is observed by assistive technology.
    // Some screen readers only detect live-region updates after a small delay.
    setTimeout(function () {
      statusRegion.textContent = message;
    }, 50);
  }


  // ---------------------------------------------------------------------------
  // Helper function: updatePasswordField()
  // Shows or hides (visually) the password field based on which radio is selected.
  // Uses aria-disabled instead of disabled so screen readers still find the field.
  // ---------------------------------------------------------------------------
  function updatePasswordField() {
    // Guard: if the radio buttons or password input are missing, do nothing.
    if (!radioAuto || !radioPassword || !passwordInput || !passwordGroup) { return; }

    if (radioPassword.checked) {
      // Password mode is active — enable the password field.

      // Remove the inactive visual style.
      passwordInput.classList.remove('field-inactive');
      passwordGroup.classList.remove('field-inactive');

      // aria-disabled="false" tells screen readers the field is active.
      passwordInput.setAttribute('aria-disabled', 'false');

      // Move focus to the password field so screen-reader users are positioned there.
      // We use a short delay to allow the screen reader to process the radio change first.
      setTimeout(function () {
        passwordInput.focus();
      }, 100);

    } else {
      // Auto mode is active — de-emphasise the password field.

      // Add the inactive visual style (opacity + cursor: not-allowed).
      passwordInput.classList.add('field-inactive');
      passwordGroup.classList.add('field-inactive');

      // aria-disabled="true" tells screen readers the field is unavailable.
      // (We do NOT use disabled="disabled" because that hides it from some readers.)
      passwordInput.setAttribute('aria-disabled', 'true');

      // Clear any previously typed password when switching away from password mode.
      passwordInput.value = '';
    }
  }


  // ---------------------------------------------------------------------------
  // Attach change event listeners to both radio buttons.
  // "change" fires when the user selects a radio button.
  // ---------------------------------------------------------------------------
  if (radioAuto) {
    radioAuto.addEventListener('change', updatePasswordField);
  }

  if (radioPassword) {
    radioPassword.addEventListener('change', updatePasswordField);
  }

  // Run once on page load to set the correct initial state.
  // (The HTML has "auto" pre-checked, so the password field starts inactive.)
  updatePasswordField();


  // ---------------------------------------------------------------------------
  // Form submission handler — announces progress to screen readers.
  // ---------------------------------------------------------------------------
  if (uploadForm) {
    uploadForm.addEventListener('submit', function (event) {
      // Validate that a file has been chosen before submitting.
      var fileInput = document.getElementById('exefile');

      if (fileInput && fileInput.files.length === 0) {
        // Prevent the form from submitting if no file is selected.
        event.preventDefault();

        // Announce the error to screen readers via the live region.
        setStatus('Error: Please choose a .exe file before clicking Protect.');

        // Move focus back to the file input so the user can correct the problem.
        fileInput.focus();
        return;
      }

      // Validate that a password was entered when password mode is selected.
      if (radioPassword && radioPassword.checked) {
        var pwd = passwordInput ? passwordInput.value.trim() : '';
        if (pwd === '') {
          // Prevent submission.
          event.preventDefault();

          // Announce the error.
          setStatus('Error: You selected password mode but did not enter a password.');

          // Focus the password input.
          if (passwordInput) { passwordInput.focus(); }
          return;
        }
      }

      // All validation passed — announce that upload is in progress.
      setStatus('Uploading file. Please wait.');

      // Disable the submit button to prevent double-submission.
      if (submitBtn) {
        submitBtn.setAttribute('aria-disabled', 'true');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Uploading…';
      }

      // Announce encryption step after a short delay (simulates progress feedback).
      // In a real implementation this would be based on XMLHttpRequest progress events.
      setTimeout(function () {
        setStatus('Encrypting your file on the server.');
      }, 2000);

      // Announce final assembly step.
      setTimeout(function () {
        setStatus('Building protected exe. Your download will start shortly.');
      }, 4000);

      // Note: the form proceeds with its normal POST submission (we did not
      // call event.preventDefault() after validation passed).
      // The browser will navigate to protect.php and trigger the download.
    });
  }

}); // end DOMContentLoaded
