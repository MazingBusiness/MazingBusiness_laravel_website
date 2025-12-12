@extends('backend.layouts.app')

@section('content')

    <div class="aiz-titlebar text-left mt-2 mb-3">
        <div class="align-items-center">
            <h1 class="h3">{{translate('All Active Cron Job')}}</h1>
        </div>
    </div>
    <div class="card">
        <!-- Error Meassages -->
        @if ($errors->any())
            <div class="alert alert-danger">
                <div class="font-weight-600 mb-1">Please fix the errors below:</div>
                <ul class="mb-0 pl-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
                </ul>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}   {{-- from your catch() → with('error', ...) --}}
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
        <form class="" id="sort_customers" action="" method="GET">
            <div class="card-header row gutters-5">
                <div class="col">
                    <h5 class="mb-0 h6">Next Cron job will run at :- <span style="color: #13b513;">{{ date("F j Y, g:i a", strtotime($cronJobRunTime->run_time)) }}</span></h5>
                </div>
            </div>
            <div class="card-body">
                <table class="table aiz-table mb-0">
                    <thead>
                        <tr>
                            <th>{{ translate('Name') }}</th>
                            <th>{{ translate('Run At') }}</th>
                            <th>{{ translate('Created At') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cronJobs as $cronJob)
                            <tr>
                                <td>
                                    <p class="mb-1 text-truncate-2">
                                        {{$cronJob->name}}
                                    </p>
                                </td>
                                <td>
                                    <p class="mb-1 text-truncate-2">
                                        <strong>{{ date("F j Y, g:i a", strtotime($cronJob->run_at)) }}</strong>
                                    </p>
                                </td>
                                <td>
                                    <p class="mb-1 text-truncate-2">
                                        {{ date("F j Y, g:i a", strtotime($cronJob->created_at)) }}
                                    </p>
                                </td>
                            </tr>
                        @empty
                            <li class="list-group-item">
                                <div class="py-4 text-center fs-16">{{ translate('No active cron job found') }}</div>
                            </li>
                        @endforelse
                    </tbody>
                </table>

                <div class="aiz-pagination">
                    {{ $cronJobs->appends(request()->input())->links() }}
                </div>
            </div>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (e) {
                const el = e.target.closest('.confirm-delete');
                if (!el) return; // not our button

                e.preventDefault();

                const url   = el.getAttribute('href') || el.dataset.href;
                const label = el.dataset.confirm || 'Are you sure you want to delete this item?';
                if (!url) return;

                if (window.confirm(label)) {
                window.location.href = url;          // or submit a POST/DELETE form if you prefer
                }
            });
        });
    </script>
@endsection