import { useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { connectWallet, hasMetaMask, personalSign } from '../web3/metamask';

const demos = [
    'admin@autochain.local',
    'gestionnaire@autochain.local',
    'chauffeur@autochain.local',
    'garagiste@autochain.local',
    'auditeur@autochain.local',
];

export default function Login() {
    const { user, login, loginWallet } = useAuth();
    const navigate = useNavigate();
    const [email, setEmail] = useState('admin@autochain.local');
    const [password, setPassword] = useState('password');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    if (user) {
        return <Navigate to="/app" replace />;
    }

    async function onSubmit(e) {
        e.preventDefault();
        setBusy(true);
        setError('');
        try {
            await login(email, password);
            navigate('/app');
        } catch (err) {
            setError(err.message);
        } finally {
            setBusy(false);
        }
    }

    async function onMetaMask() {
        setBusy(true);
        setError('');
        try {
            if (!hasMetaMask()) {
                throw new Error('MetaMask non détecté. Utilisez la connexion email ou installez MetaMask.');
            }

            const address = await connectWallet();
            const challenge = await api(`/auth/wallet/nonce?address=${encodeURIComponent(address)}`);
            const signature = await personalSign(challenge.message, address);

            await loginWallet({
                wallet_address: address,
                signature,
                message: challenge.message,
            });
            navigate('/app');
        } catch (err) {
            setError(err.message || 'Échec connexion MetaMask.');
        } finally {
            setBusy(false);
        }
    }

    return (
        <div className="ac-shell flex min-h-screen items-center justify-center px-4 py-10">
            <div className="ac-panel w-full max-w-md rounded-xl p-8 ac-fade-up">
                <Link to="/" className="font-display text-3xl font-bold text-[var(--color-ink)]">
                    Autochain Emma+
                </Link>
                <p className="mt-2 text-sm text-[var(--color-ink-soft)]">
                    Connexion sécurisée · auteur Lass
                </p>

                <form onSubmit={onSubmit} className="mt-8 space-y-4">
                    <label className="block text-sm font-medium">
                        Email
                        <input
                            className="ac-input mt-1"
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            required
                        />
                    </label>
                    <label className="block text-sm font-medium">
                        Mot de passe
                        <input
                            className="ac-input mt-1"
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            required
                        />
                    </label>

                    {error && <p className="text-sm text-[var(--color-signal)]">{error}</p>}

                    <button type="submit" className="ac-btn ac-btn-primary w-full" disabled={busy}>
                        {busy ? 'Connexion…' : 'Se connecter'}
                    </button>
                </form>

                <div className="mt-6 border-t border-black/5 pt-6">
                    <p className="text-sm font-medium">Web3 — MetaMask</p>
                    <p className="mt-1 text-xs text-[var(--color-ink-soft)]">
                        Signature `personal_sign` vérifiée côté Laravel (EIP-191). L’adresse doit être
                        liée à un compte.
                    </p>
                    <button
                        type="button"
                        className="ac-btn mt-3 w-full border border-[var(--color-teal)] text-[var(--color-teal)]"
                        onClick={onMetaMask}
                        disabled={busy}
                    >
                        {hasMetaMask() ? 'Connexion MetaMask' : 'MetaMask indisponible'}
                    </button>
                </div>

                <div className="mt-6">
                    <p className="text-xs uppercase tracking-wide text-[var(--color-ink-soft)]">
                        Comptes démo (mdp: password)
                    </p>
                    <div className="mt-2 flex flex-wrap gap-2">
                        {demos.map((demo) => (
                            <button
                                key={demo}
                                type="button"
                                className="rounded bg-white/80 px-2 py-1 text-xs text-[var(--color-ink-soft)]"
                                onClick={() => setEmail(demo)}
                            >
                                {demo.split('@')[0]}
                            </button>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
