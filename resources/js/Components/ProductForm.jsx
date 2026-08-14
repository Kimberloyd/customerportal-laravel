export default function ProductForm({ data, setData, errors, categoryOptions, showActiveToggle }) {
    return (
        <>
            {Object.entries(errors).map(([key, message]) => (
                <div key={key} className="rounded-md bg-red-50 p-3 text-sm text-red-700">{message}</div>
            ))}

            <div>
                <label className="block text-sm font-medium text-gray-700">SKU</label>
                <input
                    type="text"
                    value={data.sku}
                    onChange={(e) => setData('sku', e.target.value)}
                    className="mt-1 block w-full rounded-md border-gray-300 text-sm"
                />
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Product Name</label>
                <input
                    type="text"
                    required
                    value={data.product_name}
                    onChange={(e) => setData('product_name', e.target.value)}
                    className="mt-1 block w-full rounded-md border-gray-300 text-sm"
                />
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Category</label>
                <select
                    value={data.category}
                    onChange={(e) => setData('category', e.target.value)}
                    className="mt-1 block w-full rounded-md border-gray-300 text-sm"
                >
                    {categoryOptions.map((option) => (
                        <option key={option} value={option}>
                            {option}
                        </option>
                    ))}
                </select>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Generic Name</label>
                <input
                    type="text"
                    value={data.generic_name}
                    onChange={(e) => setData('generic_name', e.target.value)}
                    className="mt-1 block w-full rounded-md border-gray-300 text-sm"
                />
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Unit</label>
                <input
                    type="text"
                    value={data.unit}
                    onChange={(e) => setData('unit', e.target.value)}
                    className="mt-1 block w-full rounded-md border-gray-300 text-sm"
                />
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Unit Price</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    required
                    value={data.unit_price}
                    onChange={(e) => setData('unit_price', e.target.value)}
                    className="mt-1 block w-full rounded-md border-gray-300 text-sm"
                />
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Description</label>
                <textarea
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    rows={3}
                    className="mt-1 block w-full rounded-md border-gray-300 text-sm"
                />
            </div>

            {showActiveToggle && (
                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                    />
                    Active
                </label>
            )}
        </>
    );
}
