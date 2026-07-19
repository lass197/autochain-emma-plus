import { Link } from 'react-router-dom';

export default function Landing() {
    return (
        <section className="ac-hero">
            <div className="relative z-10 mx-auto flex min-h-screen max-w-6xl flex-col justify-center px-6 py-16">
                <p className="ac-fade-up font-display text-5xl font-extrabold tracking-tight md:text-7xl">
                    Autochain Emma+
                </p>
                <h1 className="ac-fade-up-delay mt-5 max-w-2xl text-xl font-medium text-teal-50 md:text-2xl">
                    Le double numérique infalsifiable de chaque véhicule.
                </h1>
                <p className="ac-fade-up-delay mt-4 max-w-xl text-base text-teal-100/85 md:text-lg">
                    Traçabilité du kilométrage, entretiens certifiés et documents sécurisés — Laravel
                    & blockchain, par Lass.
                </p>
                <div className="ac-fade-up-delay mt-10 flex flex-wrap gap-3">
                    <Link to="/login" className="ac-btn ac-btn-primary">
                        Accéder à la plateforme
                    </Link>
                    <a href="#apropos" className="ac-btn ac-btn-ghost">
                        Voir le concept
                    </a>
                </div>
            </div>

            <div id="apropos" className="relative z-10 border-t border-white/10 bg-black/20 px-6 py-14">
                <div className="mx-auto grid max-w-6xl gap-8 md:grid-cols-3">
                    {[
                        ['Intégrité', 'Relevés kilométriques horodatés et ancrés on-chain.'],
                        ['Transparence', 'Timeline combinée backend + preuves blockchain.'],
                        ['RGPD', 'Aucun nom sur la chaîne : uniquement des identifiants techniques.'],
                    ].map(([title, text]) => (
                        <div key={title}>
                            <h2 className="font-display text-xl font-bold text-white">{title}</h2>
                            <p className="mt-2 text-sm leading-relaxed text-teal-50/80">{text}</p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
