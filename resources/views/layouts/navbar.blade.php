<header class="header">
	<div class="header-content responsive-wrapper">
		<div class="header-logo">
			<a href="#" class="nav-link text-uppercase fw-bold">
				<img src="{{ asset(config('app.product') === 'novustream' ? 'images/novustreamlogo.png' : 'images/novusurgelogo.png') }}" alt="" style="width: 100px;">
			</a>
		</div>
		<div class="header-navigation">
			<nav class="header-navigation-links d-flex gap-4">
				@can('admin')
					<a href="{{route('dashboard')}}"> Dashboard </a>
				@endcan
				@can('concessionaire')
					<a href="{{route('account-overview.index')}}"> Account Overview </a>
					<a href="{{route('account-overview.bills')}}"> Bills & Payment </a>
				@endcan
				@canany(['admin', 'technician'])
					<div class="dropdown px-0 mx-0">
						<button class="border-0 bg-transparent dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
							Meter Reading
						</button>
						<ul class="dropdown-menu mt-3">
							<li><a class="dropdown-item" href="{{route('reading.index')}}">Meter Reading</a></li>
							<li><a class="dropdown-item" href="{{route('reading.report')}}">Reading Report</a></li>
						</ul>
					</div>
				@endcan
				@canany(['admin', 'cashier'])
					<a href="{{route('payments.index')}}"> Payments </a>
				@endcanany
				@can('admin')
					<div class="dropdown px-0 mx-0">
						<button class="border-0 bg-transparent dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
							Users
						</button>
						<ul class="dropdown-menu mt-3">
							<li><a class="dropdown-item" href="{{route('roles.index')}}">Roles</a></li>
							<li><a class="dropdown-item" href="{{route('concessionaires.index')}}">Concessionaires</a></li>
							<li><a class="dropdown-item" href="{{route('admins.index')}}">Personnels</a></li>
						</ul>
					</div>
					<div class="dropdown px-0 mx-0">
						<button class="border-0 bg-transparent dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
							Settings
						</button>
						<ul class="dropdown-menu mt-3">
							<li><a class="dropdown-item" href="{{route('property-types.index')}}">Property Types</a></li>
							<li><a class="dropdown-item" href="{{route('base-rate.index')}}">Base Rate</a></li>

							@can('app-novustream')
								<!-- novustream only -->
								<li><a class="dropdown-item" href="{{route('rates.index')}}">Water Rates</a></li>
							@endcan

							<li><a class="dropdown-item" href="{{route('payment-breakdown.index')}}">Payment Breakdown</a></li>
						</ul>
					</div>
				@endcan
			</nav>
			<div class="header-navigation-links d-flex gap-4">
				@can('concessionaire')
					<a href="{{route('client.support-ticket.create')}}">
						Submit Ticket
					</a>
					<a href="{{route('profile.index', ['user_type' => 'concessionaire'])}}">
						Profile
					</a>
				@elsecan('admin')
				<a href="{{route('admin.support-ticket.create')}}">
					Submit Ticket
				</a>
				<a href="{{route('profile.index', ['user_type' => 'admin'])}}">
					Profile
				</a>
				@endcan
				<form action="{{ route('auth.logout') }}" method="POST" style="display: inline;">
					@csrf
					<button type="submit" class="border-0 bg-transparent p-0 m-0 align-baseline">Logout</button>
				</form>
				<a href="#" class="avatar">
					<img src="https://assets.codepen.io/285131/hat-man.png" alt="" />
				</a>
			</div>
		</div>
		<a href="#" class="button">
			<i class="ph-list-bold"></i>
			<span>Menu</span>
		</a>
	</div>
</header>