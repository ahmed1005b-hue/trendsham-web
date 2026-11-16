@extends('layouts.app')

@section('title', ($accountTitle ?? 'حسابي').' - تريندشام')

@push('styles')
<style>
    .ts-account-layout { display:grid; grid-template-columns: 280px minmax(0,1fr); gap:1rem; align-items:start; }
    .ts-account-sidebar, .ts-account-card { background:#fff; border:1px solid #e6e6e6; border-radius:8px; }
    .ts-account-sidebar { position:sticky; top:1rem; overflow:hidden; }
    .ts-account-user { display:flex; align-items:center; gap:.75rem; padding:1rem; border-bottom:1px solid #f0f0f0; }
    .ts-account-avatar { width:44px; height:44px; flex:0 0 44px; border-radius:50%; background:#f5f5f5; color:#999; display:grid; place-items:center; overflow:hidden; font-size:1.5rem; }
    .ts-account-avatar img { width:100%; height:100%; object-fit:cover; }
    .ts-account-name { font-weight:700; color:#333; font-size:.95rem; }
    .ts-account-sub { color:#777; font-size:.78rem; }
    .ts-account-nav { padding:.35rem 0 .6rem; }
    .ts-account-group { padding:.7rem 1rem .25rem; color:#999; font-size:.75rem; font-weight:700; }
    .ts-account-link { display:flex; align-items:center; gap:.65rem; padding:.62rem 1rem; color:#333; text-decoration:none; font-size:.9rem; border-inline-start:3px solid transparent; }
    .ts-account-link i { color:#777; font-size:1rem; width:1.1rem; text-align:center; }
    .ts-account-link:hover { background:#fafafa; color:#f27a1a; }
    .ts-account-link:hover i { color:#f27a1a; }
    .ts-account-link.active { background:#fff7f0; color:#f27a1a; font-weight:700; border-inline-start-color:#f27a1a; }
    .ts-account-link.active i { color:#f27a1a; }
    .ts-account-link--btn { width:100%; background:none; border:0; text-align:inherit; }
    .ts-account-card { padding:1rem 1.1rem; }
    .ts-account-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:1rem; padding-bottom:.75rem; border-bottom:1px solid #f0f0f0; }
    .ts-account-head h1 { margin:0; font-size:1.15rem; color:#333; font-weight:700; }
    .ts-account-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; margin-bottom:1rem; }
    .ts-account-stat { border:1px solid #ededed; border-radius:8px; padding:.85rem; background:#fff; }
    .ts-account-stat .v { font-size:1.15rem; font-weight:800; color:#333; }
    .ts-account-stat .l { font-size:.78rem; color:#777; margin-top:.1rem; }
    .ts-order-chip { display:inline-block; padding:.18rem .55rem; border-radius:999px; font-size:.75rem; font-weight:700; background:#f3f3f3; color:#555; }
    .ts-order-chip.pending,.ts-order-chip.processing,.ts-order-chip.paid,.ts-order-chip.shipped { background:#fff3e8; color:#c75b00; }
    .ts-order-chip.delivered { background:#eaf7ee; color:#1f7a3a; }
    .ts-order-chip.cancelled { background:#fdecec; color:#b42318; }
    @media (max-width: 991.98px) { .ts-account-layout { grid-template-columns:minmax(0,1fr); } .ts-account-sidebar { position:static; } .ts-account-stats { grid-template-columns:repeat(2,minmax(0,1fr)); } }
</style>
@endpush

@section('content')
<div class="container py-3 py-md-4">
    @include('customer.partials.account-shell')
</div>
@endsection
