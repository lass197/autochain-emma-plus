import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';

const emptyForm = {
    vin: '',
    registration_number: '',
    brand: '',
    model: '',
    year: new Date().getFullYear(),
    current_mileage: 0,
    fuel_type: 'essence',
};

export default function Vehicles() {
    const { can } = useAuth();
    const [items, setItems] = useState([]);
    const [form, setForm] = useState(emptyForm);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');

    async function load() {
        const data = await api('/vehicles');
        setItems(data.data || []);
    }

    useEffect(() => {
        load().catch((err) => setError(err.message));
    }, []);

    async function onCreate(e) {
        e.preventDefault();
        setError('');
        setMessage('');
        try {
            await api('/vehicles', { method: 'POST', body: form });
            setForm(emptyForm);
            setMessage('Véhicule enregistré et preuve blockchain préparée.');
            await load();
        } catch (err) {
            setError(err.message);
        }
    }

    return (
        <div className="ac-fade-up space-y-8">
            <div>
                <h1 className="font-display text-3xl font-bold">Véhicules</h1>
                <p className="mt-1 text-[var(--color-ink-soft)]">
                    Registre du parc avec identifiant technique (RGPD).
                </p>
            </div>

            {can('gestionnaire', 'super_admin') && (
                <form onSubmit={onCreate} className="ac-panel grid gap-3 rounded-lg p-5 md:grid-cols-3">
                    <h2 className="font-display text-lg font-bold md:col-span-3">Nouvel enregistrement</h2>
                    {[
                        ['vin', 'VIN (17)'],
                        ['registration_number', 'Immatriculation'],
                        ['brand', 'Marque'],
                        ['model', 'Modèle'],
                        ['year', 'Année', 'number'],
                        ['current_mileage', 'Km actuel', 'number'],
                    ].map(([key, label, type = 'text']) => (
                        <label key={key} className="text-sm font-medium">
                            {label}
                            <input
                                className="ac-input mt-1"
                                type={type}
                                value={form[key]}
                                onChange={(e) =>
                                    setForm((f) => ({
                                        ...f,
                                        [key]: type === 'number' ? Number(e.target.value) : e.target.value,
                                    }))
                                }
                                required
                            />
                        </label>
                    ))}
                    <div className="md:col-span-3">
                        <button type="submit" className="ac-btn ac-btn-primary">
                            Enregistrer
                        </button>
                    </div>
                </form>
            )}

            {error && <p className="text-[var(--color-signal)]">{error}</p>}
            {message && <p className="text-[var(--color-teal)]">{message}</p>}

            <div className="ac-panel overflow-x-auto rounded-lg p-5">
                <table className="w-full min-w-[700px] text-left text-sm">
                    <thead className="text-[var(--color-ink-soft)]">
                        <tr>
                            <th className="pb-3">Immatriculation</th>
                            <th className="pb-3">Véhicule</th>
                            <th className="pb-3">Km</th>
                            <th className="pb-3">Statut</th>
                            <th className="pb-3">Technical ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((v) => (
                            <tr key={v.id} className="border-t border-black/5">
                                <td className="py-3">
                                    <Link
                                        className="font-semibold text-[var(--color-teal)]"
                                        to={`/app/vehicles/${v.id}`}
                                    >
                                        {v.registration_number}
                                    </Link>
                                </td>
                                <td className="py-3">
                                    {v.brand} {v.model} ({v.year})
                                </td>
                                <td className="py-3">{v.current_mileage?.toLocaleString('fr-FR')}</td>
                                <td className="py-3 capitalize">{v.status?.replace('_', ' ')}</td>
                                <td className="py-3 font-mono text-xs">{v.technical_id}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
