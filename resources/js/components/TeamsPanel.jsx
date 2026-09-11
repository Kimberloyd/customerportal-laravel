import ConfirmationDialog from '@/components/ConfirmationDialog';
import { Dropdown } from '@/components/interior/dropdown';
import { Modal } from '@/components/interior/modal';
import { Checkbox } from '@/components/motion/checkbox';
import { Table } from '@/components/motion/table';
import { Button } from '@/components/ui/button';
import { useFieldValidation } from '@/hooks/useFieldValidation';
import { useForm } from '@inertiajs/react';
import { Check, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

const teamRules = {
    name: (value) => ((value ?? '').trim() ? null : 'Enter a team name.'),
};

export function TeamsPanel({ teams = [], agents = [] }) {
    const [open, setOpen] = useState(false);
    const [editingTeam, setEditingTeam] = useState(null);
    const [teamPendingDeletion, setTeamPendingDeletion] = useState(null);
    const editor = useForm({ name: '', agent_ids: [] });
    const deletion = useForm({});
    const validation = useFieldValidation(teamRules);

    const selectableAgents = useMemo(() => {
        const choices = new Map(agents.map((agent) => [agent.id, agent]));
        editingTeam?.members.forEach((agent) => choices.set(agent.id, agent));

        return [...choices.values()].sort((left, right) => left.full_name.localeCompare(right.full_name));
    }, [editingTeam, agents]);

    const openCreate = () => {
        editor.reset();
        editor.clearErrors();
        validation.reset();
        setEditingTeam(null);
        setOpen(true);
    };

    const openEdit = (team) => {
        editor.setData({
            name: team.name,
            agent_ids: team.members.map((member) => member.id),
        });
        editor.clearErrors();
        validation.reset();
        setEditingTeam(team);
        setOpen(true);
    };

    const close = () => {
        editor.reset();
        editor.clearErrors();
        validation.reset();
        setEditingTeam(null);
        setOpen(false);
    };

    const toggleAgent = (id) => {
        const selected = editor.data.agent_ids.includes(id);
        if (!selected && editor.data.agent_ids.length === 3) return;

        editor.setData(
            'agent_ids',
            selected
                ? editor.data.agent_ids.filter((value) => value !== id)
                : [...editor.data.agent_ids, id],
        );
    };

    const submit = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: close };

        if (editingTeam) {
            editor.put(route('admin.teams.update', editingTeam.public_id), options);
            return;
        }

        editor.post(route('admin.teams.store'), options);
    };

    const confirmDelete = () => {
        if (!teamPendingDeletion) return;

        deletion.delete(route('admin.teams.destroy', teamPendingDeletion.public_id), {
            preserveScroll: true,
            onSuccess: () => setTeamPendingDeletion(null),
        });
    };

    const columns = [
        { key: 'name', header: 'Team', sortable: true },
        {
            key: 'members',
            header: 'Agents',
            cell: (team) => team.members.map((member) => member.full_name).join(', '),
        },
        {
            key: 'member_count',
            header: 'Members',
            cell: (team) => `${team.members.length} of 3`,
        },
        {
            key: 'actions',
            header: '',
            width: '56px',
            cell: (team) => {
                const items = [
                    {
                        value: 'edit',
                        label: 'Edit',
                        icon: <Pencil />,
                        onSelect: () => openEdit(team),
                    },
                    {
                        value: 'delete',
                        label: 'Delete',
                        icon: <Trash2 />,
                        onSelect: () => setTeamPendingDeletion(team),
                        destructive: true,
                    },
                ];

                return (
                    <div className="flex items-center">
                        <Dropdown
                            items={items}
                            value=""
                            onChange={(action) => items.find((item) => item.value === action)?.onSelect()}
                            label={`Actions for ${team.name}`}
                            trigger={<MoreHorizontal />}
                            align="right"
                            portal
                            triggerClassName="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring [&_svg]:h-5 [&_svg]:w-5"
                        />
                    </div>
                );
            },
        },
    ];

    return (
        <div>
            <div className="mb-6 flex items-start justify-between gap-4">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900">Teams</h3>
                    <p className="mt-1 text-sm text-gray-600">
                        Organize active agents into teams of up to 3 members.
                    </p>
                </div>
                <Button type="button" onClick={openCreate}>Add team</Button>
            </div>

            <Table
                data={teams}
                columns={columns}
                getRowId={(team) => String(team.id)}
                height={480}
                emptyState="No teams have been created yet. Add a team to get started."
                emptyStateHeight={240}
            />

            <Modal
                open={open}
                onClose={close}
                title={editingTeam ? 'Edit team' : 'Add team'}
                description={editingTeam ? 'Update the team name or assigned agents.' : 'Choose up to 3 active agents for this team.'}
                maxWidth={600}
                closeOnBackdrop={!editor.processing}
                closeOnEscape={!editor.processing}
                footer={
                    <>
                        <Button
                            type="button"
                            variant="tertiary"
                            className="h-10 rounded-md px-5 text-sm"
                            onClick={close}
                            disabled={editor.processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            form="team-form"
                            variant="primary"
                            className="h-10 rounded-md px-5 text-sm"
                            loading={editor.processing}
                        >
                            {editingTeam ? 'Save changes' : 'Create team'}
                        </Button>
                    </>
                }
            >
                <form id="team-form" onSubmit={submit}>
                    <label className="block text-sm font-medium text-gray-700">
                        Team name
                        <div className="relative">
                            <input
                                value={editor.data.name}
                                onChange={(event) => {
                                    editor.setData('name', event.target.value);
                                    editor.clearErrors('name');
                                    validation.onChange('name', event.target.value, editor.data);
                                }}
                                onBlur={() => validation.onBlur('name', editor.data.name, editor.data)}
                                className={`mt-1 h-10 w-full rounded-md border px-3 text-sm outline-none focus-visible:ring-2 ${
                                    editor.errors.name || validation.clientErrors.name
                                        ? 'border-red-300 focus-visible:border-red-400 focus-visible:ring-red-200'
                                        : 'border-gray-300 focus-visible:border-primary focus-visible:ring-primary/20'
                                }`}
                                required
                                autoComplete="off"
                            />
                            {validation.validFields.name && !editor.errors.name && (
                                <Check aria-hidden="true" className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-emerald-600" />
                            )}
                        </div>
                    </label>
                    {(editor.errors.name || validation.clientErrors.name) && (
                        <p className="mt-1 text-sm text-red-600" role="alert">{editor.errors.name || validation.clientErrors.name}</p>
                    )}

                    <fieldset className="mt-5">
                        <legend className="text-sm font-medium text-gray-700">
                            Agents ({editor.data.agent_ids.length}/3)
                        </legend>
                        <div className="mt-2 grid gap-2 sm:grid-cols-2">
                            {selectableAgents.map((agent) => (
                                <div key={agent.id} className="flex items-center gap-2 rounded-md p-2 text-sm">
                                    <Checkbox
                                        checked={editor.data.agent_ids.includes(agent.id)}
                                        onCheckedChange={() => toggleAgent(agent.id)}
                                        disabled={!editor.data.agent_ids.includes(agent.id) && editor.data.agent_ids.length === 3}
                                        aria-label={`Select ${agent.full_name}`}
                                    />
                                    <span>{agent.full_name}</span>
                                </div>
                            ))}
                            {selectableAgents.length === 0 && (
                                <p className="text-sm text-gray-500 sm:col-span-2">
                                    All active agents already belong to a team.
                                </p>
                            )}
                        </div>
                    </fieldset>
                    {editor.errors.agent_ids && <p className="mt-2 text-sm text-red-600" role="alert">{editor.errors.agent_ids}</p>}
                </form>
            </Modal>

            <ConfirmationDialog
                open={teamPendingDeletion !== null}
                onOpenChange={(nextOpen) => !nextOpen && setTeamPendingDeletion(null)}
                title={`Delete ${teamPendingDeletion?.name ?? 'team'}?`}
                description="This removes the team. Its agents will become available for another team."
                confirmLabel="Delete team"
                cancelLabel="Keep team"
                onConfirm={confirmDelete}
                destructive
                processing={deletion.processing}
            />
        </div>
    );
}
