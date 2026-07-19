export function hasMetaMask() {
    return typeof window !== 'undefined' && Boolean(window.ethereum?.isMetaMask || window.ethereum);
}

export async function connectWallet() {
    if (!hasMetaMask()) {
        throw new Error('MetaMask introuvable. Installez l’extension puis réessayez.');
    }

    const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
    const address = accounts?.[0];
    if (!address) {
        throw new Error('Aucun compte MetaMask sélectionné.');
    }

    return address;
}

export async function personalSign(message, address) {
    return window.ethereum.request({
        method: 'personal_sign',
        params: [message, address],
    });
}

export async function ensureChain(chainIdDecimal = 31337) {
    if (!hasMetaMask()) return;

    const hexId = '0x'+Number(chainIdDecimal).toString(16);
    const current = await window.ethereum.request({ method: 'eth_chainId' });

    if (current?.toLowerCase() === hexId.toLowerCase()) {
        return;
    }

    try {
        await window.ethereum.request({
            method: 'wallet_switchEthereumChain',
            params: [{ chainId: hexId }],
        });
    } catch (err) {
        if (err?.code === 4902) {
            await window.ethereum.request({
                method: 'wallet_addEthereumChain',
                params: [
                    {
                        chainId: hexId,
                        chainName: 'Autochain Local Hardhat',
                        rpcUrls: ['http://127.0.0.1:8545'],
                        nativeCurrency: { name: 'ETH', symbol: 'ETH', decimals: 18 },
                    },
                ],
            });
        } else {
            throw err;
        }
    }
}

export async function sendContractTransaction({ to, data, chainId = 31337 }) {
    await ensureChain(chainId);
    const from = await connectWallet();

    return window.ethereum.request({
        method: 'eth_sendTransaction',
        params: [
            {
                from,
                to,
                data,
                value: '0x0',
            },
        ],
    });
}
