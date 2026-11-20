@include('account-overview.partials.portal-summary-styles')

<section class="portal-welcome" aria-labelledby="portal-welcome-title">
    <div class="portal-welcome-pattern" aria-hidden="true"></div>
    <div class="portal-welcome-main">
        <div class="portal-welcome-avatar-wrap">
            <div class="portal-welcome-avatar">{{ $portalInitials ?: 'U' }}</div>
            <span class="portal-active-badge">Active</span>
        </div>
        <div class="portal-welcome-copy">
            <div class="portal-eyebrow"><i class="bx bx-shield-quarter" aria-hidden="true"></i> Verified Account Holder <span>Statement as of {{ $portalStatementDate ?? now()->format('M d, Y') }}</span></div>
            <h1 id="portal-welcome-title">Welcome back, {{ $portalName }}!</h1>
            <div class="portal-account-meta">
                <span><i class="bx bx-hash" aria-hidden="true"></i>Account No: <b>{{ $portalAccountNo ?: 'No linked account' }}</b></span>
                @if(!empty($portalAddress))
                    <span><i class="bx bx-map" aria-hidden="true"></i>Service Area: <b>{{ $portalAddress }}</b></span>
                @endif
                @if(!empty($portalEmail))
                    <span class="portal-meta-email"><i class="bx bx-envelope" aria-hidden="true"></i>{{ $portalEmail }}</span>
                @endif
            </div>
        </div>
    </div>
    <div class="portal-welcome-actions">
        @if(!empty($portalCurrentBill))
            @if(!empty($portalStatementModal))
                <button type="button" class="portal-button portal-button-glass" data-bs-toggle="modal" data-bs-target="#latestStatementModal"><i class="bx bx-file" aria-hidden="true"></i>View Statement</button>
            @elseif(!empty($portalStatementUrl))
                <a href="{{ $portalStatementUrl }}" class="portal-button portal-button-glass"><i class="bx bx-file" aria-hidden="true"></i>View Statement</a>
            @endif
            @if(!empty($portalPaymentUrl) && empty($portalCurrentBill['isPaid']))
                <a href="{{ $portalPaymentUrl }}" target="_blank" rel="noopener" class="portal-button portal-button-light"><i class="bx bx-credit-card" aria-hidden="true"></i>Pay PHP {{ number_format((float) ($portalTotalAmount ?? 0), 2) }}</a>
        @endif
        @elseif(!empty($portalStatementUrl))
            <a href="{{ $portalStatementUrl }}" class="portal-button portal-button-glass"><i class="bx bx-file" aria-hidden="true"></i>View Statement</a>
        @endif
    </div>
</section>