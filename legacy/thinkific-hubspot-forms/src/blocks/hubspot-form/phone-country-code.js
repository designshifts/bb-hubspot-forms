/*
    In order to prevent heavy import loads on everypage and because forms can come up on any page 
    and this option will check if the phone country selector is present and if it is, then inject the code. 
    Injecting header is not the best way keep load times managable is important
*/ 
document.addEventListener("DOMContentLoaded", function () {
  const inputs = document.querySelectorAll('[data-country-select="true"]');

  if (inputs.length > 0) {
    // Inject CSS
    const link = document.createElement("link");
    link.rel = "stylesheet";
    link.href = "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/css/intlTelInput.css";
    document.head.appendChild(link);

    // Inject intl-tel-input script
    const script = document.createElement("script");
    script.src = "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/intlTelInput.min.js";

    script.onload = () => {
      inputs.forEach((input) => {
        // Store the original required attributes before intl-tel-input initializes
        const originalLabel = input.closest('label');
        const wasRequired = input.hasAttribute('required');
        const wasAriaRequired = input.getAttribute('aria-required') === 'true';

        const itiInstance = window.intlTelInput(input, {
          loadUtils: () => import("https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"),
          initialCountry: "us",
          countryOrder: ["us", "ca", "gb", "au"],
          separateDialCode: true,
          formatOnDisplay: true,
          nationalMode: false,
          autoPlaceholder: "polite"
        });

        input._iti = itiInstance;

        // Ensure the required attributes are preserved after intl-tel-input initializes
        if (wasRequired) {
          input.setAttribute('required', 'required');
        }
        if (wasAriaRequired) {
          input.setAttribute('aria-required', 'true');
        }

        // Add a class to the label to ensure the asterisk shows up
        if (originalLabel && (wasRequired || wasAriaRequired)) {
          originalLabel.classList.add('has-required-field');
          
          // Only add the asterisk directly if CSS ::after isn't working
          // Wait a bit to see if the CSS ::after pseudo-element shows up
          setTimeout(() => {
            const labelText = originalLabel.querySelector('.label-text');
            if (labelText && !labelText.textContent.includes('*') && !labelText.querySelector('.required-asterisk')) {
              // Check if the ::after pseudo-element is visible by checking computed styles
              const computedStyle = window.getComputedStyle(labelText, '::after');
              const hasAfterContent = computedStyle.content && computedStyle.content !== 'none' && computedStyle.content !== '""';
              
              if (!hasAfterContent) {
                labelText.innerHTML += ' <span class="required-asterisk">*</span>';
              }
            }
          }, 100);
        }

        const getSelectedCountryElement = () =>
          input.closest(".iti")?.querySelector(".iti__selected-country");

        input.addEventListener("input", () => {
          const selectedCountry = getSelectedCountryElement();
          if (!input.classList.contains("input-error")) {
            selectedCountry?.classList.remove("has-phone-error");
          }
        });

        input.addEventListener("blur", () => {
          const selectedCountry = getSelectedCountryElement();
          if (input.classList.contains("input-error")) {
            selectedCountry?.classList.add("has-phone-error");
          } else {
            selectedCountry?.classList.remove("has-phone-error");
          }
        });
      });
    };

    document.head.appendChild(script);
  }
});
