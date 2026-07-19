import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';

export default function Mileage() {
    const { can } = useAuth();
    const [records, setRecords] = useState([]);
    const [vehicles, setVehicles] = useState([]);
    const [form, setForm] = useState({ vehicle_id: '', mileage: '', notes: '' });
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');

    async function load() {
        const [r, v] = await Promise.all([api('/mileage-records'), api('/vehicles')]);
        setRecords(r.data || []);
        setVehicles(v.data || []);
    }

    useEffect(() => {
        load().catch((err) => setError(err.message));
    }, []);

    async function onSubmit(e) {
        e.preventDefault();
        setError('');
        try {
            await api('/mileage-records', {
                method: 'POST',
                body: {
                    vehicle_id: Number(form.vehicle_id),
                    mileage: Number(form.mileage),
                    notes: form.notes || null,
                    certify: true,
                },
            });
            setMessage('Relevé certifié et ancré.');
            setForm({ vehicle_id: '', mileage: '', notes: '' });
            await load();
        } catch (err) {
            setError(err.message);
        }
    }

    return (
        <div className="ac-fade-up space-y-8">
            <div>
                <h1 className="font-display text-3xl font-bold">Compteur certifié</h1>
                <p className="mt-1 text-[var(--color-ink-soft)]">
                    Relevés horodatés anti-fraude (pas de baisse de kilométrage).
                </p>
            </div>

            {can('chauffeur', 'gestionnaire', 'super_admin') && (
                <form onSubmit={onSubmit} className="ac-panel grid gap-3 rounded-lg p-5 md:grid-cols-3">
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
                                    {v.registration_number} ({v.current_mileage} km)
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="text-sm font-medium">
                        Nouveau kilométrage
                        <input
                            className="ac-input mt-1"
                            type="number"
                            min="0"
                            value={form.mileage}
                            onChange={(e) => setForm((f) => ({ ...f, mileage: e.target.value }))}
                            required
                        />
                    </label>
                    <label className="text-sm font-medium">
                        Notes
                        <input
                            className="ac-input mt-1"
                            value={form.notes}
                            onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))}
                        />
                    </label>
                    <div className="md:col-span-3">
                        <button className="ac-btn ac-btn-primary" type="submit">
                            Certifier le relevé
                        </button>
                    </div>
                </form>
            )}

            {error && <p className="text-[var(--color-signal)]">{error}</p>}
            {message && <p className="text-[var(--color-teal)]">{message}</p>}

            <div className="ac-panel overflow-x-auto rounded-lg p-5">
                <table className="w-full min-w-[640px] text-left text-sm">
                    <thead className="text-[var(--color-ink-soft)]">
                        <tr>
                            <th className="pb-3">Véhicule</th>
                            <th className="pb-3">Km</th>
                            <th className="pb-3">Date</th>
                            <th className="pb-3">Tx hash</th>
                            <th className="pb-3">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        {records.map((r) => (
                            <tr key={r.id} className="border-t border-black/5">
                                <td className="py-3">{r.vehicle?.registration_number}</td>
                                <td className="py-3">{r.mileage?.toLocaleString('fr-FR')}</td>
                                <td className="py-3">
                                    {r.recorded_at
                                        ? new Date(r.recorded_at).toLocaleString('fr-FR')
                                        : '—'}
                                </td>
                                <td className="py-3 font-mono text-xs">
                                    {r.blockchain_tx_hash
                                        ? `${r.blockchain_tx_hash.slice(0, 12)}…`
                                        : '—'}
                                </td>
                                <td className="py-3">
                                    {r.is_certified ? 'Certifié' : r.status}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
