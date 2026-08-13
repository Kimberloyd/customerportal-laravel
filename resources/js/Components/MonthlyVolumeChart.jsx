export default function MonthlyVolumeChart({ months, periodLabel }) {
    return (
        <div
            className="flex items-end gap-2 overflow-x-auto pb-1"
            role="img"
            aria-label={`Order volume for ${periodLabel}`}
        >
            {months.map((month) => (
                <div
                    key={month.full_label}
                    title={`${month.full_label}: ${month.count} ${
                        month.count === 1 ? 'order' : 'orders'
                    }, ${month.units} units sold`}
                    className="flex min-w-[2.5rem] flex-1 flex-col items-center gap-1"
                >
                    <div className="flex flex-col items-center text-xs leading-tight">
                        <span className="font-semibold text-gray-900">
                            {month.count}
                        </span>
                        <span className="text-gray-500">{month.units}u</span>
                    </div>
                    <div className="flex h-24 w-full items-end rounded bg-gray-100">
                        <span
                            className="w-full rounded bg-indigo-500"
                            style={{ height: `${month.height}%` }}
                        />
                    </div>
                    <span className="text-xs text-gray-600">{month.label}</span>
                </div>
            ))}
        </div>
    );
}
