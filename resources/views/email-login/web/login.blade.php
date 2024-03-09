<!DOCTYPE html>
<html lang="{{ config('app.locale') }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __("Login in to the application...") }}</title>
    <style>
        /* ! tailwindcss v3.1.8 | MIT License | https://tailwindcss.com */

        /*
        1. Prevent padding and border from affecting element width. (https://github.com/mozdevs/cssremedy/issues/4)
        2. Allow adding a border to an element by just adding a border-width. (https://github.com/tailwindcss/tailwindcss/pull/116)
        */

        *,
        ::before,
        ::after {
          box-sizing: border-box;
          /* 1 */
          border-width: 0;
          /* 2 */
          border-style: solid;
          /* 2 */
          border-color: #e5e7eb;
          /* 2 */
        }

        ::before,
        ::after {
          --tw-content: '';
        }

        /*
        1. Use a consistent sensible line-height in all browsers.
        2. Prevent adjustments of font size after orientation changes in iOS.
        3. Use a more readable tab size.
        4. Use the user's configured `sans` font-family by default.
        */

        html {
          line-height: 1.5;
          /* 1 */
          -webkit-text-size-adjust: 100%;
          /* 2 */
          -moz-tab-size: 4;
          /* 3 */
          tab-size: 4;
          /* 3 */
          font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
          /* 4 */
        }

        /*
        1. Remove the margin in all browsers.
        2. Inherit line-height from `html` so users can set them as a class directly on the `html` element.
        */

        body {
          margin: 0;
          /* 1 */
          line-height: inherit;
          /* 2 */
        }

        /*
        1. Add the correct height in Firefox.
        2. Correct the inheritance of border color in Firefox. (https://bugzilla.mozilla.org/show_bug.cgi?id=190655)
        3. Ensure horizontal rules are visible by default.
        */

        hr {
          height: 0;
          /* 1 */
          color: inherit;
          /* 2 */
          border-top-width: 1px;
          /* 3 */
        }

        /*
        Add the correct text decoration in Chrome, Edge, and Safari.
        */

        abbr:where([title]) {
          -webkit-text-decoration: underline dotted;
                  text-decoration: underline dotted;
        }

        /*
        Remove the default font size and weight for headings.
        */

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
          font-size: inherit;
          font-weight: inherit;
        }

        /*
        Reset links to optimize for opt-in styling instead of opt-out.
        */

        a {
          color: inherit;
          text-decoration: inherit;
        }

        /*
        Add the correct font weight in Edge and Safari.
        */

        b,
        strong {
          font-weight: bolder;
        }

        /*
        1. Use the user's configured `mono` font family by default.
        2. Correct the odd `em` font sizing in all browsers.
        */

        code,
        kbd,
        samp,
        pre {
          font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
          /* 1 */
          font-size: 1em;
          /* 2 */
        }

        /*
        Add the correct font size in all browsers.
        */

        small {
          font-size: 80%;
        }

        /*
        Prevent `sub` and `sup` elements from affecting the line height in all browsers.
        */

        sub,
        sup {
          font-size: 75%;
          line-height: 0;
          position: relative;
          vertical-align: baseline;
        }

        sub {
          bottom: -0.25em;
        }

        sup {
          top: -0.5em;
        }

        /*
        1. Remove text indentation from table contents in Chrome and Safari. (https://bugs.chromium.org/p/chromium/issues/detail?id=999088, https://bugs.webkit.org/show_bug.cgi?id=201297)
        2. Correct table border color inheritance in all Chrome and Safari. (https://bugs.chromium.org/p/chromium/issues/detail?id=935729, https://bugs.webkit.org/show_bug.cgi?id=195016)
        3. Remove gaps between table borders by default.
        */

        table {
          text-indent: 0;
          /* 1 */
          border-color: inherit;
          /* 2 */
          border-collapse: collapse;
          /* 3 */
        }

        /*
        1. Change the font styles in all browsers.
        2. Remove the margin in Firefox and Safari.
        3. Remove default padding in all browsers.
        */

        button,
        input,
        optgroup,
        select,
        textarea {
          font-family: inherit;
          /* 1 */
          font-size: 100%;
          /* 1 */
          font-weight: inherit;
          /* 1 */
          line-height: inherit;
          /* 1 */
          color: inherit;
          /* 1 */
          margin: 0;
          /* 2 */
          padding: 0;
          /* 3 */
        }

        /*
        Remove the inheritance of text transform in Edge and Firefox.
        */

        button,
        select {
          text-transform: none;
        }

        /*
        1. Correct the inability to style clickable types in iOS and Safari.
        2. Remove default button styles.
        */

        button,
        [type='button'],
        [type='reset'],
        [type='submit'] {
          -webkit-appearance: button;
          /* 1 */
          background-color: transparent;
          /* 2 */
          background-image: none;
          /* 2 */
        }

        /*
        Use the modern Firefox focus style for all focusable elements.
        */

        :-moz-focusring {
          outline: auto;
        }

        /*
        Remove the additional `:invalid` styles in Firefox. (https://github.com/mozilla/gecko-dev/blob/2f9eacd9d3d995c937b4251a5557d95d494c9be1/layout/style/res/forms.css#L728-L737)
        */

        :-moz-ui-invalid {
          box-shadow: none;
        }

        /*
        Add the correct vertical alignment in Chrome and Firefox.
        */

        progress {
          vertical-align: baseline;
        }

        /*
        Correct the cursor style of increment and decrement buttons in Safari.
        */

        ::-webkit-inner-spin-button,
        ::-webkit-outer-spin-button {
          height: auto;
        }

        /*
        1. Correct the odd appearance in Chrome and Safari.
        2. Correct the outline style in Safari.
        */

        [type='search'] {
          -webkit-appearance: textfield;
          /* 1 */
          outline-offset: -2px;
          /* 2 */
        }

        /*
        Remove the inner padding in Chrome and Safari on macOS.
        */

        ::-webkit-search-decoration {
          -webkit-appearance: none;
        }

        /*
        1. Correct the inability to style clickable types in iOS and Safari.
        2. Change font properties to `inherit` in Safari.
        */

        ::-webkit-file-upload-button {
          -webkit-appearance: button;
          /* 1 */
          font: inherit;
          /* 2 */
        }

        /*
        Add the correct display in Chrome and Safari.
        */

        summary {
          display: list-item;
        }

        /*
        Removes the default spacing and border for appropriate elements.
        */

        blockquote,
        dl,
        dd,
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        hr,
        figure,
        p,
        pre {
          margin: 0;
        }

        fieldset {
          margin: 0;
          padding: 0;
        }

        legend {
          padding: 0;
        }

        ol,
        ul,
        menu {
          list-style: none;
          margin: 0;
          padding: 0;
        }

        /*
        Prevent resizing textareas horizontally by default.
        */

        textarea {
          resize: vertical;
        }

        /*
        1. Reset the default placeholder opacity in Firefox. (https://github.com/tailwindlabs/tailwindcss/issues/3300)
        2. Set the default placeholder color to the user's configured gray 400 color.
        */

        input::placeholder,
        textarea::placeholder {
          opacity: 1;
          /* 1 */
          color: #9ca3af;
          /* 2 */
        }

        /*
        Set the default cursor for buttons.
        */

        button,
        [role="button"] {
          cursor: pointer;
        }

        /*
        Make sure disabled buttons don't get the pointer cursor.
        */

        :disabled {
          cursor: default;
        }

        /*
        1. Make replaced elements `display: block` by default. (https://github.com/mozdevs/cssremedy/issues/14)
        2. Add `vertical-align: middle` to align replaced elements more sensibly by default. (https://github.com/jensimmons/cssremedy/issues/14#issuecomment-634934210)
           This can trigger a poorly considered lint error in some tools but is included by design.
        */

        img,
        svg,
        video,
        canvas,
        audio,
        iframe,
        embed,
        object {
          display: block;
          /* 1 */
          vertical-align: middle;
          /* 2 */
        }

        /*
        Constrain images and videos to the parent width and preserve their intrinsic aspect ratio. (https://github.com/mozdevs/cssremedy/issues/14)
        */

        img,
        video {
          max-width: 100%;
          height: auto;
        }

        *, ::before, ::after {
          --tw-border-spacing-x: 0;
          --tw-border-spacing-y: 0;
          --tw-translate-x: 0;
          --tw-translate-y: 0;
          --tw-rotate: 0;
          --tw-skew-x: 0;
          --tw-skew-y: 0;
          --tw-scale-x: 1;
          --tw-scale-y: 1;
          --tw-pan-x:  ;
          --tw-pan-y:  ;
          --tw-pinch-zoom:  ;
          --tw-scroll-snap-strictness: proximity;
          --tw-ordinal:  ;
          --tw-slashed-zero:  ;
          --tw-numeric-figure:  ;
          --tw-numeric-spacing:  ;
          --tw-numeric-fraction:  ;
          --tw-ring-inset:  ;
          --tw-ring-offset-width: 0px;
          --tw-ring-offset-color: #fff;
          --tw-ring-color: rgb(59 130 246 / 0.5);
          --tw-ring-offset-shadow: 0 0 #0000;
          --tw-ring-shadow: 0 0 #0000;
          --tw-shadow: 0 0 #0000;
          --tw-shadow-colored: 0 0 #0000;
          --tw-blur:  ;
          --tw-brightness:  ;
          --tw-contrast:  ;
          --tw-grayscale:  ;
          --tw-hue-rotate:  ;
          --tw-invert:  ;
          --tw-saturate:  ;
          --tw-sepia:  ;
          --tw-drop-shadow:  ;
          --tw-backdrop-blur:  ;
          --tw-backdrop-brightness:  ;
          --tw-backdrop-contrast:  ;
          --tw-backdrop-grayscale:  ;
          --tw-backdrop-hue-rotate:  ;
          --tw-backdrop-invert:  ;
          --tw-backdrop-opacity:  ;
          --tw-backdrop-saturate:  ;
          --tw-backdrop-sepia:  ;
        }

        ::-webkit-backdrop {
          --tw-border-spacing-x: 0;
          --tw-border-spacing-y: 0;
          --tw-translate-x: 0;
          --tw-translate-y: 0;
          --tw-rotate: 0;
          --tw-skew-x: 0;
          --tw-skew-y: 0;
          --tw-scale-x: 1;
          --tw-scale-y: 1;
          --tw-pan-x:  ;
          --tw-pan-y:  ;
          --tw-pinch-zoom:  ;
          --tw-scroll-snap-strictness: proximity;
          --tw-ordinal:  ;
          --tw-slashed-zero:  ;
          --tw-numeric-figure:  ;
          --tw-numeric-spacing:  ;
          --tw-numeric-fraction:  ;
          --tw-ring-inset:  ;
          --tw-ring-offset-width: 0px;
          --tw-ring-offset-color: #fff;
          --tw-ring-color: rgb(59 130 246 / 0.5);
          --tw-ring-offset-shadow: 0 0 #0000;
          --tw-ring-shadow: 0 0 #0000;
          --tw-shadow: 0 0 #0000;
          --tw-shadow-colored: 0 0 #0000;
          --tw-blur:  ;
          --tw-brightness:  ;
          --tw-contrast:  ;
          --tw-grayscale:  ;
          --tw-hue-rotate:  ;
          --tw-invert:  ;
          --tw-saturate:  ;
          --tw-sepia:  ;
          --tw-drop-shadow:  ;
          --tw-backdrop-blur:  ;
          --tw-backdrop-brightness:  ;
          --tw-backdrop-contrast:  ;
          --tw-backdrop-grayscale:  ;
          --tw-backdrop-hue-rotate:  ;
          --tw-backdrop-invert:  ;
          --tw-backdrop-opacity:  ;
          --tw-backdrop-saturate:  ;
          --tw-backdrop-sepia:  ;
        }

        ::backdrop {
          --tw-border-spacing-x: 0;
          --tw-border-spacing-y: 0;
          --tw-translate-x: 0;
          --tw-translate-y: 0;
          --tw-rotate: 0;
          --tw-skew-x: 0;
          --tw-skew-y: 0;
          --tw-scale-x: 1;
          --tw-scale-y: 1;
          --tw-pan-x:  ;
          --tw-pan-y:  ;
          --tw-pinch-zoom:  ;
          --tw-scroll-snap-strictness: proximity;
          --tw-ordinal:  ;
          --tw-slashed-zero:  ;
          --tw-numeric-figure:  ;
          --tw-numeric-spacing:  ;
          --tw-numeric-fraction:  ;
          --tw-ring-inset:  ;
          --tw-ring-offset-width: 0px;
          --tw-ring-offset-color: #fff;
          --tw-ring-color: rgb(59 130 246 / 0.5);
          --tw-ring-offset-shadow: 0 0 #0000;
          --tw-ring-shadow: 0 0 #0000;
          --tw-shadow: 0 0 #0000;
          --tw-shadow-colored: 0 0 #0000;
          --tw-blur:  ;
          --tw-brightness:  ;
          --tw-contrast:  ;
          --tw-grayscale:  ;
          --tw-hue-rotate:  ;
          --tw-invert:  ;
          --tw-saturate:  ;
          --tw-sepia:  ;
          --tw-drop-shadow:  ;
          --tw-backdrop-blur:  ;
          --tw-backdrop-brightness:  ;
          --tw-backdrop-contrast:  ;
          --tw-backdrop-grayscale:  ;
          --tw-backdrop-hue-rotate:  ;
          --tw-backdrop-invert:  ;
          --tw-backdrop-opacity:  ;
          --tw-backdrop-saturate:  ;
          --tw-backdrop-sepia:  ;
        }

        .relative {
          position: relative;
        }

        .mx-auto {
          margin-left: auto;
          margin-right: auto;
        }

        .mb-4 {
          margin-bottom: 1rem;
        }

        .ml-2 {
          margin-left: 0.5rem;
        }

        .inline-block {
          display: inline-block;
        }

        .flex {
          display: flex;
        }

        .hidden {
          display: none;
        }

        .h-5 {
          height: 1.25rem;
        }

        .min-h-screen {
          min-height: 100vh;
        }

        .w-5 {
          width: 1.25rem;
        }

        .max-w-md {
          max-width: 28rem;
        }

        .translate-y-0 {
          --tw-translate-y: 0px;
          transform: translate(var(--tw-translate-x), var(--tw-translate-y)) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y));
        }

        .transform-gpu {
          transform: translate3d(var(--tw-translate-x), var(--tw-translate-y), 0) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y));
        }

        @keyframes spin {
          to {
            transform: rotate(360deg);
          }
        }

        .animate-spin {
          animation: spin 1s linear infinite;
        }

        .flex-col {
          flex-direction: column;
        }

        .justify-center {
          justify-content: center;
        }

        .overflow-hidden {
          overflow: hidden;
        }

        .rounded-lg {
          border-radius: 0.5rem;
        }

        .border-b {
          border-bottom-width: 1px;
        }

        .border-b-slate-200 {
          --tw-border-opacity: 1;
          border-bottom-color: rgb(226 232 240 / var(--tw-border-opacity));
        }

        .bg-slate-200 {
          --tw-bg-opacity: 1;
          background-color: rgb(226 232 240 / var(--tw-bg-opacity));
        }

        .bg-slate-50 {
          --tw-bg-opacity: 1;
          background-color: rgb(248 250 252 / var(--tw-bg-opacity));
        }

        .bg-blue-700 {
          --tw-bg-opacity: 1;
          background-color: rgb(29 78 216 / var(--tw-bg-opacity));
        }

        .py-6 {
          padding-top: 1.5rem;
          padding-bottom: 1.5rem;
        }

        .px-6 {
          padding-left: 1.5rem;
          padding-right: 1.5rem;
        }

        .px-2 {
          padding-left: 0.5rem;
          padding-right: 0.5rem;
        }

        .py-3 {
          padding-top: 0.75rem;
          padding-bottom: 0.75rem;
        }

        .px-5 {
          padding-left: 1.25rem;
          padding-right: 1.25rem;
        }

        .pt-10 {
          padding-top: 2.5rem;
        }

        .pb-8 {
          padding-bottom: 2rem;
        }

        .pb-4 {
          padding-bottom: 1rem;
        }

        .text-center {
          text-align: center;
        }

        .text-2xl {
          font-size: 1.5rem;
          line-height: 2rem;
        }

        .text-slate-600 {
          --tw-text-opacity: 1;
          color: rgb(71 85 105 / var(--tw-text-opacity));
        }

        .text-blue-800 {
          --tw-text-opacity: 1;
          color: rgb(30 64 175 / var(--tw-text-opacity));
        }

        .text-blue-50 {
          --tw-text-opacity: 1;
          color: rgb(239 246 255 / var(--tw-text-opacity));
        }

        .underline {
          -webkit-text-decoration-line: underline;
                  text-decoration-line: underline;
        }

        .opacity-25 {
          opacity: 0.25;
        }

        .opacity-75 {
          opacity: 0.75;
        }

        .shadow-xl {
          --tw-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
          --tw-shadow-colored: 0 20px 25px -5px var(--tw-shadow-color), 0 8px 10px -6px var(--tw-shadow-color);
          box-shadow: var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow);
        }

        .shadow-md {
          --tw-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
          --tw-shadow-colored: 0 4px 6px -1px var(--tw-shadow-color), 0 2px 4px -2px var(--tw-shadow-color);
          box-shadow: var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow);
        }

        .shadow-slate-900\/30 {
          --tw-shadow-color: rgb(15 23 42 / 0.3);
          --tw-shadow: var(--tw-shadow-colored);
        }

        .ring-gray-900\/5 {
          --tw-ring-color: rgb(17 24 39 / 0.05);
        }

        .transition-colors {
          transition-property: color, background-color, border-color, fill, stroke, -webkit-text-decoration-color;
          transition-property: color, background-color, border-color, text-decoration-color, fill, stroke;
          transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, -webkit-text-decoration-color;
          transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
          transition-duration: 150ms;
        }

        .after\:pointer-events-none::after {
          content: var(--tw-content);
          pointer-events: none;
        }

        .after\:absolute::after {
          content: var(--tw-content);
          position: absolute;
        }

        .after\:inset-0::after {
          content: var(--tw-content);
          top: 0px;
          right: 0px;
          bottom: 0px;
          left: 0px;
        }

        .after\:ring-1::after {
          content: var(--tw-content);
          --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
          --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(1px + var(--tw-ring-offset-width)) var(--tw-ring-color);
          box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
        }

        .after\:ring-slate-900\/10::after {
          content: var(--tw-content);
          --tw-ring-color: rgb(15 23 42 / 0.1);
        }

        .hover\:bg-blue-800:hover {
          --tw-bg-opacity: 1;
          background-color: rgb(30 64 175 / var(--tw-bg-opacity));
        }

        .active\:translate-y-1:active {
          --tw-translate-y: 0.25rem;
          transform: translate(var(--tw-translate-x), var(--tw-translate-y)) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y));
        }

        .active\:bg-blue-900:active {
          --tw-bg-opacity: 1;
          background-color: rgb(30 58 138 / var(--tw-bg-opacity));
        }

        .active\:shadow:active {
          --tw-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
          --tw-shadow-colored: 0 1px 3px 0 var(--tw-shadow-color), 0 1px 2px -1px var(--tw-shadow-color);
          box-shadow: var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow);
        }

        .disabled\:cursor-wait:disabled {
          cursor: wait;
        }

        .disabled\:shadow-none:disabled {
          --tw-shadow: 0 0 #0000;
          --tw-shadow-colored: 0 0 #0000;
          box-shadow: var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow);
        }

        .disabled\:ring-inset:disabled {
          --tw-ring-inset: inset;
        }

        .active\:disabled\:translate-y-0:disabled:active {
          --tw-translate-y: 0px;
          transform: translate(var(--tw-translate-x), var(--tw-translate-y)) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y));
        }

        @media (min-width: 640px) {
          .sm\:mx-auto {
            margin-left: auto;
            margin-right: auto;
          }

          .sm\:max-w-lg {
            max-width: 32rem;
          }

          .sm\:rounded-lg {
            border-radius: 0.5rem;
          }

          .sm\:py-12 {
            padding-top: 3rem;
            padding-bottom: 3rem;
          }

          .sm\:px-10 {
            padding-left: 2.5rem;
            padding-right: 2.5rem;
          }

          .after\:sm\:rounded-lg::after {
            content: var(--tw-content);
            border-radius: 0.5rem;
          }
        }
    </style>
    <style>
        #login.autologin {
            position: relative;
            overflow: hidden;
        }

        #login.autologin::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            animation-name: fill;
            animation-duration: 5s;
            animation-iteration-count: 1;
            animation-fill-mode: forwards;
        }

        @keyframes fill {
            from {
                width: 0;
                background-color: rgba(0, 255, 0, 0);
            }
            to {
                width: 100%;
                background-color: rgba(0, 255, 0, 0.5);
            }
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // If there is any error, show the HTML and do nothing else.
            if ({{ $errors->any() ? 'true' : 'false' }}) {
                document.getElementById("error").classList.remove("hidden");

                return;
            }

            const form = document.getElementById("enabled")
            const button = document.getElementById("login")

            // Auto log in the user after 5 seconds
            let timeout = timeout = setTimeout(() => button.click(), 5000)

            // Add the CSS animation.
            button.classList.add("autologin")

            // When the submission is received, remove the auto-log in.
            form.addEventListener("submit", () => {
                button.disabled = true
                document.getElementById("go").classList.add("hidden")
                document.getElementById("wait").classList.remove("hidden")
                if (timeout) {
                    clearTimeout(timeout);
                }
            })
        })
    </script>
</head>
<body>
<div class="relative flex min-h-screen flex-col justify-center overflow-hidden bg-slate-200 py-6 sm:py-12">
    <div class="relative bg-slate-50 px-6 pt-10 pb-8 shadow-xl after:pointer-events-none after:absolute after:inset-0 after:sm:rounded-lg after:ring-1 after:ring-slate-900/10 sm:mx-auto sm:max-w-lg sm:rounded-lg sm:px-10">
        <div class="mx-auto max-w-md">
            <h1 class="mb-4 border-b border-b-slate-200 pb-4 text-center text-2xl text-slate-600">Log in</h1>

            <div id="error" class="hidden text-center">
                <p class="mb-4">The login link you have requested is invalid or has expired.</p>
                <a href="{{ config('app.url') }}" target="_self" class="px-2 py-3 text-blue-800 underline">&laquo; Go back to the application</a>
            </div>

            <form method="post" id="enabled" class="text-md text-center">
                @csrf
                <p class="mb-4">You're here because you received an email to login. Just click the link below or wait some seconds.</p>
                <button id="login" type="submit" class="inline-block translate-y-0 transform-gpu rounded-lg bg-blue-700 px-5 py-3 text-blue-50 shadow-md shadow-slate-900/30 ring-gray-900/5 transition-colors hover:bg-blue-800 active:bg-blue-900 active:shadow active:translate-y-1 active:disabled:translate-y-0 disabled:shadow-none disabled:cursor-wait disabled:ring-inset">
                    Login to the application
                    <span id="go" class="inline-block ml-2 h-5 w-5">→</span>
                    <span id="wait" class="hidden">
                        <svg class="inline-block animate-spin ml-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
