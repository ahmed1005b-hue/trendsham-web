@extends('layouts.app')

@section('title', 'إضافة منتج - تريندشام')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - إضافة منتج</span>
        <a href="{{ route('seller.products.index') }}" class="btn btn-outline-secondary btn-sm">رجوع للمنتجات</a>
    </div>
</nav>

<div class="container">
    <div class="card">
        <div class="card-body">
            <form action="{{ route('seller.products.store') }}" method="POST" enctype="multipart/form-data">
                @include('seller.products._form')
            </form>
        </div>
    </div>
</div>
@endsection
