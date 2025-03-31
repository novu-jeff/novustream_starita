@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                @if(isset($data))
                    <h1>Update Concessionaire</h1>
                @else
                    <h1>Add New Concessionaire</h1>
                @endif
                <a href="{{route('concessionaires.index')}}" class="btn btn-outline-primary px-5 py-3 text-uppercase">
                    Go Back
                </a>
            </div>
            <div class="inner-content mt-5">
                @if (isset($data))
                    <add-concessioner :property_types="{{ json_encode($property_types) }}" :data="{{ json_encode($data) }}"></add-concessioner>
                @else
                    <add-concessioner :property_types="{{ json_encode($property_types) }}"></add-concessioner>
                @endif

            </div>
        </div>
    </main>
@endsection

@section('script')
    <script>
        $(function () {
            @if (session('alert'))
                setTimeout(() => {
                    let alertData = @json(session('alert'));
                    alert(alertData.status, alertData.message);
                }, 100);
            @endif
        });
    </script>
@endsection

