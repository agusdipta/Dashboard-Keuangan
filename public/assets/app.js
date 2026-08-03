document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) {
    window.lucide.createIcons();
  }

  setupSidebar();
  syncCategoryOptions();
  setupMoneyInputs();
  renderCashflowChart();
});

function setupSidebar() {
  const body = document.body;
  const toggles = document.querySelectorAll('[data-sidebar-toggle]');
  const closeButtons = document.querySelectorAll('[data-sidebar-close], .sidebar-nav a');

  if (!toggles.length) {
    return;
  }

  const setOpen = (isOpen) => {
    body.classList.toggle('sidebar-open', isOpen);
    toggles.forEach((button) => {
      button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  };

  toggles.forEach((button) => {
    button.addEventListener('click', () => {
      setOpen(!body.classList.contains('sidebar-open'));
    });
  });

  closeButtons.forEach((button) => {
    button.addEventListener('click', () => setOpen(false));
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      setOpen(false);
    }
  });
}

function setupMoneyInputs() {
  document.querySelectorAll('[data-money-input]').forEach((input) => {
    const apply = () => {
      const digits = input.value.replace(/\D/g, '');
      input.value = digits ? new Intl.NumberFormat('id-ID').format(Number(digits)) : '';
    };

    input.addEventListener('input', apply);
    input.addEventListener('blur', apply);
    apply();
  });
}

function syncCategoryOptions() {
  const typeSelect = document.querySelector('[data-transaction-type]');
  const categorySelect = document.querySelector('[data-category-select]');

  if (!typeSelect || !categorySelect) {
    return;
  }

  const apply = () => {
    const activeType = typeSelect.value;
    let selectedStillVisible = categorySelect.value === '';

    Array.from(categorySelect.options).forEach((option) => {
      const optionType = option.dataset.type;

      if (!optionType) {
        option.hidden = false;
        return;
      }

      option.hidden = optionType !== activeType;

      if (!option.hidden && option.selected) {
        selectedStillVisible = true;
      }
    });

    if (!selectedStillVisible) {
      categorySelect.value = '';
    }
  };

  typeSelect.addEventListener('change', apply);
  apply();
}

function renderCashflowChart() {
  const canvas = document.getElementById('cashflowChart');
  const data = window.financeChartData;

  if (!canvas || !data || !window.Chart) {
    return;
  }

  new window.Chart(canvas, {
    type: 'line',
    data: {
      labels: data.labels,
      datasets: [
        {
          label: 'Pemasukan',
          data: data.income,
          borderColor: '#16a34a',
          backgroundColor: 'rgba(22, 163, 74, 0.12)',
          borderWidth: 3,
          tension: 0.35,
          fill: true,
          pointRadius: 0,
          pointHoverRadius: 5,
        },
        {
          label: 'Pengeluaran',
          data: data.expense,
          borderColor: '#dc2626',
          backgroundColor: 'rgba(220, 38, 38, 0.10)',
          borderWidth: 3,
          tension: 0.35,
          fill: true,
          pointRadius: 0,
          pointHoverRadius: 5,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false,
      },
      plugins: {
        legend: {
          labels: {
            boxWidth: 10,
            boxHeight: 10,
            color: '#475569',
            font: {
              family: 'Inter, system-ui, sans-serif',
            },
          },
        },
        tooltip: {
          callbacks: {
            label: (context) => {
              const value = Number(context.parsed.y || 0);
              return `${context.dataset.label}: ${formatRupiah(value)}`;
            },
          },
        },
      },
      scales: {
        x: {
          grid: {
            display: false,
          },
          ticks: {
            color: '#64748b',
            maxTicksLimit: 10,
          },
        },
        y: {
          beginAtZero: true,
          grid: {
            color: '#e2e8f0',
          },
          ticks: {
            color: '#64748b',
            callback: (value) => formatCompactRupiah(value),
          },
        },
      },
    },
  });
}

function formatRupiah(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(value);
}

function formatCompactRupiah(value) {
  if (value >= 1000000) {
    return `Rp${Math.round(value / 1000000)} jt`;
  }

  if (value >= 1000) {
    return `Rp${Math.round(value / 1000)} rb`;
  }

  return `Rp${value}`;
}
