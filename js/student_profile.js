// Filter panel toggle
document.getElementById('toggleFilters')?.addEventListener('click', function() {
  const panel = document.getElementById('filterPanel');
  if (panel.style.display === 'none') {
    panel.style.display = 'block';
    this.textContent = 'Hide Filters';
  } else {
    panel.style.display = 'none';
    this.textContent = 'Show Filters';
  }
});

// Modal close on overlay click
document.getElementById('studentModal')?.addEventListener('click', function(e) {
  if (e.target === this) {
    window.location.href = 'student_profile.php';
  }
});

// Auto-hide toast
setTimeout(() => {
  const t = document.getElementById('toast');
  if (t) {
    t.style.opacity = '0';
    setTimeout(() => {
      if (t.parentNode) t.parentNode.removeChild(t);
    }, 300);
  }
}, 4000);

/**
 * FEED System - Student Profiles Modal Interactions
 * Plain JS, no frameworks
 */

document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('studentModal');
  const addBtn = document.querySelector('.header-actions .btn-primary');
  const cancelBtn = modal?.querySelector('.btn-cancel');
  const form = modal?.querySelector('form');
  const toast = document.getElementById('toast');

  // ===== OPEN MODAL =====
  // Via "Add Student" button click
  addBtn?.addEventListener('click', function(e) {
    e.preventDefault();
    openModal();
  });

  // Via URL param ?add=1 (PHP already handles this, but JS ensures display)
  if (new URLSearchParams(window.location.search).has('add')) {
    openModal();
  }

  function openModal() {
    if (!modal) return;
    modal.style.display = 'flex';
    // Trigger reflow for animation
    void modal.offsetWidth;
    modal.classList.add('active');
    // Focus first input
    const firstInput = modal.querySelector('input:not([type="hidden"])');
    firstInput?.focus();
  }

  // ===== CLOSE MODAL =====
  function closeModal() {
    if (!modal) return;
    modal.classList.remove('active');
    // Wait for transition, then hide
    setTimeout(() => {
      modal.style.display = 'none';
    }, 200);
    // Clean URL without reload
    const url = new URL(window.location);
    url.searchParams.delete('add');
    url.searchParams.delete('edit');
    window.history.replaceState({}, '', url);
  }

  // Close on overlay click
  modal?.addEventListener('click', function(e) {
    if (e.target === modal) closeModal();
  });

  // Close on Cancel button
  cancelBtn?.addEventListener('click', function(e) {
    e.preventDefault();
    closeModal();
  });

  // Close on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal?.style.display === 'flex') {
      closeModal();
    }
  });

  // ===== FORM SUBMISSION FEEDBACK =====
  form?.addEventListener('submit', function() {
    // Disable button to prevent double-submit
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Saving...';
    }
  });

  // ===== TOAST AUTO-HIDE =====
  if (toast) {
    setTimeout(() => {
      toast.style.animation = 'toastSlideIn 0.3s ease reverse';
      setTimeout(() => {
        toast.style.display = 'none';
      }, 300);
    }, 4000);
    // Manual close
    toast.querySelector('button')?.addEventListener('click', function() {
      toast.style.display = 'none';
    });
  }

  // ===== FILTER PANEL TOGGLE (Bonus, since you have the button) =====
  const toggleFiltersBtn = document.getElementById('toggleFilters');
  const filterPanel = document.getElementById('filterPanel');
  
  toggleFiltersBtn?.addEventListener('click', function() {
    if (!filterPanel) return;
    const isHidden = filterPanel.style.display === 'none' || !filterPanel.style.display;
    filterPanel.style.display = isHidden ? 'block' : 'none';
    this.textContent = isHidden ? 'Hide Filters' : 'Show Filters';
  });
});