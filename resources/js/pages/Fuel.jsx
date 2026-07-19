import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';

const emptyForm = {
    vehicle_id: '',
    liters: '',
    cost: '',
    mileage_at_fill: '',
    station: '',
};

export default function Fuel() {
    const { can } = useAuth();
    const [items, setItems] = useState([]);
    const [vehicles, setVehicles] = useState([]);
    const [average, setAverage] = useState(null);
    const [form, setForm] = useState(emptyForm);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');

    async function load() {
        const [f, v] = await Promise.all([api('/fuel-consumptions'), api('/vehicles')]);
        setItems(f.data || []);
        setVehicles(v.data || []);
    }

    useEffect(() => {
        load().catch((err) => setError(err.message));
    }, []);

    async function loadAverage(vehicleId) {
        if (!vehicleId) {
            setAverage(null);
            return;
        }
        try {
            const res = await api(`/vehicles/${vehicleId}/consumption/average`);
            setAverage(res.average_l_per_100km);
        } catch {
            setAverage(null);
        }
    }

    async function onSubmit(e) {
        e.preventDefault();
        setError('');
        setMessage('');

        try {
            const res = await api('/fuel-consumptions', {
                method: 'POST',
                body: {
                    vehicle_id: Number(form.vehicle_id),
                    liters: Number(form.liters),
                    cost: form.cost === '' ? 0 : Number(form.cost),
                    mileage_at_fill: Number(form.mileage_at_fill),
                    station: form.station || null,
                },
            });
            const vehicleId = form.vehicle_id;
            setMessage(
                res.average_consumption
                    ? `Plein enregistré. Conso moyenne : ${res.average_consumption} L/100 km.`
                    : 'Plein enregistré.',
            );
            setForm(emptyForm);
            await load();
            if (vehicleId) await loadAverage(vehicleId);
        } catch (err) {
            setError(err.message);
        }
    }

    return (
        <div className="ac-fade-up space-y-8">
            <div>
                <h1 className="font-display text-3xl font-bold">Suivi de consommation</h1>
                <p className="mt-1 text-[var(--color-ink-soft)]">
                    Calcul automatique de la consommation moyenne (L/100 km).
                </p>
            </div>

            {can('chauffeur', 'gestionnaire', 'super_admin') && (
                <form onSubmit={onSubmit} className="ac-panel grid gap-3 rounded-lg p-5 md:grid-cols-3">
                    <h2 className="font-display text-lg font-bold md:col-span-3">Enregistrer un plein</h2>

                    <label className="text-sm font-medium">
                        Véhicule
                        <select
                            className="ac-input mt-1"
                            value={form.vehicle_id}
                            onChange={(e) => {
                                const vehicle_id = e.target.value;
                                setForm((f) => ({ ...f, vehicle_id }));
                                loadAverage(vehicle_id);
                            }}
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
                        Litres
                        <input
                            className="ac-input mt-1"
                            type="number"
                            min="0.1"
                            step="0.01"
                            value={form.liters}
                            onChange={(e) => setForm((f) => ({ ...f, liters: e.target.value }))}
                            required
                        />
                    </label>

                    <label className="text-sm font-medium">
                        Coût
                        <input
                            className="ac-input mt-1"
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.cost}
                            onChange={(e) => setForm((f) => ({ ...f, cost: e.target.value }))}
                        />
                    </label>

                    <label className="text-sm font-medium">
                        Km au plein
                        <input
                            className="ac-input mt-1"
                            type="number"
                            min="0"
                            value={form.mileage_at_fill}
                            onChange={(e) =>
                                setForm((f) => ({ ...f, mileage_at_fill: e.target.value }))
                            }
                            required
                        />
                    </label>

                    <label className="text-sm font-medium md:col-span-2">
                        Station
                        <input
                            className="ac-input mt-1"
                            value={form.station}
                            onChange={(e) => setForm((f) => ({ ...f, station: e.target.value }))}
                            placeholder="Total Dakar"
                        />
                    </label>

                    {average !== null && (
                        <p className="md:col-span-3 text-sm text-[var(--color-teal)]">
                            Consommation moyenne actuelle : <strong>{average} L/100 km</strong>
                        </p>
                    )}

                    <div className="md:col-span-3">
                        <button className="ac-btn ac-btn-primary" type="submit">
                            Enregistrer le plein
                        </button>
                    </div>
                </form>
            )}

            {error && <p className="text-[var(--color-signal)]">{error}</p>}
            {message && <p className="text-[var(--color-teal)]">{message}</p>}

            <div className="ac-panel overflow-x-auto rounded-lg p-5">
                <table className="w-full min-w-[720px] text-left text-sm">
                    <thead className="text-[var(--color-ink-soft)]">
                        <tr>
                            <th className="pb-3">Véhicule</th>
                            <th className="pb-3">Litres</th>
                            <th className="pb-3">Coût</th>
                            <th className="pb-3">Km</th>
                            <th className="pb-3">L/100 km</th>
                            <th className="pb-3">Station</th>
                            <th className="pb-3">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 && (
                            <tr>
                                <td colSpan={7} className="py-6 text-[var(--color-ink-soft)]">
                                    Aucun plein enregistré.
                                </td>
                            </tr>
                        )}
                        {items.map((row) => (
                            <tr key={row.id} className="border-t border-black/5">
                                <td className="py-3">{row.vehicle?.registration_number}</td>
                                <td className="py-3">{row.liters}</td>
                                <td className="py-3">{Number(row.cost).toLocaleString('fr-FR')}</td>
                                <td className="py-3">
                                    {row.mileage_at_fill?.toLocaleString('fr-FR')}
                                </td>
                                <td className="py-3 font-semibold text-[var(--color-teal)]">
                                    {row.consumption_l_per_100km ?? '—'}
                                </td>
                                <td className="py-3">{row.station || '—'}</td>
                                <td className="py-3">
                                    {row.filled_at
                                        ? new Date(row.filled_at).toLocaleString('fr-FR')
                                        : '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
