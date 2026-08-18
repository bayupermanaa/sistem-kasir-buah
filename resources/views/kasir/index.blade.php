<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Halaman Kasir',
            'subtitle' => 'Cari produk, pilih berat, lalu selesaikan pembayaran tunai dengan cepat.',
            'icon' => 'bi-cash-coin',
        ])
    </x-slot>

    @php
        $formatRupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
        $productPayload = $stocks->map(function ($stock) {
            return [
                'stock_id' => $stock->id,
                'product_id' => $stock->product->id,
                'name' => $stock->product->name,
                'code' => $stock->product->code,
                'category' => $stock->product->category?->name,
                'category_id' => $stock->product->category_id,
                'unit' => $stock->product->unit?->name,
                'unit_symbol' => $stock->product->unit?->symbol,
                'price' => (float) $stock->product->selling_price,
                'stock' => (float) $stock->quantity,
                'stock_label' => $stock->statusLabel(),
                'stock_badge' => $stock->statusBadgeClass(),
                'image_url' => $stock->product->image_url,
                'search' => strtolower(trim(($stock->product->name ?? '').' '.($stock->product->code ?? '').' '.($stock->product->category?->name ?? ''))),
            ];
        })->values();
    @endphp

    <div class="ui-surface ui-banner p-4 mb-3">
        <div class="row align-items-center g-3">
            <div class="col-lg-7">
                <div class="d-inline-flex align-items-center gap-2 ui-badge-soft-primary px-3 py-2 rounded-pill fw-semibold mb-3">
                    <i class="bi bi-shop"></i>
                    Toko aktif: {{ $selectedOutlet?->name }}
                </div>
                <h2 class="fw-bold mb-2">Kasir siap dipakai</h2>
                <p class="text-muted mb-0">
                    Gunakan pencarian untuk menemukan produk, lalu atur berat di modal sebelum item masuk ke keranjang.
                    Semua transaksi tetap memakai toko utama.
                </p>
            </div>
            <div class="col-lg-5">
                <div class="ui-surface bg-white p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="ui-quick-action-icon">
                            <i class="bi bi-lightning-charge-fill fs-4"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Alur cepat</div>
                            <div class="text-muted small">Cari produk → pilih berat → simpan ke keranjang → checkout tunai.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ui-surface ui-filter-card p-3 p-lg-4 mb-3">
        <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-end">
            <div class="flex-grow-1">
                <label class="form-label fw-semibold" for="searchInput">Cari produk</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input
                        type="text"
                        id="searchInput"
                        class="form-control"
                        placeholder="Nama atau kode produk"
                        value="{{ $search }}"
                        autocomplete="off"
                    >
                </div>
            </div>

            <div class="flex-grow-1">
                <label class="form-label fw-semibold" for="categoryFilter">Filter kategori</label>
                <select id="categoryFilter" class="form-select form-select-lg">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $selectedCategoryId === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="d-grid d-lg-block">
                <button type="button" class="btn btn-outline-secondary btn-lg" id="clearSearchButton">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Bersihkan
                </button>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3 align-items-center justify-content-between">
            <div class="text-muted small">
                Menampilkan <span class="fw-semibold" id="visibleCount">{{ $stocks->count() }}</span> produk
            </div>
            <div class="text-muted small">
                <i class="bi bi-info-circle me-1"></i>Filter berjalan langsung di browser
            </div>
        </div>
    </div>

    <div id="noProductsMessage" class="ui-empty-state ui-surface d-none mb-3">
        <i class="bi bi-search"></i>
        <div class="fw-semibold">Produk tidak ditemukan</div>
        <div class="text-muted small">Coba ubah kata kunci atau kategori untuk menampilkan produk.</div>
    </div>

    <div class="row g-3 align-items-start">
        <div class="col-lg-8">
            <div class="row g-3" id="productGrid">
                @forelse ($stocks as $stock)
                    @php
                        $product = $stock->product;
                    @endphp
                    <div
                        class="col-md-6 product-card"
                        data-product-card
                        data-search="{{ strtolower(trim(($product->name ?? '').' '.($product->code ?? '').' '.($product->category?->name ?? ''))) }}"
                        data-category-id="{{ $product->category_id }}"
                    >
                        <div class="card ui-product-card h-100 border-0">
                            @if ($product->image_url)
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="ui-product-media">
                            @else
                                <div class="ui-product-placeholder">
                                    <i class="bi bi-image"></i>
                                </div>
                            @endif

                            <div class="card-body">
                                <div class="d-flex justify-content-between gap-2">
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold fs-5 mb-1">{{ $product->name }}</div>
                                        <div class="text-muted small">
                                            {{ $product->category?->name }} &middot; {{ $product->unit?->name }}
                                        </div>
                                    </div>
                                    <span class="badge {{ $stock->statusBadgeClass() }} align-self-start">{{ $stock->statusLabel() }}</span>
                                </div>

                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <span class="badge ui-badge-soft-primary">Kode {{ $product->code }}</span>
                                    <span class="badge ui-badge-soft-warning">Stok {{ number_format((float) $stock->quantity, 3, ',', '.') }} kg</span>
                                </div>

                                <div class="d-flex justify-content-between small">
                                    <span class="text-muted">Harga jual</span>
                                    <span class="fw-semibold">{{ $formatRupiah($product->selling_price) }}/kg</span>
                                </div>

                                <button
                                    type="button"
                                    class="btn btn-primary mt-auto js-add-product"
                                    data-stock='@json($productPayload->firstWhere("stock_id", $stock->id))'
                                >
                                    <i class="bi bi-plus-lg me-1"></i>Tambah
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="ui-empty-state ui-surface">
                            <i class="bi bi-box-seam"></i>
                            <div class="fw-semibold">Belum ada produk aktif</div>
                            <div class="text-muted small">Produk aktif dengan stok tersedia akan muncul di sini.</div>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="col-lg-4">
            <form method="POST" action="{{ route('kasir.store') }}" id="checkoutForm" class="card ui-cart-panel border-0 shadow-sm">
                @csrf
                <div class="card-header border-0 pb-0">
                    <div class="fw-bold fs-5">Keranjang</div>
                    <div class="text-muted small">Kelola item, pilih diskon, lalu lanjutkan ke pembayaran.</div>
                </div>

                <div class="card-body">
                    <div id="cartEmpty" class="ui-empty-state mb-3">
                        <i class="bi bi-cart3"></i>
                        <div class="fw-semibold">Keranjang masih kosong</div>
                        <div class="text-muted small">Tambahkan produk dari katalog untuk memulai transaksi.</div>
                    </div>

                    <div class="table-responsive mb-3">
                        <table class="table align-middle table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th class="text-end">Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="cartRows"></tbody>
                        </table>
                    </div>

                    <div id="cartHiddenInputs"></div>

                    <div class="ui-cart-summary mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <span id="cartSubtotal" class="fw-semibold">Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Diskon</span>
                            <span id="cartDiscount" class="fw-semibold">Rp 0</span>
                        </div>
                        <hr class="ui-soft-divider my-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Total Bayar</span>
                            <span id="cartTotal" class="ui-cart-total text-primary">Rp 0</span>
                        </div>
                    </div>

                    <div class="ui-surface bg-white border p-3 mb-3">
                        <div class="fw-semibold mb-3">Diskon</div>
                        <div class="row g-2 align-items-end">
                            <div class="col-5">
                                <label class="form-label small fw-semibold" for="discount_type">Tipe</label>
                                <select id="discount_type" name="discount_type" class="form-select">
                                    <option value="none">Tanpa diskon</option>
                                    <option value="amount">Nominal</option>
                                    <option value="percent">Persentase</option>
                                </select>
                            </div>
                            <div class="col-7">
                                <label class="form-label small fw-semibold" for="discount_value">Nilai</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white" id="discountPrefix">Rp</span>
                                    <input type="number" min="0" step="0.01" name="discount_value" class="form-control" id="discount_value" value="0">
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="discount" id="discount_amount" value="0">
                        <div class="text-muted small mt-2" id="discountHint">Gunakan nominal atau persen untuk potongan harga.</div>
                        @error('discount_value')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="ui-surface bg-white border p-3 mb-3">
                        <div class="fw-semibold mb-3">Metode Pembayaran</div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="ui-method-option active h-100 w-100" for="paymentMethodCash" data-payment-card="cash">
                                    <input class="form-check-input" type="radio" name="payment_method" id="paymentMethodCash" value="cash" checked>
                                    <span class="ui-quick-action-icon flex-shrink-0" style="width: 2.5rem; height: 2.5rem;">
                                        <i class="bi bi-cash-coin"></i>
                                    </span>
                                    <span>
                                        <span class="d-block fw-semibold">Tunai</span>
                                        <small class="text-muted">Input uang atau Uang Pas</small>
                                    </span>
                                </label>
                            </div>
                            <div class="col-6">
                                <label class="ui-method-option h-100 w-100" for="paymentMethodQris" data-payment-card="qris">
                                    <input class="form-check-input" type="radio" name="payment_method" id="paymentMethodQris" value="qris">
                                    <span class="ui-quick-action-icon flex-shrink-0" style="width: 2.5rem; height: 2.5rem;">
                                        <i class="bi bi-qr-code-scan"></i>
                                    </span>
                                    <span>
                                        <span class="d-block fw-semibold">QRIS</span>
                                        <small class="text-muted">Scan dan konfirmasi</small>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" id="cashPaymentSection">
                        <label class="form-label fw-semibold" for="cash_received">Uang diterima</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-white">Rp</span>
                            <input type="number" min="0" step="0.01" class="form-control @error('cash_received') is-invalid @enderror" name="cash_received" id="cash_received" placeholder="0">
                        </div>
                        <div class="d-flex justify-content-end mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="cashPasButton">
                                <i class="bi bi-lightning-charge me-1"></i>Uang Pas
                            </button>
                        </div>
                        @error('cash_received')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Kembalian</span>
                        <span id="cashChange" class="fw-semibold text-muted">Rp 0</span>
                    </div>

                    <div class="mb-3 d-none" id="qrisSection">
                        <div class="border rounded-4 p-3 bg-light">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <div class="ui-quick-action-icon flex-shrink-0">
                                    <i class="bi bi-qr-code-scan fs-4"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">QRIS siap dibayar</div>
                                    <div class="text-muted small">Tampilkan QR ini ke pembeli, lalu konfirmasi setelah pembayaran diterima.</div>
                                </div>
                            </div>
                            <div class="row g-3 align-items-center">
                                <div class="col-md-5">
                                    <div class="ui-qr-placeholder">
                                        <i class="bi bi-qr-code"></i>
                                        <span>QRIS</span>
                                    </div>
                                </div>
                                <div class="col-md-7">
                                    <div class="text-muted small mb-1">Total yang harus dibayar</div>
                                    <div class="fw-bold fs-4 mb-3" id="qrisTotalDisplay">Rp 0</div>
                                    <button type="button" class="btn btn-outline-primary w-100" id="qrisConfirmButton">
                                        <i class="bi bi-check2-circle me-1"></i>Pembayaran Diterima
                                    </button>
                                    <div class="text-muted small mt-2">Kembalian otomatis Rp 0 untuk QRIS.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @error('items')<div class="alert alert-danger mb-3">{{ $message }}</div>@enderror
                    @error('transaction')<div class="alert alert-warning mb-0">{{ $message }}</div>@enderror
                </div>

                <div class="card-footer border-0 pt-0">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg" id="checkoutButton" disabled>
                            <span class="spinner-border spinner-border-sm d-none me-2" id="checkoutSpinner" role="status" aria-hidden="true"></span>
                            Simpan Transaksi
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0" id="productModalTitle">Tambah Produk</h5>
                        <small class="text-muted" id="productModalSubtitle"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info border-0 small mb-3 d-none" id="productModalInfo">
                        Produk ini sudah ada di keranjang. Silakan ubah berat atau jumlahnya.
                    </div>
                    <input type="hidden" id="modalStockId">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <div class="fw-semibold" id="modalProductName"></div>
                            <span class="badge" id="modalStockBadge"></span>
                        </div>
                        <div class="text-muted small" id="modalProductMeta"></div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-primary w-100 js-quick-weight" data-kg="0.1">0,1 kg</button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-primary w-100 js-quick-weight" data-kg="0.25">0,25 kg</button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-primary w-100 js-quick-weight" data-kg="0.5">0,5 kg</button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-primary w-100 js-quick-weight" data-kg="0.7">0,7 kg</button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-primary w-100 js-quick-weight" data-kg="1">1 kg</button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-primary w-100 js-quick-weight" data-kg="1.5">1,5 kg</button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-primary w-100 js-quick-weight" data-kg="2">2 kg</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="modalWeightKg">Berat (kg)</label>
                        <input type="number" min="0.001" step="0.001" id="modalWeightKg" class="form-control">
                        <input type="hidden" id="modalWeightGrams">
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Estimasi subtotal</span>
                        <span class="fw-semibold fs-5" id="modalSubtotal">Rp 0</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="saveCartButton">Tambah ke Keranjang</button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="cartToast" class="toast align-items-center text-bg-success border-0 shadow" role="status" aria-live="polite" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="cartToastBody">Item keranjang berhasil diperbarui.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const products = @json($productPayload);
            const searchInput = document.getElementById('searchInput');
            const categoryFilter = document.getElementById('categoryFilter');
            const clearSearchButton = document.getElementById('clearSearchButton');
            const productCards = Array.from(document.querySelectorAll('[data-product-card]'));
            const visibleCount = document.getElementById('visibleCount');
            const noProductsMessage = document.getElementById('noProductsMessage');

            const cartRows = document.getElementById('cartRows');
            const cartHiddenInputs = document.getElementById('cartHiddenInputs');
            const cartEmpty = document.getElementById('cartEmpty');
            const cartSubtotal = document.getElementById('cartSubtotal');
            const cartDiscount = document.getElementById('cartDiscount');
            const cartTotal = document.getElementById('cartTotal');
            const cashReceived = document.getElementById('cash_received');
            const cashChange = document.getElementById('cashChange');
            const cashPasButton = document.getElementById('cashPasButton');
            const checkoutButton = document.getElementById('checkoutButton');
            const checkoutSpinner = document.getElementById('checkoutSpinner');
            const checkoutForm = document.getElementById('checkoutForm');
            const modalElement = document.getElementById('productModal');
            const productModal = new bootstrap.Modal(modalElement);
            const modalProductName = document.getElementById('modalProductName');
            const modalProductMeta = document.getElementById('modalProductMeta');
            const modalStockBadge = document.getElementById('modalStockBadge');
            const modalWeightKg = document.getElementById('modalWeightKg');
            const modalWeightGrams = document.getElementById('modalWeightGrams');
            const modalSubtotal = document.getElementById('modalSubtotal');
            const modalStockId = document.getElementById('modalStockId');
            const saveCartButton = document.getElementById('saveCartButton');
            const modalSubtitle = document.getElementById('productModalSubtitle');
            const modalTitle = document.getElementById('productModalTitle');
            const modalInfo = document.getElementById('productModalInfo');
            const cartToast = document.getElementById('cartToast');
            const cartToastBody = document.getElementById('cartToastBody');
            const discountTypeInput = document.getElementById('discount_type');
            const discountValueInput = document.getElementById('discount_value');
            const discountPrefix = document.getElementById('discountPrefix');
            const discountHint = document.getElementById('discountHint');
            const discountAmountInput = document.getElementById('discount_amount');
            const paymentMethodCash = document.getElementById('paymentMethodCash');
            const paymentMethodQris = document.getElementById('paymentMethodQris');
            const cashPaymentSection = document.getElementById('cashPaymentSection');
            const qrisSection = document.getElementById('qrisSection');
            const qrisTotalDisplay = document.getElementById('qrisTotalDisplay');
            const qrisConfirmButton = document.getElementById('qrisConfirmButton');

            const cart = [];
            let editingIndex = null;
            let activeProduct = null;
            let qrisConfirmed = false;
            let currentTotals = {
                subtotal: 0,
                discountAmount: 0,
                total: 0,
            };

            const formatRupiah = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(Number(value || 0));

            const formatKg = (value) => Number(value || 0).toLocaleString('id-ID', {
                minimumFractionDigits: 3,
                maximumFractionDigits: 3,
            });

            const formatGrams = (value) => Number(value || 0).toLocaleString('id-ID');

            const roundKg = (value) => Math.round(Number(value || 0) * 1000) / 1000;
            const roundMoney = (value) => Math.round((Number(value || 0) + Number.EPSILON) * 100) / 100;
            const kgToGrams = (value) => Math.max(1, Math.round(roundKg(value) * 1000));

            const findProduct = (stockId) => products.find((item) => Number(item.stock_id) === Number(stockId));
            const getPaymentMethod = () => document.querySelector('input[name="payment_method"]:checked')?.value ?? 'cash';

            const setModalMode = (isEditing) => {
                modalTitle.textContent = isEditing ? 'Edit Produk di Keranjang' : 'Tambah Produk';
                saveCartButton.textContent = isEditing ? 'Perbarui Keranjang' : 'Tambah ke Keranjang';
                modalInfo.classList.toggle('d-none', !isEditing);
            };

            const showCartToast = (message) => {
                if (!window.bootstrap?.Toast || !cartToast) {
                    return;
                }

                cartToastBody.textContent = message;
                bootstrap.Toast.getOrCreateInstance(cartToast, {
                    delay: 2200,
                }).show();
            };

            const focusSearch = () => {
                searchInput.focus();
                searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
            };

            const filterProducts = () => {
                const keyword = searchInput.value.trim().toLowerCase();
                const categoryId = categoryFilter.value;
                let visible = 0;

                productCards.forEach((card) => {
                    const matchesKeyword = keyword === '' || card.dataset.search.includes(keyword);
                    const matchesCategory = categoryId === '' || card.dataset.categoryId === categoryId;
                    const showCard = matchesKeyword && matchesCategory;

                    card.classList.toggle('d-none', !showCard);
                    if (showCard) {
                        visible += 1;
                    }
                });

                visibleCount.textContent = visible;
                noProductsMessage.classList.toggle('d-none', visible !== 0);
            };

            const getCartSubtotal = () => cart.reduce((sum, item) => sum + Number(item.subtotal || 0), 0);

            const getDiscountAmount = (subtotal) => {
                const mode = discountTypeInput.value;
                const rawValue = Math.max(0, Number(discountValueInput.value || 0));

                if (mode === 'amount') {
                    return Math.min(rawValue, subtotal);
                }

                if (mode === 'percent') {
                    return roundMoney(subtotal * (Math.min(rawValue, 100) / 100));
                }

                return 0;
            };

            const syncDiscountUi = () => {
                const mode = discountTypeInput.value;

                if (mode === 'none') {
                    discountPrefix.textContent = 'Rp';
                    discountHint.textContent = 'Diskon dinonaktifkan. Pilih nominal atau persen jika perlu.';
                    discountValueInput.value = '0';
                    discountValueInput.disabled = true;
                    discountValueInput.removeAttribute('max');
                } else if (mode === 'amount') {
                    discountPrefix.textContent = 'Rp';
                    discountHint.textContent = 'Masukkan nominal diskon dalam rupiah.';
                    discountValueInput.disabled = false;
                    discountValueInput.removeAttribute('max');
                } else {
                    discountPrefix.textContent = '%';
                    discountHint.textContent = 'Masukkan diskon persen hingga maksimal 100%.';
                    discountValueInput.disabled = false;
                    discountValueInput.setAttribute('max', '100');
                }
            };

            const updatePaymentMethodUi = () => {
                const method = getPaymentMethod();

                document.querySelectorAll('[data-payment-card]').forEach((label) => {
                    label.classList.toggle('active', label.dataset.paymentCard === method);
                });

                cashPaymentSection.classList.toggle('d-none', method !== 'cash');
                qrisSection.classList.toggle('d-none', method !== 'qris');

                if (method === 'qris') {
                    cashReceived.value = '';
                    cashReceived.classList.remove('is-invalid');
                }
            };

            const updateCashChange = (totalValue = currentTotals.total) => {
                const method = getPaymentMethod();

                if (method !== 'cash') {
                    cashChange.textContent = formatRupiah(0);
                    cashChange.classList.remove('text-success');
                    cashChange.classList.add('text-muted');
                    return;
                }

                const cashValue = Number(cashReceived.value || 0);
                const change = cashValue - totalValue;

                cashChange.textContent = formatRupiah(change > 0 ? change : 0);
                cashChange.classList.toggle('text-success', cashValue >= totalValue && totalValue > 0);
                cashChange.classList.toggle('text-muted', cashValue < totalValue || totalValue === 0);
            };

            const updateCheckoutState = (totalValue = currentTotals.total) => {
                const method = getPaymentMethod();

                if (cart.length === 0) {
                    checkoutButton.disabled = true;
                    if (method === 'qris') {
                        qrisConfirmButton.disabled = true;
                    }
                    return;
                }

                if (method === 'cash') {
                    checkoutButton.disabled = Number(cashReceived.value || 0) < totalValue;
                    qrisConfirmButton.disabled = true;
                    return;
                }

                checkoutButton.disabled = !qrisConfirmed;
                qrisConfirmButton.disabled = qrisConfirmed;
                qrisConfirmButton.innerHTML = qrisConfirmed
                    ? '<i class="bi bi-check2-circle me-1"></i>Pembayaran Diterima'
                    : '<i class="bi bi-check2-circle me-1"></i>Konfirmasi Pembayaran';
            };

            const refreshSummary = (resetQris = false) => {
                const subtotal = getCartSubtotal();
                const discountAmount = getDiscountAmount(subtotal);
                const total = roundMoney(Math.max(0, subtotal - discountAmount));

                currentTotals = {
                    subtotal: roundMoney(subtotal),
                    discountAmount,
                    total,
                };

                cartSubtotal.textContent = formatRupiah(currentTotals.subtotal);
                cartDiscount.textContent = formatRupiah(currentTotals.discountAmount);
                cartTotal.textContent = formatRupiah(currentTotals.total);
                discountAmountInput.value = currentTotals.discountAmount.toFixed(2);
                qrisTotalDisplay.textContent = formatRupiah(currentTotals.total);

                if (resetQris) {
                    qrisConfirmed = false;
                }

                updatePaymentMethodUi();
                updateCashChange(currentTotals.total);
                updateCheckoutState(currentTotals.total);
            };

            const renderCart = (resetQris = true) => {
                cartRows.innerHTML = '';
                cartHiddenInputs.innerHTML = '';

                cart.forEach((item, index) => {
                    const row = document.createElement('tr');
                    row.className = 'ui-cart-row';
                    row.innerHTML = `
                        <td>
                            <div class="ui-cart-item-title">${item.name}</div>
                            <div class="ui-cart-item-meta">${formatKg(item.quantity_kg)} kg (${formatGrams(item.weight_grams)} g) &middot; ${formatRupiah(item.price)}/kg</div>
                        </td>
                        <td class="text-end fw-semibold">${formatRupiah(item.subtotal)}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary js-edit-item" data-index="${index}" title="Edit" data-bs-toggle="tooltip">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger js-remove-item" data-index="${index}" title="Hapus" data-bs-toggle="tooltip">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    `;
                    cartRows.appendChild(row);

                    const productInput = document.createElement('input');
                    productInput.type = 'hidden';
                    productInput.name = `items[${index}][product_id]`;
                    productInput.value = item.product_id;

                    const quantityInput = document.createElement('input');
                    quantityInput.type = 'hidden';
                    quantityInput.name = `items[${index}][quantity]`;
                    quantityInput.value = item.quantity_kg.toFixed(3);

                    const gramsInput = document.createElement('input');
                    gramsInput.type = 'hidden';
                    gramsInput.name = `items[${index}][weight_grams]`;
                    gramsInput.value = item.weight_grams;

                    cartHiddenInputs.appendChild(productInput);
                    cartHiddenInputs.appendChild(quantityInput);
                    cartHiddenInputs.appendChild(gramsInput);
                });

                cartEmpty.classList.toggle('d-none', cart.length > 0);
                refreshSummary(resetQris);

                if (window.bootstrap?.Tooltip) {
                    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
                        const existing = bootstrap.Tooltip.getInstance(element);
                        if (existing) {
                            existing.dispose();
                        }
                        new bootstrap.Tooltip(element);
                    });
                }
            };

            const openModalForStock = (stockId, index = null) => {
                const product = findProduct(stockId);
                if (!product) {
                    return;
                }

                activeProduct = product;
                editingIndex = index;
                setModalMode(index !== null);
                modalStockId.value = product.stock_id;
                modalProductName.textContent = product.name;
                modalProductMeta.textContent = `${product.category ?? '-'} &middot; ${product.unit_symbol ?? '-'} &middot; ${formatRupiah(product.price)}/kg`;
                modalStockBadge.className = `badge ${product.stock_badge}`;
                modalStockBadge.textContent = product.stock_label;
                modalSubtitle.textContent = `${product.code} &middot; Stok tersedia ${formatKg(product.stock)} kg`;

                const startingKg = index !== null && cart[index]
                    ? Number(cart[index].quantity_kg || cart[index].weight_grams / 1000 || 0.1)
                    : 0.1;

                modalWeightKg.value = roundKg(startingKg).toFixed(3);
                modalWeightGrams.value = kgToGrams(startingKg);
                modalWeightKg.classList.remove('is-invalid');
                updateModalSubtotal();
                productModal.show();
            };

            const updateModalSubtotal = () => {
                if (!activeProduct) {
                    return;
                }

                const kilograms = roundKg(modalWeightKg.value);
                const grams = kgToGrams(kilograms);

                modalWeightGrams.value = grams;
                modalSubtotal.textContent = formatRupiah(kilograms * Number(activeProduct.price || 0));
            };

            const saveItem = () => {
                if (!activeProduct) {
                    return;
                }

                const kilograms = roundKg(modalWeightKg.value);
                const grams = kgToGrams(kilograms);

                if (!kilograms || grams < 1) {
                    modalWeightKg.classList.add('is-invalid');
                    return;
                }

                modalWeightKg.classList.remove('is-invalid');

                const item = {
                    stock_id: activeProduct.stock_id,
                    product_id: activeProduct.product_id,
                    name: activeProduct.name,
                    quantity_kg: kilograms,
                    weight_grams: grams,
                    price: Number(activeProduct.price || 0),
                    subtotal: roundMoney(kilograms * Number(activeProduct.price || 0)),
                };

                let isUpdate = false;

                if (editingIndex === null) {
                    const existingIndex = cart.findIndex((current) => Number(current.product_id) === Number(item.product_id));
                    if (existingIndex >= 0) {
                        cart[existingIndex] = item;
                        isUpdate = true;
                    } else {
                        cart.push(item);
                    }
                } else {
                    cart[editingIndex] = item;
                    isUpdate = true;
                }

                productModal.hide();
                renderCart(true);

                if (isUpdate) {
                    showCartToast('Item keranjang berhasil diperbarui.');
                }

                focusSearch();
            };

            const setPaymentMethod = (method) => {
                if (method === 'qris') {
                    qrisConfirmed = false;
                }

                updatePaymentMethodUi();
                refreshSummary(true);
            };

            document.querySelectorAll('.js-add-product').forEach((button) => {
                button.addEventListener('click', () => {
                    const productData = JSON.parse(button.dataset.stock);
                    const existingIndex = cart.findIndex((current) => Number(current.product_id) === Number(productData.product_id));
                    openModalForStock(productData.stock_id, existingIndex >= 0 ? existingIndex : null);
                });
            });

            document.querySelectorAll('.js-quick-weight').forEach((button) => {
                button.addEventListener('click', () => {
                    modalWeightKg.value = Number(button.dataset.kg || 0).toFixed(3);
                    updateModalSubtotal();
                });
            });

            modalWeightKg.addEventListener('input', updateModalSubtotal);
            saveCartButton.addEventListener('click', saveItem);
            searchInput.addEventListener('input', filterProducts);
            categoryFilter.addEventListener('change', filterProducts);

            clearSearchButton.addEventListener('click', () => {
                searchInput.value = '';
                categoryFilter.value = '';
                filterProducts();
                focusSearch();
            });

            discountTypeInput.addEventListener('change', () => {
                syncDiscountUi();
                qrisConfirmed = false;
                refreshSummary(true);
            });

            discountValueInput.addEventListener('input', () => {
                if (discountTypeInput.value === 'percent' && Number(discountValueInput.value || 0) > 100) {
                    discountValueInput.value = '100';
                }

                qrisConfirmed = false;
                refreshSummary(true);
            });

            [paymentMethodCash, paymentMethodQris].forEach((radio) => {
                radio.addEventListener('change', () => {
                    setPaymentMethod(radio.value);
                });
            });

            cashReceived.addEventListener('input', () => {
                refreshSummary(false);
            });

            cashPasButton.addEventListener('click', () => {
                if (getPaymentMethod() !== 'cash') {
                    return;
                }

                cashReceived.value = currentTotals.total.toFixed(2);
                cashReceived.dispatchEvent(new Event('input', { bubbles: true }));
            });

            qrisConfirmButton.addEventListener('click', () => {
                if (cart.length === 0) {
                    return;
                }

                qrisConfirmed = true;
                refreshSummary(false);
            });

            modalElement.addEventListener('hidden.bs.modal', () => {
                activeProduct = null;
                editingIndex = null;
                setModalMode(false);
                modalWeightKg.classList.remove('is-invalid');
            });

            cartRows.addEventListener('click', (event) => {
                const editButton = event.target.closest('.js-edit-item');
                const removeButton = event.target.closest('.js-remove-item');

                if (editButton) {
                    openModalForStock(cart[Number(editButton.dataset.index)].stock_id, Number(editButton.dataset.index));
                }

                if (removeButton) {
                    cart.splice(Number(removeButton.dataset.index), 1);
                    renderCart(true);
                }
            });

            checkoutForm.addEventListener('submit', (event) => {
                if (cart.length === 0) {
                    event.preventDefault();
                    return;
                }

                const { total } = currentTotals;
                const method = getPaymentMethod();

                if (method === 'cash') {
                    const cashValue = Number(cashReceived.value || 0);
                    if (cashValue < total) {
                        event.preventDefault();
                        cashReceived.classList.add('is-invalid');
                        cashReceived.focus();
                        return;
                    }
                }

                if (method === 'qris' && !qrisConfirmed) {
                    event.preventDefault();
                    qrisConfirmButton.focus();
                    return;
                }

                checkoutButton.disabled = true;
                checkoutSpinner.classList.remove('d-none');
            });

            syncDiscountUi();
            updatePaymentMethodUi();
            renderCart(false);
            filterProducts();
            focusSearch();
        });
    </script>
</x-app-layout>
