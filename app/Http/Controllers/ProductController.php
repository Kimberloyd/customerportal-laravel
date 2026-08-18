<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\ProductAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ports app/products/product_routes.py. One deliberate addition beyond
 * Flask (approved for this phase): a deactivate/reactivate toggle --
 * Flask's only removal path is hard delete, which is blocked whenever
 * a product has any order history, so most real products could never
 * be hidden or removed once used.
 */
class ProductController extends Controller
{
    private const CATEGORY_OPTIONS = ['OPEN', 'EXCLUSIVE', 'OTHERS'];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $source = strtolower(trim((string) $request->query('source', 'all'))) ?: 'all';
        $status = strtolower(trim((string) $request->query('status', 'active'))) ?: 'active';
        $sortBy = strtolower(trim((string) $request->query('sort_by', 'brand'))) ?: 'brand';
        $sortDir = strtolower(trim((string) $request->query('sort_dir', 'asc'))) ?: 'asc';

        $sortColumns = [
            'generic' => 'generic_name',
            'brand' => 'product_name',
            'category' => 'category',
            'unit' => 'unit',
            'description' => 'description',
        ];
        if (! array_key_exists($sortBy, $sortColumns)) {
            $sortBy = 'brand';
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }
        if (! in_array($source, ['all', 'generic', 'standard'], true)) {
            $source = 'all';
        }
        if (! in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $query = Product::query();

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($source === 'generic') {
            $query->where('sku', 'like', 'GEN-%');
        } elseif ($source === 'standard') {
            $query->where(function ($q) {
                $q->whereNull('sku')->orWhere('sku', 'not like', 'GEN-%');
            });
        }

        if ($search !== '') {
            $pattern = '%'.$search.'%';
            $query->where(function ($q) use ($pattern) {
                $q->where('sku', 'like', $pattern)
                    ->orWhere('product_name', 'like', $pattern)
                    ->orWhere('category', 'like', $pattern)
                    ->orWhere('generic_name', 'like', $pattern)
                    ->orWhere('unit', 'like', $pattern)
                    ->orWhere('description', 'like', $pattern);
            });
        }

        $column = $sortColumns[$sortBy];
        $query->orderByRaw("LOWER(COALESCE({$column}, '')) {$sortDir}")
            ->orderBy('id', $sortDir);

        $products = $query->get();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'source' => $source,
                'status' => $status,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
            'canManage' => Auth::user()->role !== 'customer',
        ]);
    }

    public function create(): Response
    {
        abort_if(Auth::user()->role === 'customer', 403);

        return Inertia::render('Products/Create', [
            'categoryOptions' => self::CATEGORY_OPTIONS,
        ]);
    }

    public function store(Request $request)
    {
        abort_if(Auth::user()->role === 'customer', 403);

        $attributes = $this->validateProductForm($request);

        $product = DB::transaction(function () use ($attributes, $request) {
            $product = Product::create($attributes);
            ProductAudit::record($product, 'created', $request);

            return $product;
        });

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function edit(Product $product): Response
    {
        abort_if(Auth::user()->role === 'customer', 403);

        return Inertia::render('Products/Edit', [
            'product' => $product,
            'categoryOptions' => self::CATEGORY_OPTIONS,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        abort_if(Auth::user()->role === 'customer', 403);

        $attributes = $this->validateProductForm($request, $product);

        DB::transaction(function () use ($product, $attributes, $request) {
            $product->update($attributes);
            ProductAudit::record($product, 'updated', $request);
        });

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Request $request, Product $product)
    {
        abort_if(Auth::user()->role === 'customer', 403);

        if ($product->orderItems()->exists()) {
            return back()->with('error', 'Products with orders cannot be deleted.');
        }

        DB::transaction(function () use ($product, $request) {
            ProductAudit::record($product, 'deleted', $request);
            $product->delete();
        });

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }

    public function toggleActive(Request $request, Product $product)
    {
        abort_if(Auth::user()->role === 'customer', 403);

        DB::transaction(function () use ($product, $request) {
            $product->is_active = ! $product->is_active;
            $product->save();
            ProductAudit::record($product, $product->is_active ? 'reactivated' : 'deactivated', $request);
        });

        return back()->with('success', $product->is_active ? 'Product reactivated.' : 'Product deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProductForm(Request $request, ?Product $existing = null): array
    {
        $skuRule = 'nullable|string|max:100|unique:products,sku'.($existing ? ",{$existing->id}" : '');

        $validated = $request->validate([
            'sku' => $skuRule,
            'product_name' => 'required|string|max:200',
            'category' => 'nullable|string',
            'generic_name' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'unit' => 'nullable|string|max:50',
            'unit_price' => 'required|numeric|min:0|lt:10000000000',
            'is_active' => 'nullable|boolean',
        ]);

        $sku = $this->normalizeOptionalText($validated['sku'] ?? null);
        $productName = trim($validated['product_name']);
        $genericName = $this->normalizeOptionalText($validated['generic_name'] ?? null);
        $category = $this->normalizeCategory($validated['category'] ?? null);

        if ($sku && str_starts_with($sku, 'GEN-')) {
            $conflict = Product::query()
                ->where('sku', 'like', 'GEN-%')
                ->whereRaw('UPPER(TRIM(COALESCE(product_name, \'\'))) = ?', [strtoupper(trim($productName))])
                ->whereRaw('UPPER(TRIM(COALESCE(generic_name, \'\'))) = ?', [strtoupper(trim((string) $genericName))])
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'sku' => 'A generic alias with the same normalized brand and generic name already exists.',
                ]);
            }
        }

        return [
            'sku' => $sku,
            'product_name' => $productName,
            'category' => $category,
            'generic_name' => $genericName,
            'description' => $this->normalizeOptionalText($validated['description'] ?? null),
            'unit' => $this->normalizeOptionalText($validated['unit'] ?? null),
            'unit_price' => $validated['unit_price'],
            'is_active' => $request->boolean('is_active', $existing?->is_active ?? true),
        ];
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || strtolower($value) === 'none') {
            return null;
        }

        return $value;
    }

    private function normalizeCategory(?string $value): string
    {
        $category = strtoupper(trim((string) $value));

        return in_array($category, self::CATEGORY_OPTIONS, true) ? $category : 'OTHERS';
    }
}
