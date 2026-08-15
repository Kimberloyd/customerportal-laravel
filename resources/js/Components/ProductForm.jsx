import { Input } from '@/components/motion/input';

export default function ProductForm({ data, setData, errors, categoryOptions, showActiveToggle }) {
    return (
        <>
            {Object.entries(errors).map(([key, message]) => (
                <div key={key} className="rounded-md bg-red-50 p-3 text-sm text-red-700">{message}</div>
            ))}

            <div>
                <Input
                    label="SKU"
                    type="text"
                    value={data.sku}
                    onChange={(value) => setData('sku', value)}
                />
            </div>

            <div>
                <Input
                    label="Product Name"
                    type="text"
                    required
                    value={data.product_name}
                    onChange={(value) => setData('product_name', value)}
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
                <Input
                    label="Generic Name"
                    type="text"
                    value={data.generic_name}
                    onChange={(value) => setData('generic_name', value)}
                />
            </div>

            <div>
                <Input
                    label="Unit"
                    type="text"
                    value={data.unit}
                    onChange={(value) => setData('unit', value)}
                />
            </div>

            <div>
                <Input
                    label="Unit Price"
                    type="number"
                    step="0.01"
                    min="0"
                    required
                    value={data.unit_price}
                    onChange={(value) => setData('unit_price', value)}
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
