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
                            @if ($product->image)
                                <img src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}" class="ui-product-media">
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
                <input type="hidden" name="payment_method" value="cash">
                <input type="hidden" name="discount" value="0">
                <div class="card-header border-0 pb-0">
                    <div class="fw-bold fs-5">Keranjang</div>
                    <div class="text-muted small">Kelola item lalu lanjutkan ke checkout tunai.</div>
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
                            <span class="fw-semibold">Total</span>
                            <span id="cartTotal" class="ui-cart-total text-primary">Rp 0</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="cash_received">Uang diterima</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-white">Rp</span>
                            <input type="number" min="0" step="0.01" class="form-control @error('cash_received') is-invalid @enderror" name="cash_received" id="cash_received" placeholder="0">
                        </div>
                        @error('cash_received')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Kembalian</span>
                        <span id="cashChange" class="fw-semibold text-muted">Rp 0</span>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="ui-method-option active">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" checked disabled>
                                    <label class="form-check-label" for="">
                                        <span class="ui-quick-action-icon flex-shrink-0" style="width: 2.5rem; height: 2.5rem;">
                                            <i class="bi bi-cash-coin"></i>
                                        </span>
                                        <span>
                                            <span class="d-block fw-semibold">Tunai</span>
                                            <small class="text-muted">Metode aktif</small>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="ui-method-option">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" disabled>
                                    <label class="form-check-label" for="">
                                        <span class="ui-quick-action-icon flex-shrink-0" style="width: 2.5rem; height: 2.5rem;">
                                            <i class="bi bi-qr-code-scan"></i>
                                        </span>
                                        <span>
                                            <span class="d-block fw-semibold">QRIS</span>
                                            <small class="text-muted">Siap bila diaktifkan</small>
                                        </span>
                                    </label>
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
                            <button type="button" class="btn btn-outline-primary w-100 js-quick-weight" data-grams="100">0,1 kg</button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-primary w-100 js-quick-weight" data-grams="500">0,5 kg</button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-primary w-100 js-quick-weight" data-grams="700">0,7 kg</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="modalWeightGrams">Berat (gram)</label>
                        <input type="number" min="1" step="1" id="modalWeightGrams" class="form-control">
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

            const formatRupiah = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0
            }).format(value || 0);

            const formatWeight = (grams) => {
                const kilogram = grams / 1000;
                return `${kilogram.toLocaleString('id-ID', { minimumFractionDigits: 3, maximumFractionDigits: 3 })} kg (${grams.toLocaleString('id-ID')} g)`;
            };

            const cart = [];
            let editingIndex = null;

            const cartRows = document.getElementById('cartRows');
            const cartHiddenInputs = document.getElementById('cartHiddenInputs');
            const cartEmpty = document.getElementById('cartEmpty');
            const cartSubtotal = document.getElementById('cartSubtotal');
            const cartDiscount = document.getElementById('cartDiscount');
            const cartTotal = document.getElementById('cartTotal');
            const cashReceived = document.getElementById('cash_received');
            const cashChange = document.getElementById('cashChange');
            const checkoutButton = document.getElementById('checkoutButton');
            const checkoutSpinner = document.getElementById('checkoutSpinner');
            const checkoutForm = document.getElementById('checkoutForm');
            const modalElement = document.getElementById('productModal');
            const productModal = new bootstrap.Modal(modalElement);
            const modalProductName = document.getElementById('modalProductName');
            const modalProductMeta = document.getElementById('modalProductMeta');
            const modalStockBadge = document.getElementById('modalStockBadge');
            const modalWeightGrams = document.getElementById('modalWeightGrams');
            const modalSubtotal = document.getElementById('modalSubtotal');
            const modalStockId = document.getElementById('modalStockId');
            const saveCartButton = document.getElementById('saveCartButton');
            const modalSubtitle = document.getElementById('productModalSubtitle');
            const modalTitle = document.getElementById('productModalTitle');
            const modalInfo = document.getElementById('productModalInfo');
            const cartToast = document.getElementById('cartToast');
            const cartToastBody = document.getElementById('cartToastBody');
            let activeProduct = null;

            const findProduct = (stockId) => products.find((item) => Number(item.stock_id) === Number(stockId));

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

            const renderCart = () => {
                cartRows.innerHTML = '';
                cartHiddenInputs.innerHTML = '';

                let subtotal = 0;

                cart.forEach((item, index) => {
                    subtotal += item.subtotal;

                    const row = document.createElement('tr');
                    row.className = 'ui-cart-row';
                    row.innerHTML = `
                        <td>
                            <div class="ui-cart-item-title">${item.name}</div>
                            <div class="ui-cart-item-meta">${formatWeight(item.weight_grams)} · ${formatRupiah(item.price)}</div>
                        </td>
                        <td class="text-end fw-semibold">${formatRupiah(item.subtotal)}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary js-edit-item" data-index="${index}" title="Ubah" data-bs-toggle="tooltip">
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

                    const gramsInput = document.createElement('input');
                    gramsInput.type = 'hidden';
                    gramsInput.name = `items[${index}][weight_grams]`;
                    gramsInput.value = item.weight_grams;

                    cartHiddenInputs.appendChild(productInput);
                    cartHiddenInputs.appendChild(gramsInput);
                });

                cartEmpty.classList.toggle('d-none', cart.length > 0);
                cartSubtotal.textContent = formatRupiah(subtotal);
                cartDiscount.textContent = formatRupiah(0);
                cartTotal.textContent = formatRupiah(subtotal);

                updateCashChange();
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

            const updateCashChange = () => {
                const totalValue = cart.reduce((sum, item) => sum + item.subtotal, 0);
                const cashValue = Number(cashReceived.value || 0);
                const change = cashValue - totalValue;
                cashChange.textContent = formatRupiah(change > 0 ? change : 0);
                cashChange.classList.toggle('text-success', change >= 0 && totalValue > 0);
                cashChange.classList.toggle('text-muted', change < 0 || totalValue === 0);
                checkoutButton.disabled = cart.length === 0 || cashValue < totalValue;
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
                modalProductMeta.textContent = `${product.category ?? '-'} · ${product.unit_symbol ?? '-'} · ${formatRupiah(product.price)}/kg`;
                modalStockBadge.className = `badge ${product.stock_badge}`;
                modalStockBadge.textContent = product.stock_label;
                modalSubtitle.textContent = `${product.code} · Stok tersedia ${product.stock.toLocaleString('id-ID', { minimumFractionDigits: 3, maximumFractionDigits: 3 })} kg`;

                modalWeightGrams.value = index !== null && cart[index] ? cart[index].weight_grams : 100;
                updateModalSubtotal();
                productModal.show();
            };

            const updateModalSubtotal = () => {
                if (!activeProduct) {
                    return;
                }

                const grams = Number(modalWeightGrams.value || 0);
                modalSubtotal.textContent = formatRupiah((grams / 1000) * Number(activeProduct.price || 0));
            };

            const saveItem = () => {
                if (!activeProduct) {
                    return;
                }

                const grams = Number(modalWeightGrams.value || 0);
                if (!grams || grams < 1) {
                    modalWeightGrams.classList.add('is-invalid');
                    return;
                }

                modalWeightGrams.classList.remove('is-invalid');

                const subtotal = (grams / 1000) * Number(activeProduct.price || 0);
                const item = {
                    stock_id: activeProduct.stock_id,
                    product_id: activeProduct.product_id,
                    name: activeProduct.name,
                    weight_grams: grams,
                    price: Number(activeProduct.price || 0),
                    subtotal: subtotal,
                };

                if (editingIndex === null) {
                    const existingIndex = cart.findIndex((current) => Number(current.product_id) === Number(item.product_id));
                    if (existingIndex >= 0) {
                        cart[existingIndex] = item;
                        showCartToast('Item keranjang berhasil diperbarui.');
                    } else {
                        cart.push(item);
                    }
                } else {
                    cart[editingIndex] = item;
                    showCartToast('Item keranjang berhasil diperbarui.');
                }

                productModal.hide();
                renderCart();
                focusSearch();
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
                    modalWeightGrams.value = button.dataset.grams;
                    updateModalSubtotal();
                });
            });

            modalWeightGrams.addEventListener('input', updateModalSubtotal);
            saveCartButton.addEventListener('click', saveItem);
            searchInput.addEventListener('input', filterProducts);
            categoryFilter.addEventListener('change', filterProducts);

            clearSearchButton.addEventListener('click', () => {
                searchInput.value = '';
                categoryFilter.value = '';
                filterProducts();
                focusSearch();
            });

            modalElement.addEventListener('hidden.bs.modal', () => {
                activeProduct = null;
                editingIndex = null;
                setModalMode(false);
            });

            cartRows.addEventListener('click', (event) => {
                const editButton = event.target.closest('.js-edit-item');
                const removeButton = event.target.closest('.js-remove-item');

                if (editButton) {
                    openModalForStock(cart[Number(editButton.dataset.index)].stock_id, Number(editButton.dataset.index));
                }

                if (removeButton) {
                    cart.splice(Number(removeButton.dataset.index), 1);
                    renderCart();
                }
            });

            cashReceived.addEventListener('input', updateCashChange);

            checkoutForm.addEventListener('submit', (event) => {
                if (cart.length === 0) {
                    event.preventDefault();
                    return;
                }

                const totalValue = cart.reduce((sum, item) => sum + item.subtotal, 0);
                const cashValue = Number(cashReceived.value || 0);

                if (cashValue < totalValue) {
                    event.preventDefault();
                    cashReceived.classList.add('is-invalid');
                    return;
                }

                checkoutButton.disabled = true;
                checkoutSpinner.classList.remove('d-none');
            });

            renderCart();
            filterProducts();
            focusSearch();
        });
    </script>
</x-app-layout>
