<div class="ts-account-layout">
    <aside class="ts-account-sidebar" aria-label="قائمة حسابي">
        <div class="ts-account-user">
            <div class="ts-account-avatar">
                @if (auth()->user()->avatar)
                    <img src="{{ asset('storage/'.auth()->user()->avatar) }}" alt="" width="44" height="44">
                @else
                    <i class="bi bi-person-circle" aria-hidden="true"></i>
                @endif
            </div>
            <div class="min-w-0">
                <div class="ts-account-name text-truncate">{{ auth()->user()->name }}</div>
                <div class="ts-account-sub text-truncate">{{ auth()->user()->email ?: auth()->user()->phone }}</div>
            </div>
        </div>
        <nav class="ts-account-nav">
            @php($sp = auth()->user()?->sellerProfile)
            <div class="ts-account-group">طلباتي</div>
            <a class="ts-account-link {{ request()->routeIs('customer.orders.*') ? 'active' : '' }}" href="{{ route('customer.orders.index') }}"><i class="bi bi-box-seam"></i> جميع طلباتي</a>
            <a class="ts-account-link {{ request()->routeIs('customer.returns.*') ? 'active' : '' }}" href="{{ route('customer.returns.index') }}"><i class="bi bi-arrow-return-left"></i> {{ __('طلبات الإرجاع') }}</a>
            <a class="ts-account-link {{ request()->routeIs('customer.favorites.*') ? 'active' : '' }}" href="{{ route('customer.favorites.index') }}"><i class="bi bi-star"></i> المفضلة</a>
            <a class="ts-account-link {{ request()->routeIs('customer.followed-stores.*') ? 'active' : '' }}" href="{{ route('customer.followed-stores.index') }}"><i class="bi bi-shop"></i> المتاجر التي أتابعها</a>
            <div class="ts-account-group">التسوق</div>
            <a class="ts-account-link {{ request()->routeIs('customer.cart.*') ? 'active' : '' }}" href="{{ route('customer.cart.index') }}"><i class="bi bi-bag"></i> سلة التسوق</a>
            <a class="ts-account-link {{ request()->routeIs('customer.conversations.*') ? 'active' : '' }}" href="{{ route('customer.conversations.index') }}"><i class="bi bi-chat-dots"></i> رسائل المتاجر</a>
            <div class="ts-account-group">حسابي</div>
            <a class="ts-account-link {{ request()->routeIs('customer.dashboard') ? 'active' : '' }}" href="{{ route('customer.dashboard') }}"><i class="bi bi-grid"></i> نظرة عامة</a>
            <a class="ts-account-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.edit') }}"><i class="bi bi-person-gear"></i> معلومات المستخدم</a>
            <a class="ts-account-link {{ request()->routeIs('customer.cards.*') ? 'active' : '' }}" href="{{ route('customer.cards.index') }}"><i class="bi bi-credit-card"></i> بطاقاتي البنكية</a>
            @if (auth()->user()?->role === 'customer')
                <a class="ts-account-link {{ request()->routeIs('customer.coupons.*') ? 'active' : '' }}" href="{{ route('customer.coupons.index') }}"><i class="bi bi-ticket-perforated"></i> كوبوناتي</a>
                <a class="ts-account-link {{ request()->routeIs('customer.loyalty.*') ? 'active' : '' }}" href="{{ route('customer.loyalty.index') }}"><i class="bi bi-gem"></i> نقاطي
                    <span class="badge rounded-pill ms-auto" style="background:#C9962C">{{ number_format((int) \App\Loyalty\Models\LoyaltyAccount::where('user_id', auth()->id())->value('balance')) }}</span></a>
            @endif
            @if (auth()->user()?->role !== 'admin')
                @if ($sp?->status === 'approved')
                    <a class="ts-account-link" href="{{ route('seller.dashboard') }}"><i class="bi bi-shop-window"></i> لوحة متجري <span class="badge bg-success ms-auto">موثق</span></a>
                @else
                    <a class="ts-account-link {{ request()->routeIs('customer.seller-application.*','seller.identity-verification.*') ? 'active' : '' }}" href="{{ route('customer.seller-application.create') }}">
                        <i class="bi bi-patch-check"></i> {{ $sp ? 'التحقق من هوية البائع' : 'افتح متجرك' }}
                        @if ($sp?->status === 'pending') <span class="badge bg-warning text-dark ms-auto">قيد المراجعة</span>
                        @elseif ($sp?->status === 'rejected') <span class="badge bg-danger ms-auto">مرفوض</span>
                        @endif
                    </a>
                @endif
            @endif
            <form action="{{ route('logout') }}" method="POST" class="mt-1">
                @csrf
                <button type="submit" class="ts-account-link ts-account-link--btn"><i class="bi bi-box-arrow-right"></i> تسجيل الخروج</button>
            </form>
        </nav>
    </aside>
    <section class="ts-account-main">
        @yield('account_content')
    </section>
</div>
