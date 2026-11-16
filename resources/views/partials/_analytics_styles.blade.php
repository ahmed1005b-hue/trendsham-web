<style>
    /* ألوان الصفحة من متغيرات الموقع (ts-app.css) ويُعاد تعريفها في الوضع الداكن (ts-dark.css) */
    .ts-an {
        --an-surface: var(--ts-surface);
        --an-surface-2: var(--ts-surface-2);
        --an-border: var(--ts-border);
        --an-ink: var(--ts-ink);
        --an-muted: var(--ts-muted);
        --an-accent: var(--ts-emerald);
        --an-gold: var(--ts-gold);
        --an-blue: #0F6EBA;
        --an-up-bg: #D4EDDA;
        --an-up: #155724;
        --an-down-bg: #F8D7DA;
        --an-down: #721C24;
        color: var(--an-ink);
    }
    html.dark .ts-an {
        --an-accent: var(--tsd-accent);
        --an-gold: var(--tsd-gold);
        --an-blue: #8BB9FF;
        --an-up-bg: rgba(40, 167, 69, .18);
        --an-up: #7ED8A8;
        --an-down-bg: rgba(220, 53, 69, .18);
        --an-down: #FF8F8F;
    }

    .an-heading { font-family: 'Reem Kufi', sans-serif; font-weight: 700; color: var(--an-ink); }
    .an-muted { color: var(--an-muted); }

    .an-card {
        background: var(--an-surface);
        border: 1px solid var(--an-border);
        border-radius: 16px;
        padding: 1.1rem;
        block-size: 100%;
    }
    .an-title { color: var(--an-muted); font-size: .85rem; font-weight: 700; margin-block-end: .4rem; display: flex; align-items: center; gap: .5rem; }
    .an-title i { color: var(--an-gold); }
    .an-value { font-family: 'Reem Kufi', sans-serif; font-size: 1.4rem; font-weight: 700; color: var(--an-ink); line-height: 1.3; overflow-wrap: anywhere; }
    .an-value small { font-family: 'Tajawal', sans-serif; font-size: .8rem; font-weight: 500; color: var(--an-muted); }
    .an-sub { font-size: .78rem; color: var(--an-muted); margin-block-start: .35rem; }

    .an-change { display: inline-flex; align-items: center; font-size: .78rem; font-weight: 700; border-radius: 999px; padding: .1rem .6rem; margin-block-start: .4rem; }
    .an-change.up { background: var(--an-up-bg); color: var(--an-up); }
    .an-change.down { background: var(--an-down-bg); color: var(--an-down); }
    .an-change.flat { background: var(--an-surface-2); color: var(--an-muted); }

    .an-section { font-family: 'Reem Kufi', sans-serif; font-size: 1.1rem; font-weight: 700; color: var(--an-ink); margin-block: 0 .75rem; display: flex; align-items: center; gap: .5rem; }
    .an-section i { color: var(--an-gold); }

    .an-days { display: flex; flex-wrap: wrap; gap: .5rem; }
    .an-days a { border: 1px solid var(--an-border); background: var(--an-surface); color: var(--an-ink); border-radius: 999px; font-size: .8rem; font-weight: 700; padding: .3rem .95rem; text-decoration: none; }
    .an-days a:hover { border-color: var(--an-gold); color: var(--an-ink); }
    .an-days a.active { background: var(--ts-emerald); border-color: var(--ts-emerald); color: #fff; }

    .an-chip { display: inline-block; background: var(--an-surface-2); color: var(--an-ink); border-radius: 999px; padding: .25rem .7rem; font-size: .75rem; font-weight: 600; margin: 0 0 .35rem; margin-inline-end: .35rem; }

    .ts-an .an-table { --bs-table-bg: transparent; --bs-table-color: var(--an-ink); margin: 0; }
    .ts-an .an-table thead th { background: var(--an-surface-2); color: var(--an-muted); font-size: .78rem; font-weight: 700; border: 0; padding: .7rem .85rem; white-space: nowrap; }
    .ts-an .an-table tbody td { background: transparent; color: var(--an-ink); border-block-end: 1px solid var(--an-border); padding: .7rem .85rem; vertical-align: middle; overflow-wrap: anywhere; }
    .ts-an .an-table tbody tr:last-child td { border-block-end: 0; }
    .an-credit { color: var(--an-up); }
    .an-debit { color: var(--an-down); }

    .an-chart { position: relative; }
    .an-empty { text-align: center; color: var(--an-muted); padding: 2rem .5rem; font-size: .9rem; }
</style>
