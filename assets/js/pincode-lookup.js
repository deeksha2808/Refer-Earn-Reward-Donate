/**
 * PIN Code Lookup Module
 * Automatically fetches city, state, and country from a 6-digit Indian PIN code
 * using the free API: https://api.postalpincode.in/pincode/{PINCODE}
 */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-pincode-lookup]').forEach((pincodeInput) => {
    const container = pincodeInput.closest('form') || pincodeInput.closest('.row');
    if (!container) return;

    const cityField = container.querySelector('[data-pincode-city]');
    const stateField = container.querySelector('[data-pincode-state]');
    const countryField = container.querySelector('[data-pincode-country]');
    const districtField = container.querySelector('[data-pincode-district]');
    const feedbackEl = pincodeInput.parentElement.querySelector('.pincode-feedback');
    const spinnerEl = pincodeInput.parentElement.querySelector('.pincode-spinner');

    if (!cityField || !stateField || !countryField) return;

    let debounceTimer = null;
    let lastFetchedPin = '';

    const showSpinner = (visible) => {
      if (spinnerEl) spinnerEl.hidden = !visible;
    };

    const showFeedback = (message, isError) => {
      if (!feedbackEl) return;
      feedbackEl.textContent = message;
      feedbackEl.className = 'pincode-feedback small mt-1 ' + (isError ? 'text-danger' : 'text-success');
      feedbackEl.hidden = !message;
    };

    const clearFeedback = () => {
      if (feedbackEl) { feedbackEl.textContent = ''; feedbackEl.hidden = true; }
    };

    const setFieldValue = (field, value) => {
      if (!field) return;
      field.value = value;
      field.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const fetchPincode = async (pincode) => {
      if (pincode === lastFetchedPin) return;
      lastFetchedPin = pincode;

      showSpinner(true);
      clearFeedback();

      try {
        const response = await fetch(`https://api.postalpincode.in/pincode/${pincode}`);
        if (!response.ok) throw new Error('API request failed');

        const data = await response.json();
        if (!data || !data[0] || data[0].Status !== 'Success' || !data[0].PostOffice || data[0].PostOffice.length === 0) {
          showFeedback('PIN Code not found.', true);
          showSpinner(false);
          return;
        }

        const post = data[0].PostOffice[0];
        setFieldValue(cityField, post.Block && post.Block !== 'NA' ? post.Block : (post.Division || post.District || ''));
        setFieldValue(stateField, post.State || '');
        setFieldValue(countryField, 'India');
        if (districtField) setFieldValue(districtField, post.District || '');

        showFeedback(post.Name + ', ' + post.District + ', ' + post.State, false);
        showSpinner(false);
      } catch (error) {
        showFeedback('Could not fetch PIN Code details. Enter address manually.', true);
        showSpinner(false);
        // Allow manual entry on failure - remove readonly
        [cityField, stateField, countryField, districtField].forEach((f) => {
          if (f) f.removeAttribute('readonly');
        });
      }
    };

    const handlePincodeInput = () => {
      const value = pincodeInput.value.replace(/\D/g, '').slice(0, 6);
      pincodeInput.value = value;

      if (value.length < 6) {
        clearFeedback();
        lastFetchedPin = '';
        return;
      }

      if (value.length === 6) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchPincode(value), 300);
      }
    };

    pincodeInput.addEventListener('input', handlePincodeInput);
    pincodeInput.addEventListener('blur', () => {
      const value = pincodeInput.value.replace(/\D/g, '');
      if (value.length > 0 && value.length < 6) {
        showFeedback('Enter a valid 6-digit PIN Code.', true);
      } else if (value.length === 6 && value !== lastFetchedPin) {
        fetchPincode(value);
      }
    });

    // If pincode is already filled (editing existing profile), trigger lookup
    if (pincodeInput.value.length === 6 && !cityField.value) {
      fetchPincode(pincodeInput.value);
    }
  });
});
