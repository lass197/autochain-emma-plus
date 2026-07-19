import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../api/client';

export default function VehicleDetail() {
    const { id } = useParams();
    const [vehicle, setVehicle] = useState(null);
    const [timeline, setTimeline] = useState([]);
    const [proof, setProof] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        Promise.all([
            api(`/vehicles/${id}`),
            api(`/vehicles/${id}/timeline`),
            api(`/vehicles/${id}/blockchain`),
        ])
            .then(([v, t, p]) => {
                setVehicle(v.vehicle);
                setTimeline(t.timeline || []);
                setProof(p);
            })
            .catch((err) => setError(err.message));
    }, [id]);

    if (error) return <p className="text-[var(--color-signal)]">{error}</p>;
    if (!vehicle) return <p className="text-[var(--color-ink-soft)]">Chargement…</p>;

    return (
        <div className="ac-fade-up space-y-8">
            <div>
                <Link to="/app/vehicles" className="text-sm text-[var(--color-teal)]">
                    ← Retour
                </Link>
                <h1 className="font-display mt-2 text-3xl font-bold">
                    {vehicle.brand} {vehicle.model}
                </h1>
                <p className="text-[var(--color-ink-soft)]">
                    {vehicle.registration_number} · {vehicle.current_mileage?.toLocaleString('fr-FR')} km
                </p>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <section className="ac-panel rounded-lg p-5 lg:col-span-1">
                    <h2 className="font-display text-lg font-bold">Identité technique</h2>
                    <dl className="mt-4 space-y-3 text-sm">
                        <div>
                            <dt className="text-[var(--color-ink-soft)]">Technical ID</dt>
                            <dd className="font-mono text-xs break-all">{vehicle.technical_id}</dd>
                        </div>
                        <div>
                            <dt className="text-[var(--color-ink-soft)]">VIN</dt>
                            <dd className="font-mono">{vehicle.vin}</dd>
                        </div>
                        <div>
                            <dt className="text-[var(--color-ink-soft)]">Hash blockchain</dt>
                            <dd className="font-mono text-xs break-all">
                                {proof?.blockchain_hash || '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-[var(--color-ink-soft)]">Chauffeur</dt>
                            <dd>{vehicle.active_assignment?.driver?.name || 'Non affecté'}</dd>
                        </div>
                    </dl>
                </section>

                <section className="ac-panel rounded-lg p-5 lg:col-span-2">
                    <h2 className="font-display text-lg font-bold">Timeline véhicule</h2>
                    <p className="mt-1 text-sm text-[var(--color-ink-soft)]">
                        Historique combiné blockchain + backend
                    </p>
                    <div className="mt-6 space-y-4">
                        {timeline.length === 0 && (
                            <p className="text-sm text-[var(--color-ink-soft)]">Aucun événement.</p>
                        )}
                        {timeline.map((event, index) => (
                            <article key={`${event.type}-${index}`} className="ac-timeline-item">
                                <div className="flex flex-wrap items-baseline justify-between gap-2">
                                    <h3 className="font-semibold">{event.title}</h3>
                                    <span className="text-xs uppercase tracking-wide text-[var(--color-ink-soft)]">
                                        {event.source} · {event.type}
                                    </span>
                                </div>
                                <p className="mt-1 text-sm text-[var(--color-ink-soft)]">
                                    {event.summary || '—'}
                                </p>
                                <p className="mt-1 text-xs text-[var(--color-ink-soft)]">
                                    {event.occurred_at
                                        ? new Date(event.occurred_at).toLocaleString('fr-FR')
                                        : ''}
                                </p>
                            </article>
                        ))}
                    </div>
                </section>
            </div>
        </div>
    );
}
