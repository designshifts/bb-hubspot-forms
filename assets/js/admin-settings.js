(function () {
  function setResult(message, isSuccess) {
    var result = document.getElementById("bb-hubspot-forms-test-result");
    if (!result) {
      return;
    }
    result.textContent = message;
    result.style.color = isSuccess ? "#0a6b2c" : "#b32d2e";
  }

  function handleClick() {
    if (!window.bbHubspotFormsSettings) {
      return;
    }

    var portalField = document.querySelector(
      'input[name="bb_hubspot_forms_settings[portal_id]"]'
    );
    var tokenField = document.querySelector(
      'input[name="bb_hubspot_forms_settings[private_token]"]'
    );

    var button = document.getElementById("bb-hubspot-forms-test-connection");
    if (button) {
      button.disabled = true;
    }
    setResult("Testing connection...", true);

    fetch(window.bbHubspotFormsSettings.restUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": window.bbHubspotFormsSettings.nonce,
      },
      body: JSON.stringify({
        portal_id: portalField ? portalField.value : "",
        private_token: tokenField ? tokenField.value : "",
      }),
    })
      .then(function (response) {
        return response.json().then(function (data) {
          return { status: response.status, data: data };
        });
      })
      .then(function (result) {
        if (result.status >= 200 && result.status < 300 && result.data.success) {
          setResult(
            result.data.message ||
              "Connected to HubSpot successfully. Remember to save your changes.",
            true
          );
          return;
        }
        var errorMessage =
          (result.data && result.data.error) ||
          (result.data && result.data.message) ||
          "Unable to connect. Please check your access token and required scopes.";
        setResult(errorMessage, false);
      })
      .catch(function () {
        setResult("Unable to connect. Please check your access token and required scopes.", false);
      })
      .finally(function () {
        if (button) {
          button.disabled = false;
        }
      });
  }

  document.addEventListener("DOMContentLoaded", function () {
    var button = document.getElementById("bb-hubspot-forms-test-connection");
    if (button) {
      button.addEventListener("click", handleClick);
    }
  });
})();
