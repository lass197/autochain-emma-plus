import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { api, clearToken, getToken, setToken } from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const token = getToken();
        if (!token) {
            setLoading(false);
            return;
        }

        api('/auth/me')
            .then((data) => setUser(data.user))
            .catch(() => clearToken())
            .finally(() => setLoading(false));
    }, []);

    const value = useMemo(
        () => ({
            user,
            loading,
            async login(email, password) {
                const data = await api('/auth/login', {
                    method: 'POST',
                    body: { email, password, device_name: 'web' },
                });
                setToken(data.token);
                setUser(data.user);
                return data.user;
            },
            async loginWallet({ wallet_address, signature, message }) {
                const data = await api('/auth/wallet', {
                    method: 'POST',
                    body: {
                        wallet_address,
                        signature,
                        message,
                        device_name: 'web3',
                    },
                });
                setToken(data.token);
                setUser(data.user);
                return data.user;
            },
            async logout() {
                try {
                    await api('/auth/logout', { method: 'POST' });
                } catch {
                    // ignore
                }
                clearToken();
                setUser(null);
            },
            can(...roles) {
                if (!user) return false;
                if (user.role === 'super_admin') return true;
                return roles.includes(user.role);
            },
        }),
        [user, loading],
    );

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    return useContext(AuthContext);
}
