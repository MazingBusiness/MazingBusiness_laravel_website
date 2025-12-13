@extends('frontend.layouts.app') {{-- or your main public layout --}}

@section('content')
    <style>
        .mobile-portal-wrapper {
            min-height: calc(100vh - 80px);
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            padding: 24px 12px;
        }
        .mobile-portal-card {
            max-width: 460px;
            width: 100%;
            background: #020617;
            border-radius: 18px;
            padding: 24px 20px 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
            border: 1px solid rgba(148, 163, 184, 0.25);
            color: #f9fafb;
        }
        .mobile-logo {
            font-weight: 700;
            font-size: 22px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .mobile-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            padding: 4px 9px;
            border-radius: 999px;
            background: rgba(8, 47, 73, 0.9);
            color: #a5f3fc;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .mobile-pill-dot {
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background: #22c55e;
        }
        .mobile-portal-card h1 {
            font-size: 22px;
            margin: 16px 0 6px;
        }
        .mobile-portal-card p {
            font-size: 13px;
            color: #e5e7eb;
            line-height: 1.6;
            margin-bottom: 18px;
        }
        .mobile-buttons {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .mobile-btn {
            border-radius: 999px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            padding: 10px 14px;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: transform 0.08s ease, box-shadow 0.08s ease, background 0.12s ease;
        }
        .mobile-btn-primary {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #022c22;
            box-shadow: 0 10px 22px rgba(34, 197, 94, 0.35);
        }
        .mobile-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(34, 197, 94, 0.4);
        }
        .mobile-btn-ghost {
            background: rgba(15,23,42,0.9);
            color: #e5e7eb;
            border: 1px solid rgba(148, 163, 184, 0.45);
        }
        .mobile-btn-ghost:hover {
            background: rgba(15,23,42,1);
        }
        .mobile-note {
            font-size: 11px;
            color: #9ca3af;
            text-align: center;
            margin-top: 8px;
        }
    </style>

    <div class="mobile-portal-wrapper">
        <div class="mobile-portal-card">
            <div class="mobile-logo">Mazing Business</div>
            <div class="mobile-pill">
                <span class="mobile-pill-dot"></span>
                Mobile Access Portal
            </div>

            <h1>Welcome to Mazing Mobile</h1>
            <p>
                This is a dedicated mobile entry point for selected users.
                Please use the buttons below to continue to your respective portal.
            </p>

            <div class="mobile-buttons">
                {{-- TODO: replace route/URL with your actual mobile login --}}
                <a href="{{ url('login') }}" class="mobile-btn mobile-btn-primary">
                    Continue to Login
                </a>

                <a href="{{ url('/') }}" class="mobile-btn mobile-btn-ghost">
                    Go to Main Website
                </a>
            </div>

            <div class="mobile-note">
                If you were assigned a special mobile ID or portal, use only this page to log in.
            </div>
        </div>
    </div>
@endsection