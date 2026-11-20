@php
    $portalNavUser = auth()->user();
    $portalNavAccount = $portalAccount ?? $portalNavUser?->accounts?->first();
    $portalNavName = $portalName ?? ($portalNavUser->name ?? 'Account Holder');
    $portalNavAccountNo = $portalAccountNo ?? ($portalNavAccount->account_no ?? '');
    $portalNavInitials = collect(preg_split('/\s+/', trim($portalNavName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->implode('');
    $portalNavItems = [
        ['label' => 'Account Overview', 'url' => route('account-overview.index'), 'active' => request()->routeIs('account-overview.index')],
        ['label' => 'Bills & Payment', 'url' => route('account-overview.bills'), 'active' => request()->routeIs('account-overview.bills*')],
        ['label' => 'User Guide', 'url' => route('guide.concessionaire'), 'active' => request()->routeIs('guide.concessionaire')],
        ['label' => 'Submit Ticket', 'url' => route('client.support-ticket.create'), 'active' => request()->routeIs('client.support-ticket.*')],
        ['label' => 'Profile', 'url' => route('profile.index', ['user_type' => 'concessionaire']), 'active' => request()->routeIs('profile.*')],
    ];
@endphp

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap');
    body:has(.portal-topbar) .header { display: none !important; }
    body.portal-menu-open { overflow: hidden; }
    body:has(.portal-topbar) .main > .responsive-wrapper { width: min(1280px, calc(100% - 32px)); max-width: 1280px; }
    .portal-topbar { margin-bottom: 1.5rem; position: sticky; top: 0; z-index: 1040; display: flex; align-items: center; justify-content: space-between; gap: 20px; min-height: 76px; background: rgba(255,255,255,.97); border-bottom: 1px solid #dfe7ec; box-shadow: 0 2px 8px rgba(15,61,94,.04); backdrop-filter: blur(14px); border-radius: 20px; padding: 5px }
    .portal-brand { display: inline-flex; align-items: center; gap: 11px; flex: 0 0 auto; color: #0a2940; text-decoration: none; }
    .portal-brand:hover { color: #0a2940; }
    .portal-brand-mark { display: grid; place-items: center; width: 40px; height: 30px; border-radius: 9px;}
    .portal-brand-copy { display: flex; flex-direction: column; line-height: 1.15; }
    .portal-brand-copy strong { font: 800 17px 'Plus Jakarta Sans',sans-serif; }
    .portal-brand-copy small { margin-top: 3px; color: #0284c7; font-size: 9px; font-weight: 700; text-transform: uppercase; }
    .portal-nav { display: flex; align-items: center; gap: 4px; }
    .portal-nav-link { min-height: 40px; padding: 12px 12px; border: 0; border-radius: 7px; background: transparent; color: #536573; font-size: 11px; font-weight: 600; white-space: nowrap; text-decoration: none; transition: background .16s ease,color .16s ease; }
    .portal-nav-link:hover { background: #f1f5f7; color: #0a2940; }
    .portal-nav-link.is-active { background: #eaf0f3; color: #0a2940; }
    .portal-actions { display: flex; align-items: center; gap: 9px; flex: 0 0 auto; }
    .portal-icon-button { display: grid; place-items: center; width: 39px; height: 39px; border: 0; border-radius: 50%; background: transparent; color: #5a6c78; font-size: 21px; }
    .portal-icon-button:hover { background: #edf2f5; color: #0a2940; }
    .portal-notification.application-notification { position: relative; top: auto; right: auto; z-index: 1050; }
    .portal-notification .application-notification-button { position: relative; width: 39px; height: 39px; border: 0; border-radius: 50%; background: transparent; color: #5a6c78; box-shadow: none; }
    .portal-notification .application-notification-button:hover { background: #edf2f5; color: #0a2940; }
    .portal-notification .application-notification-button i { font-size: 21px; }
    .portal-notification-dot { position: absolute; top: 7px; right: 7px; width: 8px; height: 8px; border: 2px solid #fff; border-radius: 50%; background: #df4856; }
    .portal-notification .application-notification-panel { position: absolute; top: calc(100% + 8px); right: 0; z-index: 1100; display: flex; flex-direction: column; width: min(360px, calc(100vw - 32px)); max-height: min(330px, calc(100vh - 100px)); padding: 0; overflow: hidden; border: 1px solid #e0e7eb; border-radius: 12px; background: #fff; color: #172b3a; box-shadow: 0 18px 42px rgba(10,41,64,.18); }
    .portal-notification .application-notification-panel.d-none { display: none !important; }
    .portal-notification-heading { flex: 0 0 auto; padding: 14px 16px 11px; border-bottom: 1px solid #edf1f3; background: #fff; }
    .portal-notification-list { max-height: 252px; overflow-y: auto; overscroll-behavior: contain; scrollbar-gutter: stable; }
    .portal-notification-item { display: flex; align-items: flex-start; gap: 11px; padding: 12px 14px; border-bottom: 1px solid #edf1f3; }
    .portal-notification-item:last-child { border-bottom: 0; }
    .portal-notification-icon { display: grid; place-items: center; width: 32px; height: 32px; flex: 0 0 32px; border-radius: 50%; font-size: 17px; }
    .portal-notification-icon.is-bill { background: #fff5d6; color: #b77900; }
    .portal-notification-icon.is-payment { background: #dcfce7; color: #16803c; }
    .portal-notification-icon.is-due { background: #fee2e2; color: #c53030; }
    .portal-notification-icon.is-application { background: #e0f2fe; color: #0369a1; }
    .portal-notification-icon.is-account { background: #ede9fe; color: #6d28d9; }
    .portal-notification-copy { min-width: 0; }
    .portal-notification-copy .portal-notification-title { color: #172b3a; font-size: 12px; }
    .portal-notification-copy .portal-notification-message { line-height: 1.45; overflow-wrap: anywhere; }
    @media (max-width: 520px) { .portal-notification .application-notification-panel { right: -48px; width: min(360px, calc(100vw - 24px)); } }
    .portal-profile { position: relative; }
    .portal-profile-trigger { display: flex; align-items: center; gap: 9px; padding: 4px 10px 4px 5px; border: 1px solid #dfe7ec; border-radius: 30px; background: #f8fafb; color: #172b3a; text-align: left; }
    .portal-profile-trigger:hover { background: #f0f5f7; border-color: #ccd9e0; }
    .portal-avatar { display: grid; place-items: center; width: 34px; height: 34px; flex: 0 0 auto; border-radius: 50%; background: linear-gradient(145deg,#0f3d5e,#0284c7); color: #fff; font: 700 11px 'Plus Jakarta Sans',sans-serif; }
    .portal-profile-copy { display: flex; flex-direction: column; min-width: 0; }
    .portal-profile-copy strong { max-width: 130px; overflow: hidden; color: #172b3a; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
    .portal-profile-copy small { margin-top: 2px; color: #657783; font-size: 9px; white-space: nowrap; }
    .portal-profile-trigger > i { color: #74838d; font-size: 17px; }
    .portal-profile-menu { position: absolute; top: calc(100% + 9px); right: 0; z-index: 1060; width: 270px; padding: 7px; border: 1px solid #dfe7ec; border-radius: 9px; background: #fff; box-shadow: 0 16px 40px rgba(10,41,64,.18); }
    .portal-profile-menu[hidden],.portal-mobile-nav[hidden] { display: none !important; }
    .portal-profile-summary { display: flex; flex-direction: column; gap: 4px; padding: 11px 10px 13px; border-bottom: 1px solid #edf1f3; }
    .portal-profile-summary strong { font-size: 13px; }
    .portal-profile-summary span { overflow: hidden; color: #667984; font-size: 11px; text-overflow: ellipsis; }
    .portal-profile-summary small { margin-top: 6px; padding: 7px 8px; border-radius: 6px; background: #f5f8fa; color: #60727e; font-size: 10px; }
    .portal-profile-summary small b { float: right; color: #0f3d5e; font-family: monospace; }
    .portal-profile-menu > a,.portal-profile-menu > button,.portal-profile-menu form button { display: flex; align-items: center; gap: 10px; width: 100%; padding: 9px 10px; border: 0; border-radius: 6px; background: transparent; color: #405563; font-size: 11px; text-align: left; text-decoration: none; }
    .portal-profile-menu > a:hover,.portal-profile-menu > button:hover,.portal-profile-menu form button:hover { background: #f1f5f7; color: #0a2940; }
    .portal-profile-menu i { font-size: 16px; }
    .portal-profile-menu form { margin-top: 4px; padding-top: 4px; border-top: 1px solid #edf1f3; }
    .portal-profile-menu form .portal-signout { color: #ba3946; }
    .portal-mobile-menu-toggle { display: none; }
    @media (max-width: 1120px) { .portal-topbar { gap: 10px; margin-bottom: 1rem; } .portal-nav-link { padding: 0 8px; font-size: 10px; } .portal-profile-copy { display: none; } .portal-profile-trigger { padding-right: 6px; } }
    @media (max-width: 820px) {
        .portal-topbar { margin-bottom: 1rem; min-height: 66px; margin-right: -16px; margin-left: -16px; padding-right: 16px; padding-left: 16px; }
        .portal-nav { display: none; }
        .portal-mobile-menu-toggle { display: grid; }
        .portal-mobile-nav:not([hidden]) {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: flex;
        justify-content: flex-end;
        overflow: auto;
        padding: 0;
        background: rgba(8, 28, 42, .48);
    }

    .portal-mobile-drawer {
        width: min(320px, 86vw);
        min-height: 100%;
        margin-left: auto;
        padding: 18px 14px;
        background: #fff;
        box-shadow: -12px 0 36px rgba(10,41,64,.18);
        animation: portal-drawer-slide-left .25s ease-out;
    }
        .portal-mobile-drawer-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 2px 3px 16px; border-bottom: 1px solid #e8eef1; }
        .portal-mobile-drawer-title { color: #0a2940; font: 700 13px 'Plus Jakarta Sans',sans-serif; }
        .portal-mobile-close { display: grid; place-items: center; width: 36px; height: 36px; border: 0; border-radius: 50%; background: #f1f5f7; color: #405563; font-size: 21px; }
        .portal-mobile-links { display: grid; gap: 5px; padding-top: 12px; }
        .portal-mobile-nav .portal-nav-link { display: flex; align-items: center; min-height: 46px; padding: 0 12px; font-size: 12px; }
    }
    @keyframes portal-drawer-slide-left {
        from {
            transform: translateX(100%);
        }
        to {
            transform: translateX(0);
        }
    }
    @media (max-width: 520px) { body:has(.portal-topbar) .main > .responsive-wrapper { width: calc(100% - 24px); } .portal-topbar { border-radius: 0 0 12px 12px; margin-right: -12px; margin-left: -12px; padding-right: 12px; padding-left: 12px; } .portal-brand { gap: 7px; } .portal-brand-mark { width: 35px; height: 35px; font-size: 21px; } .portal-brand-copy strong { font-size: 14px; } .portal-brand-copy small { font-size: 8px; } .portal-actions { gap: 3px; } .portal-profile-trigger { padding: 2px; border: 0; background: transparent; } .portal-profile-trigger > i { display: none; } }
</style>

<header class="portal-topbar">
    <a class="portal-brand" href="{{ route('account-overview.index') }}" aria-label="Novustream Account Overview">
        <img src="{{ asset('images/novustreamwateronly.png') }}" alt="Novustream Logo" class="portal-brand-mark">
        <span class="portal-brand-copy"><strong>NOVUSTREAM</strong><small>Water Utility Portal</small></span>
    </a>
    <nav class="portal-nav" aria-label="Account navigation">
        @foreach($portalNavItems as $item)
            <a class="portal-nav-link {{ $item['active'] ? 'is-active' : '' }}" href="{{ $item['url'] }}" @if($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
        @endforeach
    </nav>
    <div class="portal-actions">
        @if(!empty($accountNotifications))
            <div class="portal-notification application-notification">
                <button type="button" class="portal-icon-button application-notification-button" id="portalNotificationToggle" aria-label="View account notifications" aria-expanded="false">
                    <i class="bx bx-bell" aria-hidden="true"></i><span class="portal-notification-dot"></span>
                </button>
                <div class="application-notification-panel d-none" id="portalNotificationPanel" aria-label="Account notifications">
                    <div class="portal-notification-heading fw-bold">Notifications</div>
                    <div class="portal-notification-list" tabindex="0" aria-label="Latest notifications; scroll to see older items">
                        @foreach($accountNotifications as $notification)
                            @php
                                $notificationType = $notification['type'] ?? '';
                                $notificationIcon = match ($notificationType) {
                                    'bill' => 'bx-receipt',
                                    'payment' => 'bx-check-circle',
                                    'due_date' => 'bx-alarm-exclamation',
                                    'application' => 'bx-file',
                                    'account' => 'bx-user',
                                    default => 'bx-bell',
                                };
                                $notificationColor = match ($notificationType) {
                                    'bill' => 'bill',
                                    'payment' => 'payment',
                                    'due_date' => 'due',
                                    'account', 'account_link', 'application' => 'application',
                                    default => 'account',
                                };
                            @endphp
                            <div class="portal-notification-item">
                                <span class="portal-notification-icon is-{{ $notificationColor }}"><i class="bx {{ $notificationIcon }}" aria-hidden="true"></i></span>
                                <div class="portal-notification-copy">
                                    <div class="portal-notification-title fw-bold mb-1">{{ $notification['title'] }}</div>
                                    <div class="portal-notification-message small text-muted">{{ $notification['message'] }}</div>
                                    @if(!empty($notification['date']))<div class="small text-muted mt-2">{{ $notification['date'] }}</div>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
        <div class="portal-profile" id="portal-profile-container">
            <button type="button" class="portal-profile-trigger" id="portalProfileToggle" aria-expanded="false" aria-controls="portal-profile-menu">
                <span class="portal-avatar">{{ $portalNavInitials ?: 'U' }}</span>
                <span class="portal-profile-copy"><strong>{{ $portalNavName }}</strong><small>ID: {{ $portalNavAccountNo ?: 'No linked account' }}</small></span>
                <i class="bx bx-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="portal-profile-menu" id="portal-profile-menu" hidden>
                <div class="portal-profile-summary">
                    <strong>{{ $portalNavName }}</strong>
                    <span>{{ $portalNavUser->email ?? '' }}</span>
                    <small>Account No. <b>{{ $portalNavAccountNo ?: 'N/A' }}</b></small>
                </div>
                <a href="{{ route('profile.index', ['user_type' => 'concessionaire']) }}"><i class="bx bx-user-circle" aria-hidden="true"></i>Profile & Contact Settings</a>
                <a href="{{ route('account-overview.index') }}#accounts"><i class="bx bx-link" aria-hidden="true"></i>Manage Linked Accounts</a>
                <a href="{{ route('client.support-ticket.create') }}"><i class="bx bx-support" aria-hidden="true"></i>Help & Support Ticket</a>
                <form action="{{ route('auth.logout') }}" method="POST">@csrf<button type="submit" class="portal-signout"><i class="bx bx-log-out" aria-hidden="true"></i>Sign Out</button></form>
            </div>
        </div>
        <button type="button" class="portal-icon-button portal-mobile-menu-toggle" id="portalMobileMenuToggle" aria-label="Open navigation" aria-expanded="false" aria-controls="portal-mobile-nav"><i class="bx bx-menu" aria-hidden="true"></i></button>
    </div>
</header>
<nav class="portal-mobile-nav" id="portal-mobile-nav" aria-label="Mobile account navigation" aria-modal="true" hidden>
    <div class="portal-mobile-drawer">
        <div class="portal-mobile-drawer-header">
            <span class="portal-mobile-drawer-title">Portal navigation</span>
            <button type="button" class="portal-mobile-close" id="portalMobileClose" aria-label="Close navigation"><i class="bx bx-x" aria-hidden="true"></i></button>
        </div>
        <div class="portal-mobile-links">
            @foreach($portalNavItems as $item)
                <a class="portal-nav-link {{ $item['active'] ? 'is-active' : '' }}" href="{{ $item['url'] }}" @if($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
            @endforeach
        </div>
    </div>
</nav>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const profileMenu = document.getElementById('portal-profile-menu');
        const profileToggle = document.getElementById('portalProfileToggle');
        const mobileNav = document.getElementById('portal-mobile-nav');
        const mobileToggle = document.getElementById('portalMobileMenuToggle');
        const mobileClose = document.getElementById('portalMobileClose');
        const notificationPanel = document.getElementById('portalNotificationPanel');
        const notificationToggle = document.getElementById('portalNotificationToggle');

        if (profileToggle && profileMenu) {
            profileToggle.addEventListener('click', function () {
                profileMenu.hidden = !profileMenu.hidden;
                profileToggle.setAttribute('aria-expanded', String(!profileMenu.hidden));
            });
        }
        if (mobileToggle && mobileNav) {
            mobileToggle.addEventListener('click', function () {
                mobileNav.hidden = !mobileNav.hidden;
                mobileToggle.setAttribute('aria-expanded', String(!mobileNav.hidden));
                document.body.classList.toggle('portal-menu-open', !mobileNav.hidden);
            });
        }
        if (mobileClose && mobileNav && mobileToggle) {
            mobileClose.addEventListener('click', function () {
                mobileNav.hidden = true;
                mobileToggle.setAttribute('aria-expanded', 'false');
                document.body.classList.remove('portal-menu-open');
                mobileToggle.focus();
            });
            mobileNav.addEventListener('click', function (event) {
                if (event.target === mobileNav) {
                    mobileNav.hidden = true;
                    mobileToggle.setAttribute('aria-expanded', 'false');
                    document.body.classList.remove('portal-menu-open');
                    mobileToggle.focus();
                }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !mobileNav.hidden) {
                    mobileNav.hidden = true;
                    mobileToggle.setAttribute('aria-expanded', 'false');
                    document.body.classList.remove('portal-menu-open');
                    mobileToggle.focus();
                }
            });
        }
        if (notificationToggle && notificationPanel) {
            notificationToggle.addEventListener('click', function () {
                notificationPanel.classList.toggle('d-none');
                notificationToggle.setAttribute('aria-expanded', String(!notificationPanel.classList.contains('d-none')));
            });
        }
        document.addEventListener('click', function (event) {
            if (profileMenu && profileToggle && !event.target.closest('#portal-profile-container')) {
                profileMenu.hidden = true;
                profileToggle.setAttribute('aria-expanded', 'false');
            }
            if (notificationPanel && notificationToggle && !event.target.closest('.portal-notification')) {
                notificationPanel.classList.add('d-none');
                notificationToggle.setAttribute('aria-expanded', 'false');
            }
        });
    });
</script>
