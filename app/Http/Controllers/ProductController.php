<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class ProductController extends Controller
{
    public function __construct(private readonly StockService $stockService)
    {
    }

    public function index(Request $request): View
    {
        $products = Product::with(['category', 'unit'])
            ->when($request->string('search')->toString(), function ($query, $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category_id'), function ($query) use ($request): void {
                $query->where('category_id', $request->integer('category_id'));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $categories = Category::orderBy('name')->get();

        return view('products.index', compact('products', 'categories'));
    }

    public function create(): View
    {
        return view('products.create', [
            'categories' => Category::orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $imagePath = null;

        try {
            $imagePath = $request->file('image')?->store('products', 'public');

            DB::transaction(function () use ($request, $imagePath): void {
                $product = Product::create([
                    'category_id' => $request->integer('category_id'),
                    'unit_id' => $request->integer('unit_id'),
                    'code' => $request->code,
                    'name' => $request->name,
                    'description' => $request->description,
                    'image' => $imagePath,
                    'purchase_price' => $request->purchase_price,
                    'selling_price' => $request->selling_price,
                    'is_active' => $request->boolean('is_active', true),
                ]);

                $this->stockService->initializeProductStocks($product);
            });

            return redirect()->route('products.index')->with('success', 'Produk berhasil ditambahkan.');
        } catch (Throwable $throwable) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $throwable;
        }
    }

    public function edit(Product $product): View
    {
        return view('products.edit', [
            'product' => $product,
            'categories' => Category::orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $imagePath = $product->image;

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }

            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product->update([
            'category_id' => $request->integer('category_id'),
            'unit_id' => $request->integer('unit_id'),
            'code' => $request->code,
            'name' => $request->name,
            'description' => $request->description,
            'image' => $imagePath,
            'purchase_price' => $request->purchase_price,
            'selling_price' => $request->selling_price,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('products.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return redirect()->route('products.index')->with('success', 'Produk berhasil dihapus.');
    }
}
