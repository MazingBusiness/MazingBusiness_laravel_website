@extends('backend.layouts.app')

@section('content')

    <div class="aiz-titlebar text-left mt-2 mb-3">
        <div class="align-items-center">
            <h1 class="h3">{{translate('All Notifications')}}</h1>
        </div>
    </div>
    <div class="col-lg-12" style="float:right; margin-bottom: 22px;">
        <a href="{{ route('addNotifications') }}" class="btn btn-success">+ Add New Notification </a>
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
                    <h5 class="mb-0 h6">{{translate('Notifications')}}</h5>
                </div>
            </div>
            <div class="card-body">
                <table class="table aiz-table mb-0">
                    <thead>
                        <tr>
                            <th>{{ translate('Title') }}</th>
                            <th>{{ translate('Body') }}</th>
                            <th>{{ translate('Type') }}</th>
                            <th>{{ translate('Image') }}</th>
                            <th>{{ translate('Message Type') }}</th>
                            <th>{{ translate('Sending Status') }}</th>
                            <th>{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($notifications as $notification)
                            <tr>
                                <td>
                                    <p class="mb-1 text-truncate-2">
                                        {{$notification->title}}
                                    </p>
                                    <small class="text-muted">
                                        {{ date("F j Y, g:i a", strtotime($notification->date_time)) }}
                                        @if($notification->pop_up_message == 1)
                                             - {{ date("F j Y, g:i a", strtotime($notification->to_date)) }}
                                        @endif
                                    </small>
                                </td>
                                <td>
                                    <p class="mb-1 text-truncate-2">
                                        {{$notification->body}}
                                    </p>
                                </td>
                                <td>
                                    <p class="mb-1 text-truncate-2">
                                        {{ucfirst($notification->type)}}
                                    </p>
                                    <small class="text-muted">
                                        @if(isset($notification->item_name))
                                            {{ $notification->item_name }}
                                        @endif
                                    </small>
                                </td>
                                <td>
                                    <img src="{{$notification->image}}" style="width:auto; height:50px;">
                                </td>
                                <td>
                                    @if($notification->pop_up_message == 0)
                                        <p class="mb-1 text-truncate-2">
                                            Push Notification Message
                                        </p>
                                    @else
                                        <p class="mb-1 text-truncate-2">
                                            Popup Message
                                        </p>
                                        <small class="text-muted">
                                            {{ $notification->show_on_screen }}
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    <p class="mb-1 text-truncate-2">
                                        @if($notification->status==0)
                                            Pending
                                        @elseif($notification->status==1)
                                            Sended
                                        @endif
                                    </p>
                                </td>
                                <td>
                                    <a href="{{ route('deleteNotification', ['id' => $notification->id]) }}" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete" title="Delete"> <i class="las la-trash"></i> </a>
                                </td>
                            </tr>
                        @empty
                            <li class="list-group-item">
                                <div class="py-4 text-center fs-16">{{ translate('No notification found') }}</div>
                            </li>
                        @endforelse
                    </tbody>
                </table>

                <div class="aiz-pagination">
                    {{ $notifications->appends(request()->input())->links() }}
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