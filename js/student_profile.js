document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('studentModal');
  const addBtn = document.querySelector('.header-actions .btn-primary');
  const cancelBtn = modal?.querySelector('.btn-cancel');
  const form = modal?.querySelector('form');
  const toast = document.getElementById('toast');
  const searchInput = document.getElementById('liveSearch');
  const searchForm = document.getElementById('searchForm');
  const filterSearch = document.getElementById('filterSearch');
  const toggleFiltersBtn = document.getElementById('toggleFilters');
  const filterButtonText = document.getElementById('filterButtonText');
  const filterPanel = document.getElementById('filterPanel');
  let searchTimer = null;

  function liveSearchTable() {
    const rows = document.querySelectorAll('table tbody tr');
    const searchTerm = (searchInput?.value || '').toLowerCase().trim();

    rows.forEach(row => {
      if (row.textContent.includes('No students found')) {
        row.style.display = '';
        return;
      }

      const lrnCell = row.cells[0]?.textContent.toLowerCase().trim() || '';
      const nameCell = row.cells[1]?.textContent.toLowerCase().trim() || '';
      row.style.display = !searchTerm || lrnCell.includes(searchTerm) || nameCell.includes(searchTerm) ? '' : 'none';
    });
  }

  function submitSearch() {
    if (!searchForm) return;
    if (filterSearch) {
      filterSearch.value = searchInput?.value || '';
    }
    searchForm.submit();
  }

  searchInput?.addEventListener('input', function() {
    if (filterSearch) {
      filterSearch.value = this.value;
    }
    liveSearchTable();
    clearTimeout(searchTimer);
    searchTimer = setTimeout(submitSearch, 450);
  });

  searchInput?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      clearTimeout(searchTimer);
      submitSearch();
    }
  });

  liveSearchTable();

  toggleFiltersBtn?.addEventListener('click', function() {
    if (!filterPanel) return;
    const isHidden = filterPanel.style.display === 'none' || !filterPanel.style.display;
    filterPanel.style.display = isHidden ? 'block' : 'none';
    if (filterButtonText) {
      filterButtonText.textContent = isHidden ? 'Hide Filters' : 'Show Filters';
    }
  });

  addBtn?.addEventListener('click', function(e) {
    e.preventDefault();
    openModal();
  });

  if (new URLSearchParams(window.location.search).has('add')) {
    openModal();
  }

  function openModal() {
    if (!modal) return;
    modal.style.display = 'flex';
    void modal.offsetWidth;
    modal.classList.add('active');
    modal.querySelector('input:not([type="hidden"])')?.focus();
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.remove('active');
    setTimeout(() => {
      modal.style.display = 'none';
    }, 200);

    const url = new URL(window.location);
    url.searchParams.delete('add');
    url.searchParams.delete('edit');
    window.history.replaceState({}, '', url);
  }

  modal?.addEventListener('click', function(e) {
    if (e.target === modal) closeModal();
  });

  cancelBtn?.addEventListener('click', function(e) {
    e.preventDefault();
    closeModal();
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal?.classList.contains('active')) {
      closeModal();
    }
  });

  form?.addEventListener('submit', function() {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Saving...';
    }
  });

  if (toast) {
    setTimeout(() => {
      toast.style.opacity = '0';
      setTimeout(() => {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
      }, 300);
    }, 4000);

    toast.querySelector('button')?.addEventListener('click', function() {
      toast.style.display = 'none';
    });
  }
});
