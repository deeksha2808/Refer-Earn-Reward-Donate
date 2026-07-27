document.addEventListener('DOMContentLoaded', () => {
  ['keydown', 'paste', 'beforeinput'].forEach((type) => document.addEventListener(type, (event) => {
    if (event.target.matches('.no-autofill[readonly]')) event.target.removeAttribute('readonly');
  }));
  document.querySelectorAll('.toast').forEach((element) => new bootstrap.Toast(element).show());
  document.querySelectorAll('.needs-validation').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const password = form.querySelector('#password');
      const confirmation = form.querySelector('#confirm_password');
      if (password && confirmation && password.value !== confirmation.value) confirmation.setCustomValidity('Passwords do not match');
      else if (confirmation) confirmation.setCustomValidity('');
      if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
      else document.querySelector('.page-loader')?.classList.add('active');
      form.classList.add('was-validated');
    });
  });
  document.querySelectorAll('form[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  }));
  document.querySelectorAll('.profile-wizard').forEach((wizard) => {
    let current = 1;
    const steps = [...wizard.querySelectorAll('.wizard-step')];
    const progress = [...wizard.querySelectorAll('.wizard-progress span')];
    const back = wizard.querySelector('.wizard-back');
    const next = wizard.querySelector('.wizard-next');
    const submit = wizard.querySelector('.wizard-submit');
    const actions = wizard.querySelector('[data-wizard-actions], .wizard-actions');
    if (!steps.length || !back || !next || !submit || !actions) return;
    const show = (step) => {
      current = Math.min(Math.max(step, 1), steps.length);
      steps.forEach((item) => {
        const active = Number(item.dataset.step) === current;
        item.classList.toggle('active', active);
      });
      // Keep navigation as a direct child of the form. Moving it into an
      // individual step makes it disappear whenever that step is hidden.
      progress.forEach((item, index) => item.classList.toggle('active', index < current));
      back.hidden = current === 1;
      next.hidden = current === steps.length;
      submit.hidden = current !== steps.length;
    };
    next.addEventListener('click', () => {
      const fields = [...wizard.querySelectorAll(`.wizard-step[data-step="${current}"] input, .wizard-step[data-step="${current}"] textarea, .wizard-step[data-step="${current}"] select`)];
      const categories = fields.filter((field) => field.name === 'service_categories[]');
      if (categories.length) categories[0].setCustomValidity(categories.some((field) => field.checked) ? '' : 'Choose at least one service category.');
      if (!fields.every((field) => field.checkValidity())) { fields.forEach((field) => field.reportValidity()); return; }
      show(current + 1);
    });
    back.addEventListener('click', () => show(current - 1));
    wizard.querySelectorAll('input[type="file"][data-preview]').forEach((input) => input.addEventListener('change', () => {
      const preview = document.getElementById(input.dataset.preview); const file = input.files[0];
      if (preview && file && file.type.startsWith('image/')) { preview.src = URL.createObjectURL(file); preview.hidden = false; }
    }));
    show(1);
  });
  const appendRequiredStar = (label) => {
    if (!label || label.querySelector('.required-star')) return;
    const star = document.createElement('span');
    star.className = 'text-danger required-star';
    star.setAttribute('aria-hidden', 'true');
    star.textContent = ' *';
    label.appendChild(star);
  };
  document.querySelectorAll('.required-label').forEach(appendRequiredStar);
  document.querySelectorAll('input[required], select[required], textarea[required]').forEach((field) => {
    if (field.type === 'radio' || field.type === 'checkbox') return;
    const label = field.id ? document.querySelector(`label[for="${field.id}"]`) : null;
    appendRequiredStar(label || field.closest('.upload-card')?.querySelector('strong') || field.parentElement?.querySelector(':scope > label'));
  });

  document.querySelectorAll('[data-other-target]').forEach((field) => {
    const target = document.querySelector(field.dataset.otherTarget);
    if (!target) return;
    const input = target.querySelector('input');
    const toggle = () => {
      const show = field.value === 'Other' || (field.type === 'checkbox' && field.checked);
      target.hidden = !show;
      if (input) {
        input.disabled = !show;
        input.required = show;
      }
    };
    field.addEventListener('change', toggle);
    toggle();
  });

  document.querySelectorAll('[data-category-control]').forEach((field) => {
    field.addEventListener('change', () => {
      if (field.value === 'Other') {
        field.value = '';
        field.focus();
      }
    });
  });

  document.querySelectorAll('select[data-location-country]').forEach((countrySelect) => {
    const stateSelect = document.querySelector(countrySelect.dataset.locationState);
    const citySelect = document.querySelector(countrySelect.dataset.locationCity);
    const map = JSON.parse(countrySelect.dataset.locationMap || '{}');
    if (!stateSelect || !citySelect) return;
    const option = (value, label = value) => {
      const element = document.createElement('option');
      element.value = value;
      element.textContent = label;
      return element;
    };
    const populate = (select, values, placeholder, selected) => {
      select.replaceChildren(option('', placeholder));
      values.forEach((value) => select.appendChild(option(value)));
      if (selected && !values.includes(selected)) select.appendChild(option(selected));
      select.value = selected || '';
    };
    const populateCities = (selected = '') => {
      const cities = map[countrySelect.value]?.[stateSelect.value] || [];
      populate(citySelect, [...cities, 'Other'], 'Choose a city', selected);
      citySelect.dispatchEvent(new Event('change'));
    };
    const populateStates = (selected = '', city = '') => {
      populate(stateSelect, Object.keys(map[countrySelect.value] || {}), 'Choose a state', selected);
      populateCities(city);
    };
    const initialState = stateSelect.dataset.selected || '';
    const initialCity = citySelect.dataset.selected || '';
    countrySelect.addEventListener('change', () => populateStates('', ''));
    stateSelect.addEventListener('change', () => populateCities(''));
    populateStates(initialState, initialCity);
  });

  document.querySelectorAll('[data-instant-search]').forEach((input) => {
    let timer;
    input.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => input.form?.requestSubmit(), 450);
    });
  });
});
