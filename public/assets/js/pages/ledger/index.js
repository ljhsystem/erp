const page = document.getElementById('ledgerDashboard');

if (page) {
  const byId = (id) => document.getElementById(id);
  const number = (value) => Number(value) || 0;
  const money = (value) => `${Math.round(number(value)).toLocaleString('ko-KR')}원`;
  const count = (value) => `${number(value).toLocaleString('ko-KR')}건`;
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
  const statusLabel = (value) => ({ POSTED: '전기완료', CLOSED: '마감' }[String(value || '').toUpperCase()] || String(value || '-'));

  function renderChart(rows) {
    const data = Array.isArray(rows) ? rows : [];
    const max = Math.max(1, ...data.flatMap((row) => [Math.abs(number(row.revenue_total)), Math.abs(number(row.expense_total)), Math.abs(number(row.profit_total))]));
    const points = data.map((row, index) => {
      const x = data.length > 1 ? 5 + (index * 90 / (data.length - 1)) : 50;
      const y = 88 - (Math.max(0, number(row.profit_total)) / max * 68);
      return `${x},${y}`;
    }).join(' ');
    byId('ledgerPerformanceChart').innerHTML = `<div class="ledger-chart-plot">${data.map((row, index) => {
      const revenueHeight = Math.max(1, Math.abs(number(row.revenue_total)) / max * 68);
      const expenseHeight = Math.max(1, Math.abs(number(row.expense_total)) / max * 68);
      return `<div class="ledger-chart-month" title="${escapeHtml(row.year_month)} 수익 ${money(row.revenue_total)}, 비용 ${money(row.expense_total)}, 손익 ${money(row.profit_total)}"><div class="ledger-bars"><i style="height:${revenueHeight}%"></i><i style="height:${expenseHeight}%"></i></div><span>${String(index + 1).padStart(2, '0')}월</span></div>`;
    }).join('')}<svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><polyline points="${points}"/></svg></div>`;
  }

  function renderAlerts(alerts) {
    const rows = Array.isArray(alerts) ? alerts : [];
    byId('alertCount').textContent = count(rows.length);
    byId('ledgerAlerts').innerHTML = rows.length ? rows.map((row) => `<a href="${escapeHtml(row.href || '#')}" class="is-${escapeHtml(String(row.level || 'INFO').toLowerCase())}"><i class="bi ${row.level === 'BLOCK' ? 'bi-x-octagon-fill' : row.level === 'ACTION' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill'}"></i><span><strong>${escapeHtml(row.title)}</strong><small>${escapeHtml(row.detail)}</small></span>${row.count != null ? `<b>${count(row.count)}</b>` : '<i class="bi bi-chevron-right"></i>'}</a>`).join('') : '<div class="ledger-empty-state"><i class="bi bi-check-circle-fill"></i><strong>지금 처리할 긴급 업무가 없습니다.</strong><span>회계 흐름이 정상입니다.</span></div>';
  }

  function renderReadiness(rows, closure) {
    const items = Array.isArray(rows) ? rows : [];
    const complete = items.filter((item) => item.complete).length;
    const percent = items.length ? Math.round(complete / items.length * 100) : 0;
    byId('readinessPercent').textContent = `${percent}%`;
    byId('readinessRing').style.setProperty('--progress', `${percent * 3.6}deg`);
    byId('readinessList').innerHTML = items.map((item) => `<a href="${escapeHtml(item.href)}"><span>${escapeHtml(item.label)}</span><b class="${item.complete ? 'is-complete' : 'is-pending'}"><i class="bi ${item.complete ? 'bi-check-circle-fill' : 'bi-dash-circle-fill'}"></i>${item.complete ? '완료' : '확인'}</b></a>`).join('');
    const labels = { OPEN: '결산 진행 중', CLOSED: '결산 마감', REOPENED: '결산 재개방' };
    byId('closureSummary').textContent = closure ? `${closure.fiscal_year}년 · ${labels[closure.close_status_code] || closure.close_status_code}` : '등록된 회계기간이 없습니다.';
  }

  function render(data) {
    const vouchers = data.vouchers || {};
    const performance = data.performance || {};
    const assets = data.assets || {};
    const inventory = data.inventory || {};
    const depreciation = data.depreciation || {};
    byId('voucherDraftCount').textContent = count(vouchers.DRAFT);
    byId('voucherRequestedCount').textContent = count(vouchers.REVIEW_REQUESTED);
    byId('voucherReviewedCount').textContent = count(vouchers.REVIEWED);
    byId('voucherPostedCount').textContent = count(vouchers.POSTED);
    byId('voucherClosedCount').textContent = count(vouchers.CLOSED);
    byId('dashboardPeriod').textContent = `${data.period?.date_from || '-'} ~ ${data.period?.date_to || '-'} · 전기 완료 기준`;
    byId('yearRevenue').textContent = money(performance.revenue_total);
    byId('yearExpense').textContent = money(performance.expense_total);
    byId('yearProfit').textContent = money(performance.profit_total);
    byId('profitCaption').textContent = `전표 ${count(performance.voucher_count)} · 분개 ${count(performance.line_count)}`;
    byId('assetBookValue').textContent = money(assets.book_value_total);
    byId('assetCaption').textContent = `사용 중 ${count(assets.active_count)}`;
    byId('inventoryClosing').textContent = money(inventory.closing_total);
    byId('inventoryCaption').textContent = `기초 ${money(inventory.opening_total)}`;
    const difference = number(performance.debit_total) - number(performance.credit_total);
    byId('yearDifference').textContent = money(difference);
    byId('balanceCaption').textContent = Math.abs(difference) < 0.001 ? '차변·대변 일치' : '차이 확인 필요';
    byId('yearDifference').closest('.ledger-pulse-item').classList.toggle('has-error', Math.abs(difference) >= 0.001);
    byId('assetAcquisition').textContent = money(assets.acquisition_total);
    byId('depreciationSummary').textContent = `당기 감가상각 ${money(depreciation.amount_total)}`;
    byId('inventoryIncrease').textContent = money(inventory.increase_total);
    byId('inventoryDecrease').textContent = money(inventory.decrease_total);
    byId('inventoryOpening').textContent = `기초재고 ${money(inventory.opening_total)}`;
    renderChart(data.monthly);
    renderAlerts(data.alerts);
    renderReadiness(data.readiness, data.closure);
    const rows = Array.isArray(data.recent_vouchers) ? data.recent_vouchers : [];
    byId('recentPostedVouchers').innerHTML = rows.length ? rows.map((row) => `<tr data-id="${escapeHtml(row.id)}" tabindex="0" role="link" title="클릭하여 전표 열기"><td>${escapeHtml(row.voucher_date)}</td><td>${escapeHtml(row.voucher_no)}</td><td>${escapeHtml(row.summary || '-')}</td><td><span class="ledger-status-badge">${escapeHtml(statusLabel(row.status_code))}</span></td><td class="text-end">${money(row.amount)}</td></tr>`).join('') : '<tr><td colspan="5" class="ledger-empty">선택한 회계연도에 전기된 전표가 없습니다.</td></tr>';
    byId('ledgerDashboardAsOf').textContent = `데이터 기준 ${data.as_of || '-'}`;
    byId('ledgerDashboardStatus').textContent = '전기 완료된 회계자료만 재무수치에 반영했습니다.';
    byId('ledgerDashboardStatus').classList.remove('is-error');
  }

  async function load() {
    const button = byId('ledgerDashboardRefresh');
    button.disabled = true;
    byId('ledgerDashboardStatus').textContent = '회계 현황을 불러오고 있습니다.';
    try {
      const year = encodeURIComponent(byId('ledgerFiscalYear').value);
      const response = await fetch(`/api/ledger/dashboard/summary?fiscal_year=${year}`, { headers: { Accept: 'application/json' } });
      const json = await response.json();
      if (!response.ok || !json.success) throw new Error(json.message || '회계 현황 조회 실패');
      render(json.data || {});
    } catch (error) {
      byId('ledgerDashboardStatus').textContent = error.message || '회계 현황을 불러오지 못했습니다.';
      byId('ledgerDashboardStatus').classList.add('is-error');
    } finally { button.disabled = false; }
  }

  byId('ledgerDashboardRefresh').addEventListener('click', load);
  byId('ledgerFiscalYear').addEventListener('change', load);
  const openVoucher = (target) => { const id = target.closest('tr')?.dataset.id; if (id) location.href = `/ledger/vouchers/input?voucher_id=${encodeURIComponent(id)}`; };
  byId('recentPostedVouchers').addEventListener('click', (event) => openVoucher(event.target));
  byId('recentPostedVouchers').addEventListener('keydown', (event) => { if (event.key === 'Enter') openVoucher(event.target); });
  load();
  setInterval(load, 60000);
}
