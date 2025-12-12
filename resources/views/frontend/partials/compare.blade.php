<a href="{{ route('compare') }}" class="d-flex align-items-center text-reset">
  <i class="la la-refresh la-2x text-white"></i>
  <span class="flex-grow-1 ml-1">
    @if (Session::has('compare'))
      <span
        class="badge badge-primary badge-inline badge-pill mz-highlight text-dark">{{ count(Session::get('compare')) }}</span>
    @else
      <span class="badge badge-primary badge-inline badge-pill mz-highlight text-dark">0</span>
    @endif
    <span class="nav-box-text d-none d-xl-block text-white">{{ translate('Compare') }}</span>
  </span>
</a>
