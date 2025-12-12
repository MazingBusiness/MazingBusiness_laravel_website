<a href="{{ route('wishlists.index') }}" class="d-flex align-items-center text-reset">
  <i class="la la-heart-o la-2x text-white"></i>
  <span class="flex-grow-1 ml-1">
    @if (Auth::check())
      <span
        class="badge badge-primary badge-inline badge-pill mz-highlight text-dark">{{ count(Auth::user()->wishlists) }}</span>
    @else
      <span class="badge badge-primary badge-inline badge-pill mz-highlight text-dark">0</span>
    @endif
    <span class="nav-box-text d-none d-xl-block text-white">{{ translate('Wishlist') }}</span>
  </span>
</a>
