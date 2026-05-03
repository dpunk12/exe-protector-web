# ExeShield Accessibility Documentation

This document describes every accessibility feature built into ExeShield. It is written for screen-reader users, low-vision users, and developers who want to understand or extend the accessibility implementation.

---

## Summary of accessibility features

ExeShield implements the following accessibility features across its web application and source code.

---

## Semantic HTML structure

The upload page (`index.php`) uses the following semantic HTML elements so assistive technology can navigate the page by structure rather than visual layout.

The page has a single `<h1>` heading containing the site name "ExeShield". There is one `<h2>` heading for the upload form section and one `<h2>` heading for the "How it works" section. There is an `<h3>` heading for the technical details subsection. This hierarchy lets screen-reader users press H to jump between headings and get an overview of the page without reading every word.

Every form input has a `<label>` element whose `for` attribute value matches the input's `id` attribute. This means that when a screen reader focuses an input, it automatically reads the label text. There are no unlabeled inputs on this page.

The radio button group is wrapped in a `<fieldset>` element with a `<legend>`. Screen readers announce the legend text before each radio option, so the user always hears "Protection mode required: Auto-generated key" rather than just "Auto-generated key" with no context.

The page uses HTML5 landmark elements: `<header role="banner">`, `<main role="main">`, and `<footer role="contentinfo">`. Screen-reader users can navigate between landmarks using their reader's shortcut key (typically R in NVDA, or the Rotor in VoiceOver).

---

## ARIA live region for progress announcements

The upload form's progress announcements are delivered through an ARIA live region: a `<div>` with `role="status"` and `aria-live="polite"` and `aria-atomic="true"`.

When the user clicks the Protect button, the JavaScript updates the text content of this div with messages such as "Uploading file. Please wait.", "Encrypting your file on the server.", and "Building protected exe. Your download will start shortly."

The `aria-live="polite"` attribute means the screen reader waits until the user is idle before reading the new message, so it does not interrupt any other announcement in progress. The `aria-atomic="true"` attribute means the reader announces the entire text content of the div as a single announcement, not just the changed portion.

If JavaScript is disabled, the form still submits normally and the browser navigates to the protect.php response page, so no live-region announcements are needed.

---

## Password field accessibility

The password field is always present in the HTML even when the "auto-generated key" radio is selected. This ensures screen-reader users can discover the field and understand its purpose.

When the auto-generated key radio is selected, JavaScript sets `aria-disabled="true"` on the password input (not `disabled="disabled"`). This approach is intentional. Using `disabled="disabled"` would hide the field from the accessibility tree in some browser-reader combinations, preventing the user from knowing the field exists. Using `aria-disabled="true"` keeps the field discoverable and allows the reader to announce it as "grayed out" or "dimmed" depending on the reader.

The CSS class `field-inactive` applies visual de-emphasis (reduced opacity) to match the `aria-disabled` state, so the visual and semantic states are always consistent.

When the user switches to password mode, JavaScript removes `aria-disabled`, removes the `field-inactive` class, and moves keyboard focus to the password input so the user is immediately positioned there.

---

## Focus management

The skip link at the top of the page allows keyboard users to jump past the header directly to the main content. The link text is "Skip to main content". The link is visually hidden until it receives keyboard focus, at which point it appears at the top of the screen.

All interactive elements (buttons, inputs, links, radio buttons) have clearly visible focus rings: a 3-pixel solid cyan outline with a 2-pixel offset. This exceeds the WCAG 2.1 Level AA focus indicator requirements.

When the password radio option is selected, focus moves automatically to the password input field so keyboard-only users do not need to navigate there manually.

---

## Contrast ratios

The color palette was chosen to meet or exceed WCAG 2.1 Level AA contrast requirements.

Primary text color `#e6edf3` on background `#0a0e14` has a contrast ratio of approximately 14.1:1, well above the 4.5:1 minimum for normal text.

Accent cyan `#00e5ff` on the dark background `#0a0e14` has a contrast ratio of approximately 10.3:1.

The focus ring color `#00e5ff` against the dark background has a contrast ratio of approximately 10.3:1, exceeding the 3:1 minimum for non-text contrast.

Button text (dark `#0a0e14`) on cyan `#00e5ff` button background has a contrast ratio of approximately 10.3:1.

Hint text color `#8b949e` on `#141924` has a contrast ratio of approximately 4.6:1, just above the 4.5:1 minimum.

---

## Reduced motion support

ExeShield respects the operating system accessibility setting "Reduce motion" (also called "Prefer reduced motion"). On Windows this is found in Settings, then Accessibility, then Visual Effects. On macOS it is in System Settings, then Accessibility, then Display.

When this setting is enabled, the CSS `prefers-reduced-motion: reduce` media query fires and all CSS transitions and animations are set to a near-zero duration (0.001 milliseconds). This prevents motion-triggered discomfort for users with vestibular disorders.

---

## High contrast mode support

ExeShield includes a `prefers-contrast: more` CSS media query that activates when the user enables the "Increase contrast" accessibility setting.

In this mode, the background becomes pure black (`#000000`), text becomes pure white (`#ffffff`), hint text becomes light gray (`#cccccc`), the accent cyan becomes fully saturated (`#00ffff`), and border widths increase from 2 pixels to 3 pixels on inputs and buttons.

Windows High Contrast Mode is also supported. Because ExeShield uses standard HTML form elements with standard colors and the `accent-color` CSS property, Windows High Contrast Mode overrides the visual styling to match the user's chosen high-contrast theme.

---

## Keyboard navigation

All interactive elements on the page are reachable and operable with the keyboard alone. The Tab order follows a logical reading order: skip link, heading, status region, form fields (file input, radio buttons, password input, submit button), how-it-works section, footer links.

The file input opens the system file picker when activated with Space or Enter. The submit button activates with Space or Enter. The radio buttons respond to arrow keys for navigation within the group.

---

## Minimum touch target size

All buttons, inputs, and radio buttons have a minimum height and width of 48 pixels to meet the WCAG 2.5.5 Target Size guideline. This makes the controls easier to tap on touchscreen devices and easier to click for users with limited fine motor control.

---

## How the upload flow sounds to screen-reader users

This section describes what a screen-reader user hears when using ExeShield with a reader such as NVDA, JAWS, or VoiceOver.

When the page loads, the reader typically announces the page title "ExeShield — Protect Your Windows EXE" and then reads the first focusable element, which is the skip link "Skip to main content".

After pressing Tab past the skip link and header, the reader focuses the file input and announces: "Choose your Windows dot exe file (required), file upload, required. Maximum file size: 50 MB. Only .exe files are accepted."

After selecting a file, the reader returns focus to the form. The user presses Tab to reach the protection mode fieldset. The reader announces: "Protection mode (required): group." Then for the first radio option: "Auto-generated key (no password needed), radio button, checked, 1 of 2." For the second: "Set my own password (exe will ask for it at runtime), radio button, not checked, 2 of 2."

If the user activates the password radio option, the reader announces the selection, then focus moves to the password input, which the reader announces as: "Password for runtime decryption (only needed if you chose Set my own password above), edit text."

When the user activates the submit button, the live region updates and the reader announces: "Uploading file. Please wait." Two seconds later: "Encrypting your file on the server." Two seconds after that: "Building protected exe. Your download will start shortly." The browser then begins the file download.

---

## How to increase font size in the browser

ExeShield uses relative font size units (rem and em) that respect the browser's base font size setting. To increase font size:

In Google Chrome or Microsoft Edge: press Control and plus to zoom in, or go to Settings, then Appearance, then Font Size.

In Mozilla Firefox: press Control and plus, or go to Settings, then General, then Language and Appearance, then Fonts and Colors.

In Safari on macOS: press Command and plus, or go to Safari menu, then Preferences, then Advanced, then Accessibility, then Never use font sizes smaller than.

Increasing zoom or font size in any of these browsers will scale all ExeShield text proportionally.

---

## How to enable high-contrast mode in the browser

In Windows, press the left Alt, left Shift, and Print Screen keys simultaneously to toggle Windows High Contrast Mode. Or go to Settings, then Accessibility, then Contrast Themes, and choose a theme.

In macOS, go to System Settings, then Accessibility, then Display, and enable "Increase Contrast".

ExeShield's CSS responds to both the Windows High Contrast Mode and the `prefers-contrast: more` CSS media query, so enabling either setting will increase contrast on the ExeShield pages.

---

## Notes for screen-reader users on the source code

The `stub.py` Python file contains heavy line-by-line comments. Every function has a multi-line docstring explaining its purpose, parameters, and return values. Every meaningful code block has a comment on the line or the line above explaining what it does. Variable names are descriptive.

The PHP files (`protect.php`, `index.php`) follow the same commenting philosophy. Block comments explain each major step, and inline comments explain individual lines that might not be obvious.

The CSS file (`style.css`) has section headings as block comments and inline comments on every property group that is accessibility-related.

The JavaScript file (`script.js`) has line-by-line comments explaining every function and event handler.

This commenting style was chosen specifically so the repository owner can listen to the source code with a screen reader and understand what each line does without needing to visually scan the code structure.
