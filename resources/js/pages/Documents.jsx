import { useEffect, useState } from 'react';
import { api, downloadFile } from '../api/client';
import { useAuth } from '../context/AuthContext';

const DOC_TYPES = [
    { value: 'carte_grise', label: 'Carte grise' },
    { value: 'assurance', label: 'Assurance' },
    { value: 'facture', label: 'Facture' },
    { value: 'controle_technique', label: 'Contrôle technique' },
    { value: 'certificat', label: 'Certificat' },
    { value: 'autre', label: 'Autre' },
];

const emptyForm = {
    vehicle_id: '',
    type: 'assurance',
    title: '',
    expires_at: '',
    ipfs_cid: '',
    is_public: false,
    file: null,
};

export default function Documents() {
    const { can } = useAuth();
    const [items, setItems] = useState([]);
    const [vehicles, setVehicles] = useState([]);
    const [form, setForm] = useState(emptyForm);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [busy, setBusy] = useState(false);

    async function load() {
        const [docs, v] = await Promise.all([api('/documents'), api('/vehicles')]);
        setItems(docs.data || []);
        setVehicles(v.data || []);
    }

    useEffect(() => {
        load().catch((err) => setError(err.message));
    }, []);

    async function onUpload(e) {
        e.preventDefault();
        if (!form.file) {
            setError('Sélectionnez un fichier.');
            return;
        }

        setBusy(true);
        setError('');
        setMessage('');

        try {
            const body = new FormData();
            body.append('vehicle_id', form.vehicle_id);
            body.append('type', form.type);
            body.append('title', form.title || form.file.name);
            body.append('file', form.file);
            body.append('is_public', form.is_public ? '1' : '0');
            if (form.expires_at) body.append('expires_at', form.expires_at);
            if (form.ipfs_cid) body.append('ipfs_cid', form.ipfs_cid);

            await api('/documents', { method: 'POST', body });
            setForm(emptyForm);
            setMessage('Document stocké avec hash SHA-256.');
            await load();
        } catch (err) {
            setError(err.message);
        } finally {
            setBusy(false);
        }
    }

    async function verify(id) {
        try {
            const res = await api(`/documents/${id}/verify`);
            setMessage(res.message);
        } catch (err) {
            setError(err.message);
        }
    }

    async function download(doc) {
        try {
            await downloadFile(`/documents/${doc.id}/download`, doc.original_name || 'document');
        } catch (err) {
            setError(err.message);
        }
    }

    async function remove(id) {
        if (!confirm('Supprimer ce document ?')) return;
        try {
            await api(`/documents/${id}`, { method: 'DELETE' });
            setMessage('Document supprimé.');
            await load();
        } catch (err) {
            setError(err.message);
        }
    }

    return (
        <div className="ac-fade-up space-y-8">
            <div>
                <h1 className="font-display text-3xl font-bold">Gestion documentaire</h1>
                <p className="mt-1 text-[var(--color-ink-soft)]">
                    Cartes grises, assurances et factures — stockage sécurisé + hash d&apos;intégrité.
                </p>
            </div>

            {can('gestionnaire', 'super_admin') && (
                <form onSubmit={onUpload} className="ac-panel grid gap-3 rounded-lg p-5 md:grid-cols-3">
                    <h2 className="font-display text-lg font-bold md:col-span-3">Déposer un document</h2>

                    <label className="text-sm font-medium">
                        Véhicule
                        <select
                            className="ac-input mt-1"
                            value={form.vehicle_id}
                            onChange={(e) => setForm((f) => ({ ...f, vehicle_id: e.target.value }))}
                            required
                        >
                            <option value="">Choisir…</option>
                            {vehicles.map((v) => (
                                <option key={v.id} value={v.id}>
                                    {v.registration_number} — {v.brand} {v.model}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="text-sm font-medium">
                        Type
                        <select
                            className="ac-input mt-1"
                            value={form.type}
                            onChange={(e) => setForm((f) => ({ ...f, type: e.target.value }))}
                        >
                            {DOC_TYPES.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="text-sm font-medium">
                        Titre
                        <input
                            className="ac-input mt-1"
                            value={form.title}
                            onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
                            placeholder="Ex. Attestation 2026"
                        />
                    </label>

                    <label className="text-sm font-medium">
                        Fichier
                        <input
                            className="ac-input mt-1"
                            type="file"
                            onChange={(e) => setForm((f) => ({ ...f, file: e.target.files?.[0] || null }))}
                            required
                        />
                    </label>

                    <label className="text-sm font-medium">
                        Expire le
                        <input
                            className="ac-input mt-1"
                            type="date"
                            value={form.expires_at}
                            onChange={(e) => setForm((f) => ({ ...f, expires_at: e.target.value }))}
                        />
                    </label>

                    <label className="text-sm font-medium">
                        CID IPFS (optionnel)
                        <input
                            className="ac-input mt-1 font-mono text-xs"
                            value={form.ipfs_cid}
                            onChange={(e) => setForm((f) => ({ ...f, ipfs_cid: e.target.value }))}
                            placeholder="Qm…"
                        />
                    </label>

                    <label className="flex items-center gap-2 text-sm font-medium md:col-span-3">
                        <input
                            type="checkbox"
                            checked={form.is_public}
                            onChange={(e) => setForm((f) => ({ ...f, is_public: e.target.checked }))}
                        />
                        Certificat public (référence IPFS)
                    </label>

                    <div className="md:col-span-3">
                        <button className="ac-btn ac-btn-primary" type="submit" disabled={busy}>
                            {busy ? 'Envoi…' : 'Stocker le document'}
                        </button>
                    </div>
                </form>
            )}

            {error && <p className="text-[var(--color-signal)]">{error}</p>}
            {message && <p className="text-[var(--color-teal)]">{message}</p>}

            <div className="ac-panel overflow-x-auto rounded-lg p-5">
                <table className="w-full min-w-[780px] text-left text-sm">
                    <thead className="text-[var(--color-ink-soft)]">
                        <tr>
                            <th className="pb-3">Titre</th>
                            <th className="pb-3">Véhicule</th>
                            <th className="pb-3">Type</th>
                            <th className="pb-3">Hash</th>
                            <th className="pb-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 && (
                            <tr>
                                <td colSpan={5} className="py-6 text-[var(--color-ink-soft)]">
                                    Aucun document.
                                </td>
                            </tr>
                        )}
                        {items.map((doc) => (
                            <tr key={doc.id} className="border-t border-black/5">
                                <td className="py-3 font-medium">{doc.title}</td>
                                <td className="py-3">{doc.vehicle?.registration_number}</td>
                                <td className="py-3 capitalize">{doc.type?.replace('_', ' ')}</td>
                                <td className="py-3 font-mono text-xs">
                                    {doc.file_hash ? `${doc.file_hash.slice(0, 14)}…` : '—'}
                                </td>
                                <td className="py-3">
                                    <div className="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            className="text-sm font-semibold text-[var(--color-teal)]"
                                            onClick={() => verify(doc.id)}
                                        >
                                            Vérifier
                                        </button>
                                        <button
                                            type="button"
                                            className="text-sm font-semibold text-[var(--color-teal)]"
                                            onClick={() => download(doc)}
                                        >
                                            Télécharger
                                        </button>
                                        {can('gestionnaire', 'super_admin') && (
                                            <button
                                                type="button"
                                                className="text-sm font-semibold text-[var(--color-signal)]"
                                                onClick={() => remove(doc.id)}
                                            >
                                                Supprimer
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
