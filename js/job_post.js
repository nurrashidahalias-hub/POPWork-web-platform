// Character counter for description
const descriptionTextarea = document.getElementById('description');
const charCounter = document.getElementById('charCounter');

descriptionTextarea.addEventListener('input', function() {
  const length = this.value.length;
  const maxLength = 2000;
  charCounter.textContent = `${length} / ${maxLength} characters`;

  // Change color based on length
  if (length > maxLength * 0.9) {
    charCounter.classList.add('warning');
  } else {
    charCounter.classList.remove('warning');
  }

  if (length >= maxLength) {
    charCounter.classList.add('error');
  } else {
    charCounter.classList.remove('error');
  }
});

// Form validation and submission
const form = document.getElementById('jobPostForm');
const submitBtn = document.getElementById('submitBtn');

form.addEventListener('submit', function(e) {
  // Validate pay rate
  const payRate = parseFloat(document.getElementById('pay_rate').value);
  if (payRate <= 0) {
    e.preventDefault();
    alert('Please enter a valid pay rate greater than 0');
    document.getElementById('pay_rate').focus();
    return false;
  }

  // Validate description length
  const description = descriptionTextarea.value;
  if (description.length < 50) {
    e.preventDefault();
    alert('Please provide a more detailed job description (at least 50 characters)');
    descriptionTextarea.focus();
    return false;
  }

  // Add loading state to button
  submitBtn.classList.add('loading');
  submitBtn.querySelector('span').textContent = 'Posting Job...';
  
  // Prevent double submission
  submitBtn.disabled = true;
});

// Format pay rate input
const payRateInput = document.getElementById('pay_rate');
payRateInput.addEventListener('blur', function() {
  if (this.value) {
    const value = parseFloat(this.value);
    if (!isNaN(value)) {
      this.value = value.toFixed(2);
    }
  }
});

// Add input validation feedback
const requiredInputs = document.querySelectorAll('input[required], select[required], textarea[required]');

requiredInputs.forEach(input => {
  input.addEventListener('blur', function() {
    if (!this.value) {
      this.style.borderColor = '#e74c3c';
    } else {
      this.style.borderColor = '#27ae60';
    }
  });

  input.addEventListener('input', function() {
    if (this.value) {
      this.style.borderColor = '#27ae60';
    }
  });
});

// Auto-resize textarea
descriptionTextarea.addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 400) + 'px';
});

// Smooth scroll to top on load
window.addEventListener('load', function() {
  window.scrollTo({ top: 0, behavior: 'smooth' });
});

const skillCheckboxes = document.querySelectorAll('.skill');

// Update description function
function updateDescription() {
  let selectedTemplates = Array.from(templatesSelect.selectedOptions).map(opt => opt.value);
  let selectedResponsibilities = Array.from(responsibilityCheckboxes)
                                      .filter(cb => cb.checked)
                                      .map(cb => cb.value);
  let selectedSkills = Array.from(skillCheckboxes)
                            .filter(cb => cb.checked)
                            .map(cb => cb.value);

  // Combine templates, responsibilities, and skills into bullet points
  let descriptionLines = [];

  // Add templates
  selectedTemplates.forEach(item => descriptionLines.push('• ' + item));

  // Add responsibilities
  selectedResponsibilities.forEach(item => descriptionLines.push('• ' + item));

  // Add skills if any
  if (selectedSkills.length > 0) {
    descriptionLines.push('• Required Skills: ' + selectedSkills.join(', '));
  }

  // Update textarea
  descriptionTextarea.value = descriptionLines.join('\n');
  descriptionTextarea.dispatchEvent(new Event('input')); // update char counter
  formModified = true;
}

// Event listeners
templatesSelect.addEventListener('change', updateDescription);
responsibilityCheckboxes.forEach(cb => cb.addEventListener('change', updateDescription));
skillCheckboxes.forEach(cb => cb.addEventListener('change', updateDescription));
