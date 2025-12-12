@extends('frontend.layouts.app')
@section('content')
  <section class="gry-bg py-4">
    <div class="profile">
      <div class="container">
        <div class="row">
          <div class="col-xxl-4 col-xl-5 col-lg-6 col-md-8 mx-auto">
            <div class="card">
              <div class="text-center pt-4">
                <h1 class="h4 fw-600">
                  {{ translate('Thank You') }}
                </h1>
              </div>
              <div class="px-4 py-3 py-lg-4">
                <div class="">
                    @if (session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif
                </div>
                <div class="text-center">
                  <p class="text-muted mb-0">{{ translate('Already have an account?') }}</p>
                  <a href="{{ route('user.login') }}">{{ translate('Log In') }}</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
