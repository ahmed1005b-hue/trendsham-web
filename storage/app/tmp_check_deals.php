<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$deals = App\Models\DailyDeal::with('product')->orderByDesc('id')->limit(10)->get();
foreach ($deals as $d) {
    $live = $d->product ? $d->product->isLive() : false;
    $active = $d->is_active && $d->starts_at <= now() && $d->ends_at >= now() && ($d->max_quantity==0 || $d->sold_quantity < $d->max_quantity);
    echo $d->id.'|pid='.$d->product_id.'|'.($d->product?$d->product->name:'null').'|active='.$d->is_active.'|window='.($d->starts_at <= now() && $d->ends_at >= now() ? '1':'0').'|live='.($live?'1':'0').'|visible='.($active && $live?'1':'0').'|spec='.$d->special_price.'|disc='.$d->discount_percentage.'|ends='.$d->ends_at.PHP_EOL;
}
