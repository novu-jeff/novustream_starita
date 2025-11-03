@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>Concessionaires List</h1>
                <div class="d-flex align-items-center gap-3">
                    <div class="dropdown">
                        <a href="{{ route('import') }}" class="btn btn-outline-primary px-5 py-3 text-uppercase">
                            Import
                        </a>
                    </div>
                    <a href="{{ route('concessionaires.create') }}" class="btn btn-primary px-5 py-3 text-uppercase">
                        Add New
                    </a>
                </div>
            </div>
            <div class="inner-content mt-5 pb-5">
                <div class="row mb-4">
                    <div class="col-12 col-md-2 mb-3">
                        <label class="mb-1">Show Entries</label>
                        <select name="entries" id="entries" class="form-select text-uppercase dropdown-toggle">
                            @foreach([10, 25, 50, 100, 200, 250, 350, 400, 450, 500] as $entry)
                                <option value="{{ $entry }}" {{ $entries == $entry ? 'selected' : '' }}>
                                    {{ $entry }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3 mb-3">
                        <label class="mb-1">Zone</label>
                        <select name="zone_no" id="zone_no" class="form-select text-uppercase dropdown-toggle">
                            <option value="all">All</option>
                            @foreach($zones as $code => $area)
                                <option value="{{ $code }}" {{ $code == $zone ? 'selected' : '' }}>
                                    {{ $code }} - {{ $area }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4 mb-3">
                        <label class="mb-1">Search</label>
                        <div class="position-relative">
                            <input
                                type="text"
                                name="search"
                                id="search"
                                class="form-control pe-5"
                                value="{{ $toSearch }}"
                                placeholder=""
                            >

                            @if(!empty($toSearch))
                                <button
                                    type="button"
                                    id="clear-search"
                                    class="btn position-absolute top-50 end-0 translate-middle-y me-2 p-0 text-muted"
                                    style="border: none; background: none; font-size: 1.2rem;"
                                    aria-label="Clear search"
                                >
                                    &times;
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
                <table class="table table-bordered table-striped w-100 mt-4">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Account No.</th>
                            <th>Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data as $user)
                            <tr>
                                <td>{{ $user->id }}</td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->accounts->pluck('account_no')->implode(', ') }}</td>
                                <td>{{ $user->accounts->pluck('address')->implode(', ') }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ route('concessionaires.edit', ['concessionaire' => $user->id]) }}"
                                            class="btn btn-primary text-white text-uppercase fw-bold"
                                            data-id="{{ $user->id }}">
                                            <i class="bx bx-edit"></i>
                                        </a>

                                        <button type="button" class="btn btn-danger btn-delete" data-id="{{ $user->id }}">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="w-100 mt-4">
                    {{ $data->links() }}
                </div>
            </div>
        </div>
    </main>
@endsection

@section('script')
<script>
    $(function () {
        function updateUrl() {
            const params = new URLSearchParams(window.location.search);

            ['search', 'entries', 'zone_no'].forEach(id => {
                const val = $('#' + id).val();
                const key = id === 'zone_no' ? 'zone' : id;

                val ? params.set(key, val) : params.delete(key);
            });

            window.location.href = window.location.pathname + '?' + params.toString();
        }

        $('#search, #entries, #zone_no').on('change', updateUrl);

        $('#clear-search').on('click', function () {
            $('#search').val('');
            updateUrl();
        });

        $(document).on('click', '.btn-delete', function() {
            const id = $(this).data('id');
            const token = '{{csrf_token()}}';
            const url = '{{route("concessionaires.destroy", ["concessionaire" => "__ID__"])}}'.replace('__ID__', id);
            remove(null, url, token)
        });
    });
</script>
@endsection
