@extends('layouts.app')

@section('title', 'إدارة الأقسام - تريندشام')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - إدارة الأقسام</span>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المدير</a>
    </div>
</nav>

<div class="container">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">إضافة قسم جديد</h2>
                    <form action="{{ route('admin.categories.store') }}" method="POST">
                        @csrf
                        <div class="mb-2">
                            <label for="f-name" class="form-label small">اسم القسم</label>
                            <input id="f-name" type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label for="f-parent_id" class="form-label small">القسم الأب (اختياري)</label>
                            <select id="f-parent_id" name="parent_id" class="form-select">
                                <option value="">— قسم رئيسي —</option>
                                @foreach ($allCategories as $cat)
                                    <option value="{{ $cat->id }}" translate="no">{{ __('categories.'.$cat->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn btn-primary btn-sm w-100">إضافة</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>الاسم</th><th>مفعّل</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category)
                                <tr>
                                    <td class="fw-bold" translate="no">{{ __('categories.'.$category->name) }}</td>
                                    <td>
                                        <form action="{{ route('admin.categories.update', $category) }}" method="POST">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="name" value="{{ $category->name }}">
                                            <input type="hidden" name="parent_id" value="{{ $category->parent_id }}">
                                            <input type="checkbox" name="is_active" value="1" onchange="this.form.submit()" @checked($category->is_active)>
                                        </form>
                                    </td>
                                    <td class="text-end">
                                        <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="d-inline" data-confirm="حذف القسم؟">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">حذف</button>
                                        </form>
                                    </td>
                                </tr>
                                @foreach ($category->children as $child)
                                    <tr>
                                        <td class="ps-4 text-muted" translate="no">— {{ __('categories.'.$child->name) }}</td>
                                        <td>
                                            <form action="{{ route('admin.categories.update', $child) }}" method="POST">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="name" value="{{ $child->name }}">
                                                <input type="hidden" name="parent_id" value="{{ $child->parent_id }}">
                                                <input type="checkbox" name="is_active" value="1" onchange="this.form.submit()" @checked($child->is_active)>
                                            </form>
                                        </td>
                                        <td class="text-end">
                                            <form action="{{ route('admin.categories.destroy', $child) }}" method="POST" class="d-inline" data-confirm="حذف القسم؟">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger">حذف</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
