import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';

export function TeamsPanel({ teams = [], employees = [] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', employee_ids: [] });
    const toggleEmployee = (id) => {
        const selected = data.employee_ids.includes(id);
        if (!selected && data.employee_ids.length === 3) return;
        setData('employee_ids', selected ? data.employee_ids.filter((value) => value !== id) : [...data.employee_ids, id]);
    };
    const submit = (event) => {
        event.preventDefault();
        post(route('admin.teams.store'), { preserveScroll: true, onSuccess: () => reset() });
    };

    return <div className="space-y-6">
        <form onSubmit={submit} className="rounded-xl border border-gray-200 p-5">
            <h3 className="text-base font-semibold text-gray-900">Create team</h3>
            <p className="mt-1 text-sm text-gray-500">A team can have up to 3 active employees.</p>
            <label className="mt-4 block text-sm font-medium text-gray-700">Team name
                <input value={data.name} onChange={(event) => setData('name', event.target.value)} className="mt-1 h-10 w-full rounded-md border border-gray-300 px-3" />
            </label>
            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
            <fieldset className="mt-4"><legend className="text-sm font-medium text-gray-700">Employees ({data.employee_ids.length}/3)</legend>
                <div className="mt-2 grid gap-2 sm:grid-cols-2">
                    {employees.map((employee) => <label key={employee.id} className="flex items-center gap-2 rounded-md border border-gray-200 p-2 text-sm">
                        <input type="checkbox" checked={data.employee_ids.includes(employee.id)} onChange={() => toggleEmployee(employee.id)} />
                        <span>{employee.full_name}<span className="block text-xs text-gray-500">{employee.email}</span></span>
                    </label>)}
                </div>
            </fieldset>
            {errors.employee_ids && <p className="mt-2 text-sm text-red-600">{errors.employee_ids}</p>}
            <Button type="submit" className="mt-5" loading={processing}>Create team</Button>
        </form>
        <div className="rounded-xl border border-gray-200">
            <div className="border-b border-gray-200 px-5 py-4"><h3 className="font-semibold text-gray-900">Teams</h3></div>
            {teams.length === 0 ? <p className="p-5 text-sm text-gray-500">No teams have been created yet.</p> : <ul className="divide-y divide-gray-100">{teams.map((team) => <li key={team.id} className="px-5 py-4"><p className="font-medium text-gray-900">{team.name}</p><p className="mt-1 text-sm text-gray-500">{team.members.map((member) => member.full_name).join(', ')}</p></li>)}</ul>}
        </div>
    </div>;
}
