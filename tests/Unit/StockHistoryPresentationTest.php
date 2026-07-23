<?php

namespace Tests\Unit;

use App\Models\StockHistory;
use Tests\TestCase;

class StockHistoryPresentationTest extends TestCase
{
    public function test_known_types_have_expected_labels_badges_and_icons(): void
    {
        $cases = [
            'restock' => ['Stok Masuk', 'bg-success', 'bi-box-arrow-in-down'],
            'adjustment_in' => ['Koreksi Tambah', 'bg-primary', 'bi-plus-circle'],
            'adjustment_out' => ['Koreksi Kurang', 'bg-warning text-dark', 'bi-dash-circle'],
            'damaged' => ['Buah Rusak', 'bg-danger', 'bi-exclamation-triangle'],
            'processed' => ['Buah Diproses', 'bg-purple', 'bi-scissors'],
            'return_in' => ['Retur Masuk', 'bg-info text-dark', 'bi-arrow-return-left'],
            'return_out' => ['Retur Keluar', 'bg-secondary', 'bi-arrow-return-right'],
            'initial_stock' => ['Stok Awal', 'bg-dark', 'bi-box-seam'],
            'sale' => ['Penjualan', 'bg-success', 'bi-cart-check'],
            'stock_opname' => ['Stock Opname', 'bg-primary', 'bi-clipboard-check'],
            'transfer_in' => ['Transfer Masuk', 'bg-info text-dark', 'bi-arrow-down-left-circle'],
            'transfer_out' => ['Transfer Keluar', 'bg-secondary', 'bi-arrow-up-right-circle'],
        ];

        foreach ($cases as $type => [$label, $badgeClass, $icon]) {
            $history = new StockHistory([
                'type' => $type,
                'quantity_change' => 1,
            ]);

            $this->assertSame($label, $history->type_label);
            $this->assertSame($badgeClass, $history->type_badge_class);
            $this->assertSame($icon, $history->type_icon);
        }
    }

    public function test_quantity_change_formats_positive_negative_and_zero_values(): void
    {
        $positive = new StockHistory(['quantity_change' => 10]);
        $negative = new StockHistory(['quantity_change' => -2]);
        $zero = new StockHistory(['quantity_change' => 0]);

        $this->assertSame('+10,000', $positive->formatted_quantity_change);
        $this->assertSame('text-success', $positive->quantity_change_class);

        $this->assertSame('-2,000', $negative->formatted_quantity_change);
        $this->assertSame('text-danger', $negative->quantity_change_class);

        $this->assertSame('0,000', $zero->formatted_quantity_change);
        $this->assertSame('text-muted', $zero->quantity_change_class);
    }

    public function test_unknown_type_uses_safe_fallback(): void
    {
        $history = new StockHistory([
            'type' => 'unknown_type',
            'quantity_change' => 5,
        ]);

        $this->assertSame('Jenis Tidak Dikenal', $history->type_label);
        $this->assertSame('bg-secondary', $history->type_badge_class);
        $this->assertSame('bi-question-circle', $history->type_icon);
    }
}
