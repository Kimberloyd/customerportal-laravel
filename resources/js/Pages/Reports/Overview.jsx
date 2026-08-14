import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const RANGE_OPTIONS = [
    { value: '3', label: '3 Months' },
    { value: '6', label: '6 Months' },
    { value: '12', label: '12 Months' },
    { value: 'custom', label: 'Custom Range' },
];

function Tile({ label, value }) {
    return (
        <div className="rounded-lg bg-white p-4 shadow-sm">
            <h3 className="text-sm font-medium text-gray-500">{label}</h3>
            <p className="mt-1 text-2xl font-semibold text-gray-900">{value}</p>
        </div>
    );
}

function ProgressBar({ percent, className = 'bg-indigo-500' }) {
    return (
        <div className="h-2 w-full rounded-full bg-gray-100">
            <div className={`h-2 rounded-full ${className}`} style={{ width: `${percent}%` }} />
        </div>
    );
}

export default function Overview({ filters, customers, isCustomerView, metrics, monthlyTrend, statusMix, agingRows, productPerformance, customerPerformance }) {
    const [range, setRange] = useState(filters.range);
    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);
    const [customerId, setCustomerId] = useState(filters.customer_id ?? '');

    const applyFilters = (e) => {
        e.preventDefault();
        router.get(
            route('reports.overview'),
            { range, start_date: startDate, end_date: endDate, customer_id: customerId },
            { preserveState: true },
        );
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Analytics Overview</h2>}
        >
            <Head title="Analytics Overview" />

            <div className="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <form onSubmit={applyFilters} className="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
                    <label className="flex flex-col text-sm text-gray-600">
                        Period
                        <select value={range} onChange={(e) => setRange(e.target.value)} className="mt-1 rounded-md border-gray-300 text-sm">
                            {RANGE_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                    </label>
                    {range === 'custom' && (
                        <>
                            <label className="flex flex-col text-sm text-gray-600">
                                From
                                <input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} className="mt-1 rounded-md border-gray-300 text-sm" />
                            </label>
                            <label className="flex flex-col text-sm text-gray-600">
                                To
                                <input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} className="mt-1 rounded-md border-gray-300 text-sm" />
                            </label>
                        </>
                    )}
                    {!isCustomerView && (
                        <label className="flex flex-col text-sm text-gray-600">
                            Customer
                            <select value={customerId} onChange={(e) => setCustomerId(e.target.value)} className="mt-1 rounded-md border-gray-300 text-sm">
                                <option value="">All Customers</option>
                                {customers.map((c) => (
                                    <option key={c.id} value={c.id}>{c.company_name}</option>
                                ))}
                            </select>
                        </label>
                    )}
                    <button type="submit" className="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">
                        Apply
                    </button>
                </form>

                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <Tile label="Unit Fulfillment" value={`${metrics.fulfillment_rate}%`} />
                    <Tile label="Order Completion" value={`${metrics.completion_rate}%`} />
                    <Tile label="Backlog Units" value={metrics.backlog_units} />
                    <Tile label="Avg Completion Time" value={`${metrics.average_completion_days}d`} />
                </div>

                <section className="rounded-lg bg-white p-4 shadow-sm">
                    <h3 className="mb-3 text-lg font-semibold text-gray-900">Ordered vs Delivered</h3>
                    <div className="flex items-end gap-3 overflow-x-auto pb-1">
                        {monthlyTrend.map((month) => (
                            <div key={month.full_label} className="flex min-w-[3rem] flex-1 flex-col items-center gap-1">
                                <div className="flex h-24 w-full items-end gap-1">
                                    <span className="w-1/2 rounded bg-indigo-500" style={{ height: `${month.ordered_height}%` }} title={`Ordered: ${month.ordered}`} />
                                    <span className="w-1/2 rounded bg-green-500" style={{ height: `${month.delivered_height}%` }} title={`Delivered: ${month.delivered}`} />
                                </div>
                                <span className="text-xs text-gray-600">{month.label}</span>
                            </div>
                        ))}
                    </div>
                </section>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <section className="rounded-lg bg-white p-4 shadow-sm">
                        <h3 className="mb-3 text-lg font-semibold text-gray-900">Order Status Mix</h3>
                        <div className="space-y-2">
                            {statusMix.map((row) => (
                                <div key={row.key}>
                                    <div className="flex justify-between text-sm text-gray-600">
                                        <span>{row.label}</span>
                                        <span>{row.count} ({row.percent}%)</span>
                                    </div>
                                    <ProgressBar percent={row.percent} />
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="rounded-lg bg-white p-4 shadow-sm">
                        <h3 className="mb-3 text-lg font-semibold text-gray-900">Backlog Aging</h3>
                        <div className="space-y-2">
                            {agingRows.map((row) => (
                                <div key={row.label}>
                                    <div className="flex justify-between text-sm text-gray-600">
                                        <span>{row.label}</span>
                                        <span>{row.orders} orders · {row.units} units</span>
                                    </div>
                                    <ProgressBar percent={row.percent} className="bg-amber-500" />
                                </div>
                            ))}
                        </div>
                    </section>
                </div>

                <section className="rounded-lg bg-white p-4 shadow-sm">
                    <h3 className="mb-3 text-lg font-semibold text-gray-900">Product Fulfillment Gaps</h3>
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr className="text-left text-gray-500">
                                <th className="py-2 pr-4">Generic Name</th>
                                <th className="py-2 pr-4">Brand Name</th>
                                <th className="py-2 pr-4">Ordered</th>
                                <th className="py-2 pr-4">Delivered</th>
                                <th className="py-2 pr-4">Backlog</th>
                                <th className="py-2 pr-4">Fulfillment %</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {productPerformance.length === 0 && (
                                <tr><td colSpan={6} className="py-4 text-center text-gray-400">No data for this period.</td></tr>
                            )}
                            {productPerformance.map((p, i) => (
                                <tr key={i}>
                                    <td className="py-2 pr-4">{p.generic_name}</td>
                                    <td className="py-2 pr-4">{p.product_name}</td>
                                    <td className="py-2 pr-4">{p.ordered}</td>
                                    <td className="py-2 pr-4">{p.delivered}</td>
                                    <td className="py-2 pr-4">{p.backlog}</td>
                                    <td className="py-2 pr-4">{p.rate}%</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>

                {!isCustomerView && (
                    <section className="rounded-lg bg-white p-4 shadow-sm">
                        <h3 className="mb-3 text-lg font-semibold text-gray-900">Customer Performance</h3>
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr className="text-left text-gray-500">
                                    <th className="py-2 pr-4">Customer</th>
                                    <th className="py-2 pr-4">Orders</th>
                                    <th className="py-2 pr-4">Ordered</th>
                                    <th className="py-2 pr-4">Delivered</th>
                                    <th className="py-2 pr-4">Backlog</th>
                                    <th className="py-2 pr-4">Completion %</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {customerPerformance.length === 0 && (
                                    <tr><td colSpan={6} className="py-4 text-center text-gray-400">No data for this period.</td></tr>
                                )}
                                {customerPerformance.map((c, i) => (
                                    <tr key={i}>
                                        <td className="py-2 pr-4">{c.name}</td>
                                        <td className="py-2 pr-4">{c.orders}</td>
                                        <td className="py-2 pr-4">{c.ordered}</td>
                                        <td className="py-2 pr-4">{c.delivered}</td>
                                        <td className="py-2 pr-4">{c.backlog}</td>
                                        <td className="py-2 pr-4">{c.completion_rate}%</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
