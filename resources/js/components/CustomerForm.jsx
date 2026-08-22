import { Input } from '@/components/motion/input';

export default function CustomerForm({ data, setData, errors, channelOptions, customerCode }) {
    return (
        <>
            {Object.entries(errors).map(([key, message]) => (
                <div key={key} className="rounded-md bg-red-50 p-3 text-sm text-red-700">{message}</div>
            ))}

            {customerCode && (
                <div>
                    <label className="block text-sm font-medium text-gray-700">Customer Code</label>
                    <p className="mt-1 text-sm text-gray-500">{customerCode}</p>
                </div>
            )}

            <div>
                <Input
                    label="Company Name"
                    type="text"
                    required
                    value={data.company_name}
                    onChange={(value) => setData('company_name', value)}
                />
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Channel</label>
                <select
                    value={data.channel}
                    onChange={(e) => setData('channel', e.target.value)}
                    className="mt-1 block w-full rounded-md border-gray-300 text-sm"
                >
                    {channelOptions.map((option) => (
                        <option key={option} value={option}>
                            {option}
                        </option>
                    ))}
                </select>
            </div>

            <div>
                <Input
                    label="Contact Person"
                    type="text"
                    value={data.contact_person}
                    onChange={(value) => setData('contact_person', value)}
                />
            </div>

            <div>
                <Input
                    label="Email"
                    type="email"
                    value={data.email}
                    onChange={(value) => setData('email', value)}
                />
            </div>

            <div>
                <Input
                    label="Phone"
                    type="text"
                    value={data.phone}
                    onChange={(value) => setData('phone', value)}
                />
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Address</label>
                <textarea
                    value={data.address}
                    onChange={(e) => setData('address', e.target.value)}
                    rows={3}
                    className="mt-1 block w-full rounded-md border-gray-300 text-sm"
                />
            </div>

            <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                    type="checkbox"
                    checked={data.is_active}
                    onChange={(e) => setData('is_active', e.target.checked)}
                />
                Active
            </label>
        </>
    );
}
