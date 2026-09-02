<div class="ui-surface ui-filter-card p-3 mb-4">
    <form method="GET" action="{{ url()->current() }}" class="row g-3 align-items-end" data-submit-loading>
        <div class="col-md-4 col-lg-3">
            <label for="preset" class="form-label fw-semibold">Preset Periode</label>
            <select id="preset" name="preset" class="form-select">
                <option value="">Pilih preset</option>
                <option value="today" @selected(($filters['preset'] ?? null) === 'today')>Hari Ini</option>
                <option value="7days" @selected(($filters['preset'] ?? null) === '7days')>7 Hari Terakhir</option>
                <option value="month" @selected(($filters['preset'] ?? null) === 'month')>Bulan Ini</option>
            </select>
        </div>
        <div class="col-md-4 col-lg-3">
            <label for="date_from" class="form-label fw-semibold">Dari</label>
            <input id="date_from" name="date_from" type="date" class="form-control" value="{{ $filters['date_from'] }}">
        </div>
        <div class="col-md-4 col-lg-3">
            <label for="date_to" class="form-label fw-semibold">Sampai</label>
            <input id="date_to" name="date_to" type="date" class="form-control" value="{{ $filters['date_to'] }}">
        </div>
        <div class="col-md-6 col-lg-3">
            <label for="cashier_id" class="form-label fw-semibold">Kasir</label>
            <select id="cashier_id" name="cashier_id" class="form-select">
                <option value="">Semua kasir</option>
                @foreach ($cashiers as $cashier)
                    <option value="{{ $cashier->id }}" @selected((string) ($filters['cashier_id'] ?? '') === (string) $cashier->id)>{{ $cashier->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
            <a href="{{ url()->current() }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
        </div>
    </form>
</div>