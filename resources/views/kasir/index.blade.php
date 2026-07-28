<x-app-layout>
    <x-slot name="header">
        @include('layouts.partials.page-header', [
            'title' => 'Halaman Kasir',
            'subtitle' => 'Cari produk, atur berat, lalu selesaikan pembayaran tunai.',
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

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-column flex-md-row gap-3 align-items-md-center justify-content-between">
            <div>
                <div class="fw-semibold">Toko aktif</div>
                <div class="text-muted small">{{ $selectedOutlet?->name }}</div>
            </div>

            <div class="badge text-bg-light border text-dark px-3 py-2">
                Semua transaksi memakai toko utama
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label class="form-label" for="searchInput">Cari produk</label>
                    <input
                        type="text"
                        id="searchInput"
                        class="form-control"
                        placeholder="Nama atau kode produk"
                        value="{{ $search }}"
                        autocomplete="off"
                    >
                </div>
                <div class="col-lg-4">
                    <label class="form-label" for="categoryFilter">Filter kategori</label>
                    <select id="categoryFilter" class="form-select">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) $selectedCategoryId === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary flex-grow-1" id="clearSearchButton">
                        Bersihkan
                    </button>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3 align-items-center justify-content-between">
                <div class="text-muted small">
                    Menampilkan <span class="fw-semibold" id="visibleCount">{{ $stocks->count() }}</span> produk
                </div>
                <div class="text-muted small">
                    <i class="bi bi-lightning-charge me-1"></i>Filter berjalan langsung di browser
                </div>
            </div>
        </div>
    </div>

    <div id="noProductsMessage" class="alert alert-info border-0 shadow-sm d-none">
        Produk tidak ditemukan. Coba ubah kata kunci atau kategori.
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="row g-3" id="productGrid">
                @foreach ($stocks as $stock)
                    <div class="col-md-6 product-card" data-product-card data-search="{{ strtolower(trim(($stock->product->name ?? '').' '.($stock->product->code ?? '').' '.($stock->product->category?->name ?? ''))) }}" data-category-id="{{ $stock->product->category_id }}">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between gap-2 mb-2">
                                    <div>
                                        <div class="fw-semibold">{{ $stock->product->name }}</div>
                                        <div class="text-muted small">{{ $stock->product->category?->name }} · {{ $stock->product->unit?->name }}</div>
                                    </div>
                                    <span class="badge {{ $stock->statusBadgeClass() }}">{{ $stock->statusLabel() }}</span>
                                </div>

                                <div class="small text-muted mb-2">Kode: {{ $stock->product->code }}</div>

                                <div class="d-flex justify-content-between small mb-1">
                                    <span>Harga</span>
                                    <span class="fw-semibold">{{ $formatRupiah($stock->product->selling_price) }}/kg</span>
                                </div>
                                <div class="d-flex justify-content-between small mb-3">
                                    <span>Stok tersedia</span>
                                    <span class="fw-semibold">{{ number_format((float) $stock->quantity, 3, ',', '.') }} kg</span>
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
                @endforeach
            </div>
        </div>

        <div class="col-lg-4">
            <form method="POST" action="{{ route('kasir.store') }}" id="checkoutForm" class="card border-0 shadow-sm sticky-lg-top" style="top: 6rem;">
                @csrf
                <input type="hidden" name="payment_method" value="cash">
                <input type="hidden" name="discount" value="0">
                <div class="card-header bg-white border-0 pb-0">
                    <div class="fw-semibold">Keranjang</div>
                    <div class="text-muted small">Kelola item lalu lanjutkan ke checkout tunai.</div>
                </div>

                <div class="card-body">
                    <div id="cartEmpty" class="alert alert-light border text-muted mb-3">
                        Keranjang masih kosong.
                    </div>

                    <div class="table-responsive mb-3">
                        <table class="table align-middle table-sm mb-0">
                            <thead class="table-light">
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

                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <span id="cartSubtotal">Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Diskon</span>
                            <span id="cartDiscount">Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between fw-semibold fs-5">
                            <span>Total</span>
                            <span id="cartTotal">Rp 0</span>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label" for="cash_received">Uang diterima</label>
                        <input type="number" min="0" step="0.01" class="form-control @error('cash_received') is-invalid @enderror" name="cash_received" id="cash_received" placeholder="0">
                        @error('cash_received')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="d-flex justify-content-between mt-3">
                        <span>Kembalian</span>
                        <span id="cashChange" class="fw-semibold text-muted">Rp 0</span>
                    </div>

                    @error('items')<div class="alert alert-danger mt-3 mb-0">{{ $message }}</div>@enderror
                    @error('transaction')<div class="alert alert-warning mt-3 mb-0">{{ $message }}</div>@enderror
                </div>

                <div class="card-footer bg-white border-0 pt-0">
                    <button type="submit" class="btn btn-success w-100" id="checkoutButton" disabled>
                        <span class="spinner-border spinner-border-sm d-none me-2" id="checkoutSpinner" role="status" aria-hidden="true"></span>
                        Simpan Transaksi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0">Tambah Produk</h5>
                        <small class="text-muted" id="productModalSubtitle"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
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
                    <button type="button" class="btn btn-primary" id="saveCartButton">Simpan ke Keranjang</button>
                </div>
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
            let activeProduct = null;

            const findProduct = (stockId) => products.find((item) => Number(item.stock_id) === Number(stockId));

            const renderCart = () => {
                cartRows.innerHTML = '';
                cartHiddenInputs.innerHTML = '';

                let subtotal = 0;

                cart.forEach((item, index) => {
                    subtotal += item.subtotal;

                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>
                            <div class="fw-semibold">${item.name}</div>
                            <div class="text-muted small">${formatWeight(item.weight_grams)} · ${formatRupiah(item.price)}</div>
                        </td>
                        <td class="text-end">${formatRupiah(item.subtotal)}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-secondary js-edit-item" data-index="${index}">Ubah</button>
                                <button type="button" class="btn btn-outline-danger js-remove-item" data-index="${index}">Hapus</button>
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
                    } else {
                        cart.push(item);
                    }
                } else {
                    cart[editingIndex] = item;
                }

                productModal.hide();
                renderCart();
                focusSearch();
            };

            document.querySelectorAll('.js-add-product').forEach((button) => {
                button.addEventListener('click', () => {
                    const productData = JSON.parse(button.dataset.stock);
                    openModalForStock(productData.stock_id);
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
