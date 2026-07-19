import { NavLink, Outlet } from 'react-router-dom';
import { useState } from 'react';
import { useAuth } from '../context/AuthContext';

const links = [
    { to: '/app', label: 'Tableau de bord', end: true },
    { to: '/app/vehicles', label: 'Véhicules' },
    { to: '/app/assignments', label: 'Affectations' },
    { to: '/app/mileage', label: 'Kilométrage' },
    { to: '/app/maintenances', label: 'Maintenances' },
    { to: '/app/documents', label: 'Documents' },
    { to: '/app/fuel', label: 'Consommation' },
    { to: '/app/alerts', label: 'Alertes' },
    { to: '/app/blockchain', label: 'Blockchain' },
];

export default function AppLayout() {
    const { user, logout } = useAuth();
    const [open, setOpen] = useState(false);

    return (
        <div className="ac-shell flex min-h-screen">
            <aside
                className={`ac-sidebar ac-panel w-64 shrink-0 p-5 md:static md:translate-x-0 ${open ? 'open' : ''}`}
            >
                <div className="mb-8">
                    <p className="font-display text-2xl font-bold tracking-tight text-[var(--color-ink)]">
                        Autochain
                    </p>
                    <p className="text-sm text-[var(--color-ink-soft)]">Emma+ · Lass</p>
                </div>

                <nav className="flex flex-col gap-1">
                    {links.map((link) => (
                        <NavLink
                            key={link.to}
                            to={link.to}
                            end={link.end}
                            onClick={() => setOpen(false)}
                            className={({ isActive }) =>
                                `rounded-md px-3 py-2 text-sm font-medium transition ${
                                    isActive
                                        ? 'bg-[var(--color-teal)] text-white'
                                        : 'text-[var(--color-ink-soft)] hover:bg-white/70'
                                }`
                            }
                        >
                            {link.label}
                        </NavLink>
                    ))}
                </nav>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex items-center justify-between gap-3 px-4 py-4 md:px-8">
                    <button
                        type="button"
                        className="ac-btn ac-panel md:hidden"
                        onClick={() => setOpen((v) => !v)}
                    >
                        Menu
                    </button>
                    <div className="ml-auto text-right">
                        <p className="font-medium">{user?.name}</p>
                        <p className="text-sm text-[var(--color-ink-soft)]">{user?.role_label}</p>
                    </div>
                    <button type="button" className="ac-btn ac-btn-primary" onClick={logout}>
                        Déconnexion
                    </button>
                </header>

                <main className="px-4 pb-10 md:px-8">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
