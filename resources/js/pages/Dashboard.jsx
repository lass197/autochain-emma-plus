import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';

export default function Dashboard() {
    const [data, setData] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        api('/dashboard')
            .then(setData)
            .catch((err) => setError(err.message));
    }, []);

    if (error) {
        return <p className="text-[var(--color-signal)]">{error}</p>;
    }

    if (!data) {
        return <p className="text-[var(--color-ink-soft)]">Chargement du parc…</p>;
    }

    const stats = [
        ['Véhicules', data.stats.total_vehicles],
        ['Disponibles', data.stats.disponibles],
        ['Affectés', data.stats.affectes],
        ['Maintenance', data.stats.en_maintenance],
        ['Alertes', data.stats.alerts_ouvertes],
    ];

    return (
        <div className="ac-fade-up space-y-8">
            <div>
                <h1 className="font-display text-3xl font-bold">État de la flotte</h1>
                <p className="mt-1 text-[var(--color-ink-soft)]">
                    Vue consolidée Autochain Emma+ — données métier + preuves blockchain.
                </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                {stats.map(([label, value], index) => (
                    <div
                        key={label}
                        className="ac-panel rounded-lg px-4 py-5"
                        style={{ animationDelay: `${index * 60}ms` }}
                    >
                        <p className="text-sm text-[var(--color-ink-soft)]">{label}</p>
                        <p className="font-display mt-1 text-3xl font-bold text-[var(--color-teal)]">
                            {value}
                        </p>
                    </div>
                ))}
            </div>

            <section className="ac-panel rounded-lg p-5">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="font-display text-xl font-bold">Véhicules récents</h2>
                    <Link to="/app/vehicles" className="text-sm font-semibold text-[var(--color-teal)]">
                        Voir tout
                    </Link>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[640px] text-left text-sm">
                        <thead className="text-[var(--color-ink-soft)]">
                            <tr>
                                <th className="pb-3 font-medium">Immatriculation</th>
                                <th className="pb-3 font-medium">Modèle</th>
                                <th className="pb-3 font-medium">Statut</th>
                                <th className="pb-3 font-medium">Km</th>
                                <th className="pb-3 font-medium">Chauffeur</th>
                                <th className="pb-3 font-medium">Conso.</th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.recent_vehicles.map((v) => (
                                <tr key={v.id} className="border-t border-black/5">
                                    <td className="py-3">
                                        <Link
                                            to={`/app/vehicles/${v.id}`}
                                            className="font-semibold text-[var(--color-teal)]"
                                        >
                                            {v.registration_number}
                                        </Link>
                                    </td>
                                    <td className="py-3">
                                        {v.brand} {v.model}
                                    </td>
                                    <td className="py-3 capitalize">{v.status?.replace('_', ' ')}</td>
                                    <td className="py-3">{v.current_mileage?.toLocaleString('fr-FR')}</td>
                                    <td className="py-3">{v.driver?.name || '—'}</td>
                                    <td className="py-3">
                                        {v.avg_consumption ? `${v.avg_consumption} L/100` : '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    );
}
