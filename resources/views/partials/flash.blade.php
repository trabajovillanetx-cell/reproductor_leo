@if (session('success'))
    <div class="mb-4 rounded-xl border border-emerald-300/50 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 shadow-lg ring-1 ring-white/40">
        {{ session('success') }}
    </div>
@endif
@if (session('warning'))
    <div class="mb-4 rounded-xl border border-amber-300/50 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-950 shadow-lg ring-1 ring-white/40">
        {{ session('warning') }}
    </div>
@endif
@if (session('error'))
    <div class="mb-4 rounded-xl border border-red-300/50 bg-red-50 px-4 py-3 text-sm font-medium text-red-900 shadow-lg ring-1 ring-white/40">
        {{ session('error') }}
    </div>
@endif
