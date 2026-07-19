import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { connectWallet, hasMetaMask, sendContractTransaction } from '../web3/metamask';

export default function Blockchain() {
    const { can } = useAuth();
    const [settings, setSettings] = useState(null);
    const [status, setStatus] = useState(null);
    const [txs, setTxs] = useState([]);
    const [wallet, setWallet] = useState('');
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');

    async function load() {
        const [s, t, st] = await Promise.all([
            api('/blockchain/settings'),
            api('/blockchain/transactions'),
            api('/blockchain/status'),
        ]);
        setSettings(s.settings);
        setTxs(t.data || []);
        setStatus(st.status);
    }

    useEffect(() => {
        load().catch((err) => setError(err.message));
    }, []);

    async function saveSettings(e) {
        e.preventDefault();
        try {
            const res = await api('/blockchain/settings', {
                method: 'PUT',
                body: settings,
            });
            setSettings(res.settings);
            setMessage('Configuration smart contract mise à jour.');
            const st = await api('/blockchain/status');
            setStatus(st.status);
        } catch (err) {
            setError(err.message);
        }
    }

    async function connect() {
        try {
            const address = await connectWallet();
            setWallet(address);
            setMessage(`Wallet connecté : ${address}`);
        } catch (err) {
            setError(err.message);
        }
    }

    async function anchor(id, forceSimulate = false) {
        setError('');
        try {
            const res = await api(`/blockchain/transactions/${id}/anchor`, {
                method: 'POST',
                body: { force_simulate: forceSimulate },
            });
            setMessage(`${res.message} (mode: ${res.mode})`);
            await load();
        } catch (err) {
            setError(err.message);
        }
    }

    async function anchorWithMetaMask(id) {
        setError('');
        try {
            const { calldata } = await api(`/blockchain/transactions/${id}/calldata`);
            if (!calldata?.to || !calldata?.data) {
                throw new Error('Calldata indisponible. Configurez l’adresse du contrat.');
            }

            const txHash = await sendContractTransaction({
                to: calldata.to,
                data: calldata.data,
                chainId: calldata.chain_id || status?.chain_id || 31337,
            });

            await api(`/blockchain/transactions/${id}/confirm`, {
                method: 'POST',
                body: { tx_hash: txHash },
            });

            setMessage(`Ancré via MetaMask — tx ${txHash.slice(0, 12)}…`);
            await load();
        } catch (err) {
            setError(err.message || 'Échec ancrage MetaMask.');
        }
    }

    async function signAdmin(id) {
        try {
            await api(`/blockchain/transactions/${id}/sign-admin`, { method: 'POST' });
            setMessage('Signature admin enregistrée.');
            await load();
        } catch (err) {
            setError(err.message);
        }
    }

    async function signBuyer(id) {
        try {
            await api(`/blockchain/transactions/${id}/sign-buyer`, { method: 'POST' });
            setMessage('Signature acheteur enregistrée.');
            await load();
        } catch (err) {
            setError(err.message);
        }
    }

    if (!settings || !status) {
        return <p className="text-[var(--color-ink-soft)]">Chargement…</p>;
    }

    return (
        <div className="ac-fade-up space-y-8">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="font-display text-3xl font-bold">Couche blockchain</h1>
                    <p className="mt-1 text-[var(--color-ink-soft)]">
                        Smart contract VehicleRegistry · preuves sans données nominatives.
                    </p>
                </div>
                <button
                    type="button"
                    className="ac-btn ac-btn-primary"
                    onClick={connect}
                    disabled={!hasMetaMask()}
                >
                    {wallet ? `${wallet.slice(0, 6)}…${wallet.slice(-4)}` : 'Connecter MetaMask'}
                </button>
            </div>

            <section className="ac-panel grid gap-3 rounded-lg p-5 md:grid-cols-4">
                <div>
                    <p className="text-xs uppercase text-[var(--color-ink-soft)]">Mode</p>
                    <p className="font-display text-xl font-bold text-[var(--color-teal)]">
                        {status.mode}
                    </p>
                </div>
                <div>
                    <p className="text-xs uppercase text-[var(--color-ink-soft)]">RPC</p>
                    <p className="text-sm">{status.rpc_reachable ? 'En ligne' : 'Hors ligne'}</p>
                </div>
                <div>
                    <p className="text-xs uppercase text-[var(--color-ink-soft)]">Chain ID</p>
                    <p className="text-sm">{status.chain_id ?? '—'}</p>
                </div>
                <div>
                    <p className="text-xs uppercase text-[var(--color-ink-soft)]">Bloc</p>
                    <p className="text-sm">{status.block_number ?? '—'}</p>
                </div>
                <div className="md:col-span-2">
                    <p className="text-xs uppercase text-[var(--color-ink-soft)]">Contrat</p>
                    <p className="font-mono text-xs break-all">{status.contract_address || 'Non déployé'}</p>
                </div>
                <div className="md:col-span-2">
                    <p className="text-xs uppercase text-[var(--color-ink-soft)]">Opérateur</p>
                    <p className="font-mono text-xs break-all">
                        {status.operator_address || 'Clé non configurée'}
                    </p>
                </div>
            </section>

            {can('super_admin') && (
                <form onSubmit={saveSettings} className="ac-panel grid gap-3 rounded-lg p-5 md:grid-cols-2">
                    <h2 className="font-display text-lg font-bold md:col-span-2">
                        Configuration smart contract
                    </h2>
                    {['network', 'rpc_url', 'contract_address'].map((key) => (
                        <label key={key} className="text-sm font-medium">
                            {key}
                            <input
                                className="ac-input mt-1 font-mono text-xs"
                                value={settings[key] || ''}
                                onChange={(e) =>
                                    setSettings((s) => ({ ...s, [key]: e.target.value }))
                                }
                            />
                        </label>
                    ))}
                    <label className="flex items-center gap-2 text-sm font-medium md:col-span-2">
                        <input
                            type="checkbox"
                            checked={Boolean(settings.require_double_signature)}
                            onChange={(e) =>
                                setSettings((s) => ({
                                    ...s,
                                    require_double_signature: e.target.checked,
                                }))
                            }
                        />
                        Double signature obligatoire (admin + acheteur)
                    </label>
                    <div className="md:col-span-2">
                        <button className="ac-btn ac-btn-primary" type="submit">
                            Enregistrer
                        </button>
                    </div>
                </form>
            )}

            {error && <p className="text-[var(--color-signal)]">{error}</p>}
            {message && <p className="text-[var(--color-teal)]">{message}</p>}

            <section className="ac-panel overflow-x-auto rounded-lg p-5">
                <h2 className="font-display mb-4 text-lg font-bold">Transactions</h2>
                <table className="w-full min-w-[820px] text-left text-sm">
                    <thead className="text-[var(--color-ink-soft)]">
                        <tr>
                            <th className="pb-3">Action</th>
                            <th className="pb-3">Véhicule</th>
                            <th className="pb-3">Payload hash</th>
                            <th className="pb-3">Tx</th>
                            <th className="pb-3">Statut</th>
                            <th className="pb-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {txs.map((tx) => (
                            <tr key={tx.id} className="border-t border-black/5">
                                <td className="py-3">{tx.action_type}</td>
                                <td className="py-3">{tx.vehicle?.registration_number || '—'}</td>
                                <td className="py-3 font-mono text-xs">
                                    {tx.payload_hash?.slice(0, 16)}…
                                </td>
                                <td className="py-3 font-mono text-xs">
                                    {tx.tx_hash ? `${tx.tx_hash.slice(0, 10)}…` : '—'}
                                </td>
                                <td className="py-3">{tx.status}</td>
                                <td className="py-3 text-right">
                                    <div className="flex flex-wrap justify-end gap-2">
                                        {can('gestionnaire', 'super_admin') &&
                                            !tx.signed_by_admin &&
                                            tx.status !== 'confirmed' && (
                                                <button
                                                    type="button"
                                                    className="text-sm font-semibold text-[var(--color-teal)]"
                                                    onClick={() => signAdmin(tx.id)}
                                                >
                                                    Signer admin
                                                </button>
                                            )}
                                        {can('auditeur', 'super_admin') &&
                                            !tx.signed_by_buyer &&
                                            tx.status !== 'confirmed' && (
                                                <button
                                                    type="button"
                                                    className="text-sm font-semibold text-[var(--color-teal)]"
                                                    onClick={() => signBuyer(tx.id)}
                                                >
                                                    Signer acheteur
                                                </button>
                                            )}
                                        {can('gestionnaire', 'super_admin') &&
                                            tx.status !== 'confirmed' && (
                                                <>
                                                    <button
                                                        type="button"
                                                        className="text-sm font-semibold text-[var(--color-teal)]"
                                                        onClick={() => anchor(tx.id)}
                                                    >
                                                        Ancrer (serveur)
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="text-sm font-semibold text-[var(--color-teal)]"
                                                        onClick={() => anchorWithMetaMask(tx.id)}
                                                    >
                                                        Ancrer MetaMask
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="text-sm text-[var(--color-ink-soft)]"
                                                        onClick={() => anchor(tx.id, true)}
                                                    >
                                                        Simuler
                                                    </button>
                                                </>
                                            )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>

            <section className="ac-panel rounded-lg p-5 text-sm text-[var(--color-ink-soft)]">
                <h2 className="font-display text-lg font-bold text-[var(--color-ink)]">
                    Déploiement local
                </h2>
                <ol className="mt-3 list-decimal space-y-1 pl-5">
                    <li>
                        <code>cd blockchain && npm install</code>
                    </li>
                    <li>
                        <code>npx hardhat node</code> (terminal 1)
                    </li>
                    <li>
                        <code>npm run deploy:local</code> (terminal 2)
                    </li>
                    <li>Coller l’adresse du contrat dans la config ci-dessus</li>
                </ol>
            </section>
        </div>
    );
}
