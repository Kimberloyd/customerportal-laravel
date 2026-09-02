import { Modal } from '@/components/interior/modal';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';

export function TeamsPanel({ teams = [], employees = [] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ name: '', employee_ids: [] });
    const close = () => {
        if (processing) return;
        reset();
        clearErrors();
        setOpen(false);
    };
    const toggleEmployee = (id) => {
        const selected = data.employee_ids.includes(id);
        if (!selected && data.employee_ids.length === 3) return;
        setData('employee_ids', selected ? data.employee_ids.filter((value) => value !== id) : [...data.employee_ids, id]);
    };
    const submit = (event) => {
        event.preventDefault();
        post(route('admin.teams.store'), { preserveScroll: true, onSuccess: close });
    };
    const columns = useMemo(() => [
        { key: 'name', header: 'Team', sortable: true },
        { key: 'members', header: 'Employees', cell: (team) => team.members.map((member) => member.full_name).join(', ') },
        { key: 'member_count', header: 'Members', cell: (team) => `${team.members.length} of 3` },
    ], []);

    return <div>
        <div className="mb-6 flex items-start justify-between gap-4"><div><h3 className="text-lg font-semibold text-gray-900">Teams</h3><p className="mt-1 text-sm text-gray-600">Organize active employees into teams of up to 3 members.</p></div><Button type="button" onClick={() => setOpen(true)}><Plus aria-hidden="true" className="mr-2 h-4 w-4" />Add team</Button></div>
        <Table data={teams} columns={columns} getRowId={(team) => String(team.id)} height={480} emptyState="No teams have been created yet. Add a team to get started." emptyStateHeight={240} />

        <Modal open={open} onClose={close} title="Add team" description="Choose up to 3 active employees for this team." maxWidth={600} closeOnBackdrop={!processing} closeOnEscape={!processing} footer={<><Button type="button" variant="tertiary" onClick={close} disabled={processing}>Cancel</Button><Button type="submit" form="team-form" loading={processing}>Create team</Button></>}>
            <form id="team-form" onSubmit={submit}>
                <label className="block text-sm font-medium text-gray-700">Team name
                    <input value={data.name} onChange={(event) => setData('name', event.target.value)} className="mt-1 h-10 w-full rounded-md border border-gray-300 px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" required autoComplete="off" />
                </label>
                {errors.name && <p className="mt-1 text-sm text-red-600" role="alert">{errors.name}</p>}
                <fieldset className="mt-5"><legend className="text-sm font-medium text-gray-700">Employees ({data.employee_ids.length}/3)</legend>
                    <div className="mt-2 grid gap-2 sm:grid-cols-2">
                        {employees.map((employee) => <label key={employee.id} className="flex items-center gap-2 rounded-md border border-gray-200 p-2 text-sm">
                            <input type="checkbox" checked={data.employee_ids.includes(employee.id)} onChange={() => toggleEmployee(employee.id)} disabled={!data.employee_ids.includes(employee.id) && data.employee_ids.length === 3} />
                            <span>{employee.full_name}<span className="block text-xs text-gray-500">{employee.email}</span></span>
                        </label>)}
                    </div>
                </fieldset>
                {errors.employee_ids && <p className="mt-2 text-sm text-red-600" role="alert">{errors.employee_ids}</p>}
            </form>
        </Modal>
    </div>;
}
