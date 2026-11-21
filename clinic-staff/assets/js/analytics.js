(function () {
    'use strict';

    var dataEl = document.getElementById('analyticsData');
    if (!dataEl) return;
    var data;
    try { data = JSON.parse(dataEl.textContent); } catch (err) { return; }

    var page = data.page;
    var hasChart = typeof window.Chart !== 'undefined';
    if (hasChart) {
        document.documentElement.classList.add('charts-ready');
        window.Chart.defaults.font.family = "'DM Sans', sans-serif";
        window.Chart.defaults.color = '#5b7089';
    }

    document.querySelectorAll('[data-auto-submit]').forEach(function (select) {
        select.addEventListener('change', function () {
            if (!select.form) return;
            if (typeof select.form.requestSubmit === 'function') select.form.requestSubmit();
            else select.form.submit();
        });
    });

    var revealItems = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.05 });
        revealItems.forEach(function (item) {
            item.classList.add('animate');
            observer.observe(item);
        });
    } else {
        revealItems.forEach(function (item) { item.classList.add('visible'); });
    }

    function fmt(value, decimals) {
        return Number(value).toLocaleString('en-US', {
            minimumFractionDigits: decimals || 0,
            maximumFractionDigits: decimals === undefined ? 1 : decimals
        });
    }

    function setText(id, value) {
        var el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function updateQueryLink(id, params) {
        var link = document.getElementById(id);
        if (!link) return;
        var url = new URL(link.href, window.location.origin);
        Object.keys(params).forEach(function (key) { url.searchParams.set(key, params[key]); });
        link.href = url.toString();
    }

    var chartInstances = [];
    function makeChart(id, config) {
        var canvas = document.getElementById(id);
        if (!canvas || !hasChart) return null;
        var chart = new window.Chart(canvas, config);
        chartInstances.push(chart);
        return chart;
    }

    var baseChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 500 },
        plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#e8eef7' }, border: { display: false } },
            x: { grid: { display: false }, border: { display: false }, ticks: { maxTicksLimit: 12, maxRotation: 0 } }
        }
    };

    /* ---------------- Descriptive ---------------- */
    if (page === 'descriptive') {
        makeChart('descriptiveTrend', {
            type: 'line',
            data: {
                labels: data.daily.labels,
                datasets: [
                    {
                        label: 'Bookings', data: data.daily.values, borderColor: '#1668e3',
                        backgroundColor: 'rgba(22,104,227,.12)', fill: true, tension: .35,
                        pointRadius: 2, pointBackgroundColor: '#1668e3', borderWidth: 2
                    },
                    {
                        label: 'Completed', data: data.completedDaily.values, borderColor: '#00a88e',
                        backgroundColor: 'rgba(0,168,142,.08)', fill: true, tension: .35,
                        pointRadius: 2, pointBackgroundColor: '#00a88e', borderWidth: 2
                    }
                ]
            },
            options: baseChartOptions
        });

        makeChart('descriptiveStatus', {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Confirmed', 'Completed', 'Cancelled'],
                datasets: [{
                    data: [data.statuses.pending, data.statuses.confirmed, data.statuses.completed, data.statuses.cancelled],
                    backgroundColor: ['#f5a623', '#1668e3', '#00a88e', '#94a3b8'],
                    borderWidth: 0, hoverOffset: 5
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '72%',
                plugins: { legend: { display: false }, tooltip: { mode: 'nearest', intersect: true } }
            }
        });

        makeChart('descriptiveWeekday', {
            type: 'bar',
            data: {
                labels: data.weekday.labels,
                datasets: [{
                    label: 'Bookings', data: data.weekday.values,
                    backgroundColor: ['#1668e3', '#00a88e', '#7c5cff', '#f5a623', '#e5484d', '#0ea5e9', '#94a3b8'],
                    borderRadius: 7, maxBarThickness: 38
                }]
            },
            options: baseChartOptions
        });

        var records = data.records || [];
        var recordMap = {};
        records.forEach(function (r) { recordMap[r.id] = r; });
        var rows = Array.prototype.slice.call(document.querySelectorAll('[data-record-row]'));
        var searchInput = document.getElementById('recordSearch');
        var statusSelect = document.getElementById('recordStatus');
        var sortSelect = document.getElementById('recordSort');
        var prevBtn = document.getElementById('recordsPrev');
        var nextBtn = document.getElementById('recordsNext');
        var pageInfo = document.getElementById('recordsPageInfo');
        var countEl = document.getElementById('recordCount');
        var pageSize = 10;
        var currentPage = 1;
        var activeSlot = '';
        var emptyRow = null;

        function filteredRows() {
            var query = (searchInput && searchInput.value || '').toLowerCase().trim();
            var status = statusSelect ? statusSelect.value : '';
            var sort = sortSelect ? sortSelect.value : 'date-desc';
            var result = rows.filter(function (row) {
                if (status && row.dataset.status !== status) return false;
                if (activeSlot && row.dataset.slot !== activeSlot) return false;
                if (query && row.textContent.toLowerCase().indexOf(query) === -1) return false;
                return true;
            });
            result.sort(function (a, b) {
                if (sort === 'name-asc') return a.dataset.name.localeCompare(b.dataset.name);
                if (sort === 'branch-asc') return a.dataset.branch.localeCompare(b.dataset.branch) || b.dataset.date.localeCompare(a.dataset.date);
                if (sort === 'date-asc') return a.dataset.date.localeCompare(b.dataset.date);
                return b.dataset.date.localeCompare(a.dataset.date);
            });
            return result;
        }

        function renderRecords() {
            var result = filteredRows();
            var totalPages = Math.max(1, Math.ceil(result.length / pageSize));
            if (currentPage > totalPages) currentPage = totalPages;
            var start = (currentPage - 1) * pageSize;
            var visible = result.slice(start, start + pageSize);
            rows.forEach(function (row) { row.hidden = visible.indexOf(row) === -1; });
            var tbody = document.querySelector('#recordsTable tbody');
            if (tbody) {
                if (!result.length) {
                    if (!emptyRow) {
                        emptyRow = document.createElement('tr');
                        emptyRow.className = 'empty-row';
                        var cell = document.createElement('td');
                        cell.colSpan = 6;
                        cell.className = 'empty-table';
                        cell.textContent = 'No records match your search and filters.';
                        emptyRow.appendChild(cell);
                        tbody.appendChild(emptyRow);
                    }
                    emptyRow.hidden = false;
                } else if (emptyRow) {
                    emptyRow.hidden = true;
                }
            }
            setText('recordCount', result.length);
            if (pageInfo) pageInfo.textContent = 'Page ' + currentPage + ' of ' + totalPages + ' · ' + result.length + ' records';
            if (prevBtn) prevBtn.disabled = currentPage <= 1;
            if (nextBtn) nextBtn.disabled = currentPage >= totalPages;
        }

        if (searchInput) searchInput.addEventListener('input', function () { currentPage = 1; renderRecords(); });
        if (statusSelect) statusSelect.addEventListener('change', function () { currentPage = 1; renderRecords(); });
        if (sortSelect) sortSelect.addEventListener('change', function () { currentPage = 1; renderRecords(); });
        if (prevBtn) prevBtn.addEventListener('click', function () { if (currentPage > 1) { currentPage--; renderRecords(); } });
        if (nextBtn) nextBtn.addEventListener('click', function () { currentPage++; renderRecords(); });

        document.querySelectorAll('[data-slot-filter]').forEach(function (button) {
            button.addEventListener('click', function () {
                var slot = button.dataset.slotFilter;
                activeSlot = activeSlot === slot ? '' : slot;
                document.querySelectorAll('[data-slot-filter]').forEach(function (b) {
                    b.setAttribute('aria-pressed', b.dataset.slotFilter === activeSlot ? 'true' : 'false');
                });
                currentPage = 1;
                renderRecords();
                var panel = document.getElementById('records-panel');
                if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        var drawer = document.getElementById('recordDrawer');
        var overlay = document.getElementById('drawerOverlay');
        var drawerBody = document.getElementById('drawerBody');
        var drawerTitle = document.getElementById('drawerTitle');
        var drawerClose = document.getElementById('drawerClose');
        var drawerLink = document.getElementById('drawerManageLink');
        var lastFocused = null;

        function addField(label, value, isParagraph) {
            var wrap = document.createElement('div');
            wrap.className = 'drawer-field';
            var span = document.createElement('span');
            span.textContent = label;
            var content = document.createElement(isParagraph ? 'p' : 'b');
            content.textContent = value || '—';
            wrap.appendChild(span);
            wrap.appendChild(content);
            drawerBody.appendChild(wrap);
        }

        function openDrawer(record) {
            if (!drawer || !record) return;
            lastFocused = document.activeElement;
            drawerBody.textContent = '';
            drawerTitle.textContent = record.name;
            addField('Reference', record.reference);
            addField('Status', record.status.charAt(0).toUpperCase() + record.status.slice(1));
            addField('Branch', record.branch);
            addField('Appointment', record.date + ' · ' + record.time);
            addField('Email', record.email);
            addField('Phone', record.phone);
            addField('Booked', record.created);
            addField('Notes', record.notes, true);
            if (drawerLink) drawerLink.href = drawerLink.href.split('?')[0] + '?status=' + encodeURIComponent(record.status);
            drawer.hidden = false;
            overlay.hidden = false;
            document.body.style.overflow = 'hidden';
            drawerClose.focus();
        }

        function closeDrawer() {
            if (!drawer) return;
            drawer.hidden = true;
            overlay.hidden = true;
            document.body.style.overflow = '';
            if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
        }

        document.querySelectorAll('[data-record-details]').forEach(function (button) {
            button.addEventListener('click', function () {
                openDrawer(recordMap[button.dataset.recordDetails]);
            });
        });
        if (drawerClose) drawerClose.addEventListener('click', closeDrawer);
        if (overlay) overlay.addEventListener('click', closeDrawer);
        document.addEventListener('keydown', function (event) {
            if (!drawer || drawer.hidden) return;
            if (event.key === 'Escape') { closeDrawer(); return; }
            if (event.key === 'Tab') {
                var focusable = drawer.querySelectorAll('button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                if (!focusable.length) return;
                var first = focusable[0];
                var last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        });

        renderRecords();
    }

    /* ---------------- Predictive ---------------- */
    if (page === 'predictive') {
        var forecast = data.forecast;
        var capacity = data.capacity;
        var serverShift = forecast.shift || 0;
        var baseRows = forecast.rows.map(function (row) {
            return Object.assign({}, row, { baseExpected: row.closed ? 0 : row.expected / (1 + serverShift / 100) });
        });
        var historyLen = forecast.history.labels.length;
        var labels = forecast.history.labels.concat(baseRows.map(function (r) { return r.label; }));
        var nulls = function (n) { return new Array(n).fill(null); };
        var recorded = forecast.history.values.concat(nulls(baseRows.length));
        var expected = nulls(historyLen).concat(baseRows.map(function (r) { return r.expected; }));
        var booked = nulls(historyLen).concat(baseRows.map(function (r) { return r.booked; }));
        var band = forecast.stdDev * 1.28;
        var lower = nulls(historyLen).concat(baseRows.map(function (r) { return r.closed ? 0 : Math.max(0, r.expected - band); }));
        var upper = nulls(historyLen).concat(baseRows.map(function (r) { return r.closed ? 0 : r.expected + band; }));

        var predictiveChart = makeChart('predictiveChart', {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Recorded', data: recorded, borderColor: '#1668e3', backgroundColor: 'rgba(22,104,227,.10)', fill: true, tension: .3, pointRadius: 2, borderWidth: 2 },
                    { label: 'Range low', data: lower, borderColor: 'transparent', backgroundColor: 'transparent', pointRadius: 0, fill: false },
                    { label: 'Range high', data: upper, borderColor: 'transparent', backgroundColor: 'rgba(124,92,255,.12)', pointRadius: 0, fill: '-1' },
                    { label: 'Expected', data: expected, borderColor: '#7c5cff', borderDash: [6, 4], backgroundColor: 'transparent', tension: .3, pointRadius: 2, borderWidth: 2 },
                    { label: 'Booked', data: booked, borderColor: '#00a88e', backgroundColor: 'transparent', tension: .3, pointRadius: 2, borderWidth: 2 }
                ]
            },
            options: Object.assign({}, baseChartOptions, {
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index', intersect: false,
                        filter: function (item) { return item.dataset.label !== 'Range low' && item.dataset.label !== 'Range high'; }
                    }
                }
            })
        });

        var shiftSlider = document.getElementById('demandShift');
        var resetScenario = document.getElementById('resetScenario');
        var forecastTableRows = document.querySelectorAll('[data-forecast-row]');

        function applyShift(newShift) {
            var total = 0;
            var adjusted = baseRows.map(function (row) {
                var value = row.closed ? 0 : Math.round(row.baseExpected * (1 + newShift / 100) * 10) / 10;
                total += value;
                return Object.assign({}, row, { expected: value });
            });
            total = Math.round(total);
            var margin = (forecast.upper - forecast.lower) / 2;
            var lowerTotal = Math.max(0, Math.round(total - margin));
            var upperTotal = Math.round(total + margin);
            var util = capacity.capacity ? Math.round(total / capacity.capacity * 1000) / 10 : 0;
            var headroom = Math.round(capacity.capacity - total);
            var avg = forecast.openDays ? Math.round(total / forecast.openDays * 10) / 10 : 0;

            setText('heroForecastTotal', fmt(total, 0));
            setText('heroForecastRange', lowerTotal + '–' + upperTotal);
            setText('heroAvgDay', fmt(avg));
            setText('kpiExpected', fmt(total, 0));
            setText('kpiRange', lowerTotal + '–' + upperTotal);
            setText('kpiUtil', fmt(util) + '%');
            setText('kpiShift', (newShift > 0 ? '+' : '') + newShift);
            setText('scenarioTotal', fmt(total, 0));
            setText('scenarioUtil', fmt(util) + '%');
            setText('headroomText', fmt(headroom, 0));
            setText('demandShiftValue', (newShift > 0 ? '+' : '') + newShift + '%');

            var hidden = document.getElementById('shiftHidden');
            if (hidden) hidden.value = newShift;
            updateQueryLink('forecastExport', { shift: newShift });

            if (predictiveChart) {
                predictiveChart.data.datasets[3].data = nulls(historyLen).concat(adjusted.map(function (r) { return r.expected; }));
                predictiveChart.data.datasets[1].data = nulls(historyLen).concat(adjusted.map(function (r) { return r.closed ? 0 : Math.max(0, r.expected - band); }));
                predictiveChart.data.datasets[2].data = nulls(historyLen).concat(adjusted.map(function (r) { return r.closed ? 0 : r.expected + band; }));
                predictiveChart.update('none');
            }

            forecastTableRows.forEach(function (tr, index) {
                var row = adjusted[index];
                if (!row) return;
                var expectedCell = tr.querySelector('.forecast-expected');
                var gapCell = tr.querySelector('.forecast-gap');
                if (expectedCell) expectedCell.textContent = row.closed ? '—' : fmt(row.expected);
                if (gapCell) gapCell.textContent = row.closed ? '—' : fmt(Math.round((row.expected - row.booked) * 10) / 10);
            });
        }

        if (shiftSlider) {
            shiftSlider.addEventListener('input', function () { applyShift(parseInt(shiftSlider.value, 10)); });
        }
        if (resetScenario) {
            resetScenario.addEventListener('click', function () {
                if (shiftSlider) shiftSlider.value = 0;
                applyShift(0);
            });
        }
    }

    /* ---------------- Prescriptive ---------------- */
    if (page === 'prescriptive') {
        var slotsSlider = document.getElementById('slotsPerDay');
        var coverageSlider = document.getElementById('staffCoverage');
        var resetPlanner = document.getElementById('resetPlanner');
        var forecastTotal = data.forecastTotal || 0;
        var branchCount = data.branchCount || 1;
        var openDays = data.openDays || 1;

        function applyPlanner() {
            var slots = slotsSlider ? parseInt(slotsSlider.value, 10) : 12;
            var coverage = coverageSlider ? parseInt(coverageSlider.value, 10) : 100;
            var capacityValue = Math.round(branchCount * slots * openDays * coverage / 100);
            var util = capacityValue ? Math.round(forecastTotal / capacityValue * 1000) / 10 : 0;
            var headroom = Math.round(capacityValue - forecastTotal);
            var required = Math.ceil(forecastTotal / (branchCount * openDays * coverage / 100));

            setText('slotsValue', slots);
            setText('coverageValue', coverage + '%');
            setText('gaugeUtil', fmt(util) + '%');
            setText('gaugeCapacity', fmt(capacityValue, 0) + ' slots');
            setText('gaugeHeadroom', fmt(headroom, 0) + ' slots');
            setText('gaugeRequired', fmt(required, 0));
            setText('plannerUtilKpi', fmt(util) + '%');

            var ring = document.getElementById('gaugeRing');
            if (ring) {
                ring.style.setProperty('--gauge', Math.min(100, util) + '%');
                ring.style.background = util >= 85
                    ? 'conic-gradient(#e5484d ' + Math.min(100, util) + '%, #e8eef7 0)'
                    : util >= 70
                        ? 'conic-gradient(#f5a623 ' + Math.min(100, util) + '%, #e8eef7 0)'
                        : 'conic-gradient(#00a88e ' + Math.min(100, util) + '%, #e8eef7 0)';
            }
            var status = document.getElementById('plannerStatus');
            if (status) {
                status.className = 'planner-status' + (util >= 85 ? ' danger' : util >= 70 ? ' warn' : '');
                status.textContent = util >= 85 ? 'Near capacity — review staffing' : util >= 70 ? 'Filling — monitor daily' : 'Headroom available';
            }
            var slotsHidden = document.getElementById('slotsHidden');
            var coverageHidden = document.getElementById('coverageHidden');
            if (slotsHidden) slotsHidden.value = slots;
            if (coverageHidden) coverageHidden.value = coverage;
            updateQueryLink('planExport', { slots: slots, coverage: coverage });
        }

        if (slotsSlider) slotsSlider.addEventListener('input', applyPlanner);
        if (coverageSlider) coverageSlider.addEventListener('input', applyPlanner);
        if (resetPlanner) resetPlanner.addEventListener('click', function () {
            if (slotsSlider) slotsSlider.value = 12;
            if (coverageSlider) coverageSlider.value = 100;
            applyPlanner();
        });
        applyPlanner();

        var actionSearch = document.getElementById('actionSearch');
        var actionPriority = document.getElementById('actionPriority');
        var actionState = document.getElementById('actionState');
        var cards = Array.prototype.slice.call(document.querySelectorAll('.recommendation-card'));
        var noActions = document.getElementById('noActionsMessage');

        function filterActions() {
            var query = (actionSearch && actionSearch.value || '').toLowerCase().trim();
            var priority = actionPriority ? actionPriority.value : '';
            var state = actionState ? actionState.value : '';
            var visibleCount = 0;
            cards.forEach(function (card) {
                var show = (!query || card.dataset.search.indexOf(query) !== -1)
                    && (!priority || card.dataset.priority === priority)
                    && (!state || card.dataset.state === state);
                card.hidden = !show;
                if (show) visibleCount++;
            });
            if (noActions) noActions.hidden = visibleCount !== 0;
        }

        if (actionSearch) actionSearch.addEventListener('input', filterActions);
        if (actionPriority) actionPriority.addEventListener('change', filterActions);
        if (actionState) actionState.addEventListener('change', filterActions);
        filterActions();
    }
})();