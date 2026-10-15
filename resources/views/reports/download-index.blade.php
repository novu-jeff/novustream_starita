@extends('layouts.app')

@section('content')
<main class="container-fluid report-builder py-4 px-3 px-lg-4">
    <div class="report-page-header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div class="d-flex align-items-start gap-3">
            <div class="report-title-icon"><i class="bx bx-file" aria-hidden="true"></i></div>
            <div>
                <p class="eyebrow mb-1">REPORTS</p>
                <h1 class="report-page-title mb-1">Generate Reports</h1>
                <p class="report-page-subtitle mb-0">Find, select, and configure the reports you need.</p>
            </div>
        </div>

        {{-- <div class="preset-tools d-flex flex-wrap gap-2 align-items-center">
            <div class="preset-select-wrap">
                <label for="presetSelect" class="visually-hidden">Saved report presets</label>
                <select id="presetSelect" class="form-select" aria-label="Saved report presets">
                    <option value="">Saved presets</option>
                </select>
            </div>
            <button type="button" id="loadPreset" class="btn btn-light report-icon-button" title="Load selected preset" aria-label="Load selected preset">
                <i class="bx bx-folder-open" aria-hidden="true"></i>
            </button>
            <label for="presetName" class="visually-hidden">Preset name</label>
            <input type="text" id="presetName" class="form-control preset-name-input" maxlength="40" placeholder="Preset name" aria-label="Preset name">
            <button type="button" id="savePreset" class="btn btn-outline-primary">Save preset</button>
        </div> --}}
    </div>

    <form id="reportForm" action="{{ route('reports.download.generate') }}" method="POST">
        @csrf
        <div class="row g-4 align-items-stretch">
            <section class="col-12 col-xl-8" aria-labelledby="reportPickerTitle">
                <div class="report-card report-catalog-card h-100">
                    <div class="catalog-toolbar">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                            <div>
                                <h2 id="reportPickerTitle" class="section-title mb-1">Choose reports</h2>
                                <p class="section-subtitle mb-0">Select one or more reports to include in your export.</p>
                            </div>
                            <span class="selected-count-pill" id="selectedCount">0 selected</span>
                        </div>

                        <div class="catalog-search-row">
                            <div class="input-group report-search">
                                <span class="input-group-text"><i class="bx bx-search" aria-hidden="true"></i></span>
                                <input type="search" id="reportSearch" class="form-control" placeholder="Search reports (e.g. billing, ageing, payments...)" autocomplete="off" aria-label="Search reports">
                                <button type="button" class="btn btn-light search-clear" id="clearReportSearch" aria-label="Clear report search" title="Clear search">
                                    <i class="bx bx-x" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="report-tabs" role="group" aria-label="Report catalog filter">
                                <button type="button" class="report-tab active" data-report-tab="all"><i class="bx bx-grid-alt" aria-hidden="true"></i> All</button>
                                <button type="button" class="report-tab" data-report-tab="favorites"><i class="bx bx-star" aria-hidden="true"></i> Favorites</button>
                                <button type="button" class="report-tab" data-report-tab="recent"><i class="bx bx-time-five" aria-hidden="true"></i> Recent</button>
                            </div>
                        </div>
                    </div>

                    <div class="report-categories" id="reportCatalog">
                        @foreach($reportCategories as $category => $reports)
                            @php
                                $categorySlug = \Illuminate\Support\Str::slug($category);
                                $categoryIcon = str_contains(strtolower($category), 'billing') || str_contains(strtolower($category), 'collection') ? 'bx-receipt' : (str_contains(strtolower($category), 'account') || str_contains(strtolower($category), 'concession') ? 'bx-group' : (str_contains(strtolower($category), 'reading') || str_contains(strtolower($category), 'consumption') ? 'bx-droplet' : (str_contains(strtolower($category), 'penalt') || str_contains(strtolower($category), 'delinquen') || str_contains(strtolower($category), 'ageing') ? 'bx-error-circle' : (str_contains(strtolower($category), 'tax') || str_contains(strtolower($category), 'financial') || str_contains(strtolower($category), 'book') ? 'bx-wallet' : 'bx-file'))));
                            @endphp
                            <section class="report-category is-collapsed" data-category="{{ $category }}">
                                <div class="category-heading">
                                    <button type="button" class="category-toggle" aria-expanded="false" aria-controls="category-{{ $categorySlug }}">
                                        <span class="category-icon"><i class="bx {{ $categoryIcon }}" aria-hidden="true"></i></span>
                                        <span class="category-title-wrap">
                                            <span class="category-title">{{ $category }}</span>
                                            <span class="category-meta"><span class="category-dot">•</span> {{ count($reports) }} reports</span>
                                        </span>
                                        <i class="bx bx-chevron-down category-chevron" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" class="select-category" data-category-select="{{ $category }}">Select all</button>
                                </div>

                                <div class="report-list" id="category-{{ $categorySlug }}">
                                    @foreach($reports as $index => $report)
                                        @php($reportId = 'report-' . $categorySlug . '-' . $index . '-' . \Illuminate\Support\Str::slug($report))
                                        <div class="report-item" data-report-name="{{ strtolower($report) }}" data-report-category="{{ $category }}">
                                            <input class="form-check-input report-checkbox" type="checkbox" value="{{ $report }}" name="reports[]" id="{{ $reportId }}">
                                            <label class="report-label" for="{{ $reportId }}"><span>{{ $report }}</span></label>
                                            <button type="button" class="favorite-toggle" data-favorite-report="{{ $report }}" title="Add to favorites" aria-label="Add {{ $report }} to favorites">
                                                <i class="bx bx-star" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                        <div id="noReportsFound" class="empty-search-state d-none">
                            <span class="empty-state-icon"><i class="bx bx-search-alt" aria-hidden="true"></i></span>
                            <strong>No reports found</strong>
                            <span>Try another keyword or switch to All reports.</span>
                        </div>
                    </div>

                    <div class="catalog-tip"><i class="bx bx-bulb" aria-hidden="true"></i><span><strong>Tip:</strong> Save commonly used report combinations as presets to generate them faster next time.</span></div>
                </div>
            </section>

            <aside class="col-12 col-xl-4 selected-panel" aria-labelledby="selectedReportsTitle">
                <div class="selected-panel-inner">
                    <section class="report-card selected-reports-card">
                        <div class="panel-heading">
                            <div class="d-flex align-items-center gap-2">
                                <h2 id="selectedReportsTitle" class="section-title mb-0">Selected reports</h2>
                                <span class="selected-count-pill selected-count-pill-small" id="selectedPanelCount">0 selected</span>
                            </div>
                            <button type="button" id="clearSelection" class="text-action">Clear all</button>
                        </div>
                        <ul id="selectedReports" class="selected-reports-list list-unstyled mb-0" aria-live="polite"></ul>
                        <div id="emptySelection" class="empty-selection">
                            <span class="empty-state-icon"><i class="bx bx-file-blank" aria-hidden="true"></i></span>
                            <strong>No reports selected yet</strong>
                            <span>Choose reports from the list to get started.</span>
                        </div>
                    </section>

                    <section class="report-card generation-card">
                        <div class="generation-heading">
                            <span class="settings-icon"><i class="bx bx-cog" aria-hidden="true"></i></span>
                            <div>
                                <h2 class="section-title mb-1">Generation settings</h2>
                                <p class="section-subtitle mb-0">Choose how your report should be exported.</p>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="reportMode" class="form-label">Mode</label>
                                <select id="reportMode" name="mode" class="form-select">
                                    <option value="combined">Combined (all reports in one file)</option>
                                    <option value="separate">Separate (one file per report, returned as ZIP)</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="sheetGrouping" class="form-label">Sheet grouping</label>
                                <select id="sheetGrouping" name="sheet_grouping" class="form-select">
                                    <option value="all">All months in one sheet</option>
                                    <option value="monthly">Separate sheet per month</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="reportFormat" class="form-label">Format</label>
                                <select id="reportFormat" name="format" class="form-select">
                                    <option value="xlsx">Excel (.xlsx)</option>
                                    <option value="csv">CSV (.csv)</option>
                                    <option value="plain">Plain Sheet (.xlsx)</option>
                                </select>
                                <small class="form-text text-muted">When exporting List of Concessionaires by itself, CSV streams rows and avoids Excel's worksheet limit.</small>
                            </div>
                            <div class="col-6">
                                <label for="reportStartDate" class="form-label">Start date</label>
                                <input type="date" id="reportStartDate" name="start_date" class="form-control" value="{{ request('start_date', now()->startOfMonth()->format('Y-m-d')) }}">
                            </div>
                            <div class="col-6">
                                <label for="reportEndDate" class="form-label">End date</label>
                                <input type="date" id="reportEndDate" name="end_date" class="form-control" value="{{ request('end_date', now()->endOfMonth()->format('Y-m-d')) }}">
                            </div>
                            <div class="col-12">
                                <label for="reportZone" class="form-label">Zone <span class="required-mark">*</span></label>
                                <select id="reportZone" name="zone" class="form-select" required>
                                    <option value="">Select zone</option>
                                    <option value="all" {{ request('zone', 'all') === 'all' ? 'selected' : '' }}>All zones</option>
                                    @foreach ($zones as $z)
                                        <option value="{{ $z }}" {{ request('zone') == $z ? 'selected' : '' }}>{{ $z }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div id="reportFormError" class="form-feedback d-none" role="alert">Select at least one report before generating.</div>
                        <button type="submit" id="generateReports" class="btn btn-primary generate-button" disabled>
                            <i class="bx bx-file" aria-hidden="true"></i><span>Generate report</span><i class="bx bx-right-arrow-alt ms-auto" aria-hidden="true"></i>
                        </button>
                        <p class="generation-note"><i class="bx bx-time-five" aria-hidden="true"></i> Your report will be prepared for download.</p>
                    </section>
                </div>
            </aside>
        </div>
    </form>
</main>

<style>
    
</style>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const reportForm = document.getElementById('reportForm');
    const reportItems = Array.from(document.querySelectorAll('.report-item'));
    const reportCheckboxes = Array.from(document.querySelectorAll('.report-checkbox'));
    const selectedReports = document.getElementById('selectedReports');
    const selectedCount = document.getElementById('selectedCount');
    const selectedPanelCount = document.getElementById('selectedPanelCount');
    const generateButton = document.getElementById('generateReports');
    const emptySelection = document.getElementById('emptySelection');
    const noReportsFound = document.getElementById('noReportsFound');
    const searchInput = document.getElementById('reportSearch');
    const formError = document.getElementById('reportFormError');
    const favoritesKey = 'starita.reports.favorites';
    const recentKey = 'starita.reports.recent';
    const presetsKey = 'starita.reports.presets';
    let activeTab = 'all';

    function readStorage(key, fallback) {
        try {
            const value = JSON.parse(localStorage.getItem(key) || 'null');
            return value === null ? fallback : value;
        } catch (error) {
            return fallback;
        }
    }

    function writeStorage(key, value) {
        try { localStorage.setItem(key, JSON.stringify(value)); } catch (error) { /* Storage may be unavailable. */ }
    }

    function selectedValues() {
        return reportCheckboxes.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value);
    }

    function renderSelected() {
        const selected = reportCheckboxes.filter((checkbox) => checkbox.checked);
        selectedReports.replaceChildren();
        const countLabel = `${selected.length} selected`;
        selectedCount.textContent = countLabel;
        selectedPanelCount.textContent = countLabel;
        emptySelection.classList.toggle('d-none', selected.length > 0);
        generateButton.disabled = selected.length === 0;
        formError.classList.add('d-none');

        selected.forEach((checkbox) => {
            const item = checkbox.closest('.report-item');
            const category = item.dataset.reportCategory || 'Report';
            const li = document.createElement('li');
            li.className = 'selected-entry';

            const label = document.createElement('div');
            label.className = 'selected-entry-label';
            const icon = document.createElement('span');
            icon.className = 'selected-entry-icon';
            const iconElement = document.createElement('i');
            iconElement.className = 'bx bx-file';
            iconElement.setAttribute('aria-hidden', 'true');
            icon.append(iconElement);

            const textWrap = document.createElement('span');
            textWrap.style.minWidth = '0';
            const name = document.createElement('span');
            name.className = 'selected-entry-name';
            name.textContent = checkbox.value;
            const categoryName = document.createElement('span');
            categoryName.className = 'selected-entry-category';
            categoryName.textContent = category;
            textWrap.append(name, categoryName);
            label.append(icon, textWrap);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'selected-entry-remove';
            remove.title = `Remove ${checkbox.value}`;
            remove.setAttribute('aria-label', `Remove ${checkbox.value}`);
            const removeIcon = document.createElement('i');
            removeIcon.className = 'bx bx-x';
            removeIcon.setAttribute('aria-hidden', 'true');
            remove.append(removeIcon);
            remove.addEventListener('click', () => {
                checkbox.checked = false;
                renderSelected();
                filterReports();
            });

            li.append(label, remove);
            selectedReports.append(li);
        });
    }

    function refreshFavorites() {
        const favorites = new Set(readStorage(favoritesKey, []));
        document.querySelectorAll('.favorite-toggle').forEach((button) => {
            const favorite = favorites.has(button.dataset.favoriteReport);
            button.classList.toggle('is-favorite', favorite);
            button.title = favorite ? 'Remove from favorites' : 'Add to favorites';
            button.setAttribute('aria-label', `${favorite ? 'Remove' : 'Add'} ${button.dataset.favoriteReport} ${favorite ? 'from' : 'to'} favorites`);
            const icon = button.querySelector('i');
            if (icon) icon.className = `bx ${favorite ? 'bxs-star' : 'bx-star'}`;
        });
    }

    function filterReports() {
        const query = searchInput.value.trim().toLowerCase();
        const favorites = new Set(readStorage(favoritesKey, []));
        const recent = new Set(readStorage(recentKey, []));
        let visibleCount = 0;

        reportItems.forEach((item) => {
            const checkbox = item.querySelector('.report-checkbox');
            const name = item.dataset.reportName || '';
            const category = (item.dataset.reportCategory || '').toLowerCase();
            const matchesQuery = !query || name.includes(query) || category.includes(query);
            const matchesTab = activeTab === 'all' || (activeTab === 'favorites' ? favorites.has(checkbox.value) : recent.has(checkbox.value));
            const visible = matchesQuery && matchesTab;
            item.classList.toggle('d-none', !visible);
            if (visible) visibleCount++;
        });

        document.querySelectorAll('.report-category').forEach((category) => {
            const hasMatches = Boolean(category.querySelector('.report-item:not(.d-none)'));
            category.classList.toggle('d-none', !hasMatches);
            if (hasMatches && (query || activeTab !== 'all')) setCategoryCollapsed(category, false);
        });
        noReportsFound.classList.toggle('d-none', visibleCount > 0);
        refreshCategorySelectLabels();
    }

    function setCategoryCollapsed(category, collapsed) {
        category.classList.toggle('is-collapsed', collapsed);
        const toggle = category.querySelector('.category-toggle');
        if (toggle) toggle.setAttribute('aria-expanded', String(!collapsed));
        const selectButton = category.querySelector('.select-category');
        if (selectButton) selectButton.setAttribute('aria-hidden', String(collapsed));
    }

    function refreshPresetOptions() {
        const select = document.getElementById('presetSelect');
        if (!select) return;
        const currentValue = select.value;
        select.replaceChildren(new Option('Saved presets', ''));
        const presets = readStorage(presetsKey, {});
        Object.keys(presets).sort((a, b) => a.localeCompare(b)).forEach((name) => select.add(new Option(name, name)));
        if (Object.prototype.hasOwnProperty.call(presets, currentValue)) select.value = currentValue;
    }

    function refreshCategorySelectLabels() {
        document.querySelectorAll('.report-category').forEach((category) => {
            const button = category.querySelector('.select-category');
            const categoryItems = Array.from(category.querySelectorAll('.report-item'));
            if (!button || !categoryItems.length) return;
            const allSelected = categoryItems.every((item) => item.querySelector('.report-checkbox').checked);
            button.textContent = allSelected ? 'Deselect all' : 'Select all';
        });
    }

    function setActiveTab(button) {
        activeTab = button.dataset.reportTab;
        document.querySelectorAll('[data-report-tab]').forEach((tab) => {
            const active = tab === button;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-pressed', String(active));
        });
        filterReports();
    }

    reportCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', () => {
        const selected = selectedValues();
        if (checkbox.checked && checkbox.value === 'List of Concessionaires' && selected.length === 1) {
            document.getElementById('reportFormat').value = 'csv';
        }
        renderSelected();
    }));
    searchInput.addEventListener('input', filterReports);
    document.getElementById('clearReportSearch').addEventListener('click', () => {
        searchInput.value = '';
        filterReports();
        searchInput.focus();
    });

    document.querySelectorAll('[data-report-tab]').forEach((button) => button.addEventListener('click', () => setActiveTab(button)));
    document.querySelectorAll('.category-toggle').forEach((button) => button.addEventListener('click', () => {
        const category = button.closest('.report-category');
        setCategoryCollapsed(category, !category.classList.contains('is-collapsed'));
    }));

    document.querySelectorAll('.select-category').forEach((button) => button.addEventListener('click', () => {
        const categoryName = button.dataset.categorySelect;
        const members = reportItems.filter((item) => item.dataset.reportCategory === categoryName);
        if (!members.length) return;
        const shouldSelect = members.some((item) => !item.querySelector('.report-checkbox').checked);
        members.forEach((item) => { item.querySelector('.report-checkbox').checked = shouldSelect; });
        renderSelected();
        refreshCategorySelectLabels();
    }));

    document.querySelectorAll('.favorite-toggle').forEach((button) => button.addEventListener('click', () => {
        const favorites = new Set(readStorage(favoritesKey, []));
        const report = button.dataset.favoriteReport;
        if (favorites.has(report)) favorites.delete(report); else favorites.add(report);
        writeStorage(favoritesKey, Array.from(favorites));
        refreshFavorites();
        filterReports();
    }));

    document.getElementById('clearSelection').addEventListener('click', () => {
        reportCheckboxes.forEach((checkbox) => { checkbox.checked = false; });
        document.querySelectorAll('.select-category').forEach((button) => { button.textContent = 'Select all'; });
        renderSelected();
    });

    document.getElementById('savePreset')?.addEventListener('click', () => {
        const name = document.getElementById('presetName').value.trim();
        const selected = selectedValues();
        if (!name) {
            document.getElementById('presetName').focus();
            document.getElementById('presetName').setCustomValidity('Enter a name for this preset.');
            document.getElementById('presetName').reportValidity();
            return;
        }
        document.getElementById('presetName').setCustomValidity('');
        if (!selected.length) {
            formError.textContent = 'Select at least one report before saving a preset.';
            formError.classList.remove('d-none');
            return;
        }
        const presets = readStorage(presetsKey, {});
        presets[name] = selected;
        writeStorage(presetsKey, presets);
        refreshPresetOptions();
        document.getElementById('presetSelect').value = name;
    });

    document.getElementById('loadPreset')?.addEventListener('click', () => {
        const name = document.getElementById('presetSelect').value;
        const preset = readStorage(presetsKey, {})[name];
        if (!preset) return;
        reportCheckboxes.forEach((checkbox) => { checkbox.checked = preset.includes(checkbox.value); });
        document.querySelectorAll('.select-category').forEach((button) => { button.textContent = 'Select all'; });
        renderSelected();
        filterReports();
    });

    reportForm.addEventListener('submit', (event) => {
        const selected = selectedValues();
        if (!selected.length) {
            event.preventDefault();
            formError.textContent = 'Select at least one report before generating.';
            formError.classList.remove('d-none');
            return;
        }
        const startDate = document.getElementById('reportStartDate');
        const endDate = document.getElementById('reportEndDate');
        if (startDate.value && endDate.value && startDate.value > endDate.value) {
            event.preventDefault();
            endDate.setCustomValidity('End date must be on or after the start date.');
            endDate.reportValidity();
            endDate.addEventListener('change', () => endDate.setCustomValidity(''), { once: true });
            return;
        }
        const previous = readStorage(recentKey, []);
        writeStorage(recentKey, [...selected, ...previous.filter((report) => !selected.includes(report))].slice(0, 20));
    });

    document.querySelectorAll('.report-category').forEach((category) => setCategoryCollapsed(category, true));
    refreshFavorites();
    refreshPresetOptions();
    renderSelected();
    filterReports();
});
</script>
@endsection
