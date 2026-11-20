<section class="portal-kpis" aria-label="Account summary">
    <article class="portal-kpi portal-kpi-due">
        <div><p>Total Amount Due</p><strong>PHP {{ number_format((float) ($portalTotalAmount ?? 0), 2) }}</strong><small><span class="portal-status-dot"></span>{{ (float) ($portalTotalAmount ?? 0) > 0 ? ($portalBalanceNote ?? 'Current balance') : 'No balance due' }}</small></div>
        <span class="portal-kpi-icon"><i class="bx bx-receipt" aria-hidden="true"></i></span>
    </article>
    <article class="portal-kpi">
        <div><p>Payment Due Date</p><strong class="portal-kpi-date">{{ !empty($portalDueDate) ? \Carbon\Carbon::parse($portalDueDate)->format('M d, Y') : 'N/A' }}</strong><small>{{ $portalDueDateNote ?? ((float) ($portalTotalAmount ?? 0) > 0 ? 'Latest bill due date' : 'No payment scheduled') }}</small></div>
        <span class="portal-kpi-icon portal-kpi-icon-amber"><i class="bx bx-calendar" aria-hidden="true"></i></span>
    </article>
    <article class="portal-kpi">
        <div><p>Latest Consumption</p><strong>{{ number_format((float) ($portalLatestConsumption ?? 0), 2) }} <small>m³</small></strong><small>{{ $portalConsumptionNote ?? 'Latest available reading' }}</small></div>
        <span class="portal-kpi-icon portal-kpi-icon-blue"><i class="bx bx-water" aria-hidden="true"></i></span>
    </article>
    <article class="portal-kpi">
        <div><p>Account & Property</p><strong>{{ $portalAccountCount ?? 0 }} {{ \Illuminate\Support\Str::plural('Active', $portalAccountCount ?? 0) }}</strong><small>{{ $portalAccountNote ?? (($portalApprovedAccountCount ?? 0) . ' approved') }}</small></div>
        <span class="portal-kpi-icon portal-kpi-icon-green"><i class="bx bx-home-alt" aria-hidden="true"></i></span>
    </article>
</section>