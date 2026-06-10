import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ClipboardCheck,
    MonitorPlay,
    Receipt,
    Swords,
    Timer,
    Trophy,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, login, register } from '@/routes';
import proyeccion from '@/routes/proyeccion';
import type { Auth } from '@/types';

const features = [
    {
        icon: Receipt,
        title: 'Inscripciones y caja',
        desc: 'Tarifas por fecha y control de pagos por robot.',
    },
    {
        icon: ClipboardCheck,
        title: 'Inspección técnica',
        desc: 'Checklist de peso, dimensiones y aprobación.',
    },
    {
        icon: Swords,
        title: 'Combate por rounds',
        desc: 'Mejor de tres, amonestaciones y reparación.',
    },
    {
        icon: Timer,
        title: 'Cronometraje',
        desc: 'Hasta 3 intentos; el mejor tiempo válido gana.',
    },
    {
        icon: Trophy,
        title: 'Brackets y podio',
        desc: 'Llaves automáticas y podio de los 3 primeros.',
    },
    {
        icon: MonitorPlay,
        title: 'Proyección en vivo',
        desc: 'Marcador y bracket en pantalla, en tiempo real.',
    },
];

export default function Welcome() {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <>
            <Head title="RoboLeague — Gestión de competencias de robótica" />

            <div className="rl-landing relative min-h-svh overflow-hidden bg-[var(--rl-navy)] font-sans text-[var(--rl-fg)] antialiased">
                {/* Capas de fondo: rejilla de circuito + resplandor eléctrico */}
                <div aria-hidden className="rl-grid pointer-events-none absolute inset-0" />
                <div
                    aria-hidden
                    className="pointer-events-none absolute -top-40 left-1/2 h-[42rem] w-[42rem] -translate-x-1/3 rounded-full opacity-60 blur-[120px]"
                    style={{
                        background:
                            'radial-gradient(circle, var(--rl-blue) 0%, transparent 70%)',
                    }}
                />
                <div
                    aria-hidden
                    className="pointer-events-none absolute -right-40 top-1/3 h-[34rem] w-[34rem] rounded-full opacity-40 blur-[130px]"
                    style={{
                        background:
                            'radial-gradient(circle, var(--rl-cyan) 0%, transparent 70%)',
                    }}
                />

                <div className="relative mx-auto flex min-h-svh max-w-6xl flex-col px-6 lg:px-8">
                    {/* Encabezado */}
                    <header className="rl-up flex items-center justify-between py-6">
                        <div className="flex items-center gap-2.5">
                            <div className="flex aspect-square size-9 items-center justify-center rounded-lg bg-[var(--rl-blue)] text-[var(--rl-navy)] shadow-[0_0_24px_-4px_var(--rl-blue)]">
                                <AppLogoIcon className="size-5" />
                            </div>
                            <span className="font-display text-lg font-semibold tracking-wide">
                                RoboLeague
                            </span>
                        </div>

                        <nav className="flex items-center gap-2 text-sm">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="rounded-lg bg-[var(--rl-blue)] px-4 py-2 font-medium text-[var(--rl-navy)] transition hover:brightness-110"
                                >
                                    Ir al panel
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="rounded-lg px-4 py-2 font-medium text-[var(--rl-fg)]/80 transition hover:bg-white/5 hover:text-[var(--rl-fg)]"
                                    >
                                        Iniciar sesión
                                    </Link>
                                    <Link
                                        href={register()}
                                        className="rounded-lg bg-[var(--rl-blue)] px-4 py-2 font-medium text-[var(--rl-navy)] transition hover:brightness-110"
                                    >
                                        Crear cuenta
                                    </Link>
                                </>
                            )}
                        </nav>
                    </header>

                    {/* Hero */}
                    <main className="grid flex-1 items-center gap-12 py-10 lg:grid-cols-[1.1fr_0.9fr] lg:py-16">
                        <div>
                            <p className="rl-up rl-d1 mb-6 inline-flex items-center gap-2 rounded-full border border-[var(--rl-border)] bg-white/[0.03] px-3 py-1 text-xs font-medium tracking-wide text-[var(--rl-muted)]">
                                <span className="rl-pulse inline-block size-1.5 rounded-full bg-[var(--rl-cyan)]" />
                                Plataforma de torneos de robótica
                            </p>

                            <h1 className="rl-up rl-d2 font-display text-5xl font-bold leading-[1.05] tracking-tight sm:text-6xl lg:text-[4.25rem]">
                                Organiza, juzga y{' '}
                                <span className="text-[var(--rl-cyan)] [text-shadow:0_0_28px_var(--rl-cyan)]">
                                    proyecta
                                </span>{' '}
                                tu competencia.
                            </h1>

                            <p className="rl-up rl-d3 mt-6 max-w-xl text-lg leading-relaxed text-[var(--rl-muted)]">
                                Inscripciones, inspección técnica, combates por
                                rounds, cronometraje y brackets — con marcador y
                                podio en pantalla, en tiempo real.
                            </p>

                            <div className="rl-up rl-d4 mt-9 flex flex-wrap items-center gap-3">
                                {auth.user ? (
                                    <Link
                                        href={dashboard()}
                                        className="group inline-flex items-center gap-2 rounded-xl bg-[var(--rl-blue)] px-6 py-3 font-semibold text-[var(--rl-navy)] shadow-[0_0_32px_-6px_var(--rl-blue)] transition hover:brightness-110"
                                    >
                                        Ir al panel
                                        <ArrowRight className="size-4 transition group-hover:translate-x-0.5" />
                                    </Link>
                                ) : (
                                    <Link
                                        href={login()}
                                        className="group inline-flex items-center gap-2 rounded-xl bg-[var(--rl-blue)] px-6 py-3 font-semibold text-[var(--rl-navy)] shadow-[0_0_32px_-6px_var(--rl-blue)] transition hover:brightness-110"
                                    >
                                        Iniciar sesión
                                        <ArrowRight className="size-4 transition group-hover:translate-x-0.5" />
                                    </Link>
                                )}

                                <Link
                                    href={proyeccion.index()}
                                    className="inline-flex items-center gap-2 rounded-xl border border-[var(--rl-border)] px-6 py-3 font-semibold text-[var(--rl-fg)] transition hover:border-[var(--rl-cyan)] hover:bg-white/[0.03]"
                                >
                                    <span className="rl-pulse inline-block size-2 rounded-full bg-red-500" />
                                    Ver proyección en vivo
                                </Link>
                            </div>
                        </div>

                        {/* Visual: bracket eléctrico */}
                        <div className="rl-up rl-d3 relative hidden lg:block">
                            <div className="rounded-2xl border border-[var(--rl-border)] bg-[var(--rl-panel)]/40 p-6 backdrop-blur-sm">
                                <div className="mb-4 flex items-center justify-between">
                                    <span className="font-display text-sm font-semibold tracking-wide text-[var(--rl-muted)]">
                                        BRACKET · FINAL
                                    </span>
                                    <Trophy className="size-4 text-[var(--rl-cyan)]" />
                                </div>
                                <BracketArt />
                            </div>
                        </div>
                    </main>

                    {/* Tira de características */}
                    <section className="rl-up rl-d5 grid gap-3 pb-12 sm:grid-cols-2 lg:grid-cols-3">
                        {features.map(({ icon: Icon, title, desc }) => (
                            <div
                                key={title}
                                className="group rounded-xl border border-[var(--rl-border)] bg-white/[0.02] p-5 transition hover:border-[var(--rl-blue)] hover:bg-white/[0.04]"
                            >
                                <div className="mb-3 flex size-9 items-center justify-center rounded-lg bg-[var(--rl-blue)]/15 text-[var(--rl-cyan)] transition group-hover:bg-[var(--rl-blue)]/25">
                                    <Icon className="size-5" />
                                </div>
                                <h3 className="font-display font-semibold tracking-wide">
                                    {title}
                                </h3>
                                <p className="mt-1 text-sm leading-relaxed text-[var(--rl-muted)]">
                                    {desc}
                                </p>
                            </div>
                        ))}
                    </section>

                    {/* Pie */}
                    <footer className="border-t border-[var(--rl-border)] py-6 text-center text-xs text-[var(--rl-muted)]">
                        RoboLeague — Gestión integral de competencias de
                        robótica
                    </footer>
                </div>
            </div>

            <style>{`
                .rl-landing {
                    --rl-navy: oklch(0.16 0.025 265);
                    --rl-panel: oklch(0.21 0.03 264);
                    --rl-fg: oklch(0.97 0.01 250);
                    --rl-muted: oklch(0.72 0.03 255);
                    --rl-blue: oklch(0.71 0.15 257);
                    --rl-cyan: oklch(0.82 0.14 210);
                    --rl-border: oklch(0.33 0.04 262);
                }
                .rl-grid {
                    background-image:
                        linear-gradient(to right, oklch(0.97 0.01 250 / 0.04) 1px, transparent 1px),
                        linear-gradient(to bottom, oklch(0.97 0.01 250 / 0.04) 1px, transparent 1px);
                    background-size: 56px 56px;
                    mask-image: radial-gradient(ellipse 90% 70% at 50% 0%, black 40%, transparent 100%);
                    -webkit-mask-image: radial-gradient(ellipse 90% 70% at 50% 0%, black 40%, transparent 100%);
                }
                .rl-up {
                    opacity: 0;
                    animation: rl-rise 0.7s cubic-bezier(0.22, 1, 0.36, 1) forwards;
                }
                .rl-d1 { animation-delay: 0.06s; }
                .rl-d2 { animation-delay: 0.12s; }
                .rl-d3 { animation-delay: 0.2s; }
                .rl-d4 { animation-delay: 0.3s; }
                .rl-d5 { animation-delay: 0.4s; }
                @keyframes rl-rise {
                    from { opacity: 0; transform: translateY(18px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .rl-pulse { animation: rl-pulse 1.8s ease-in-out infinite; }
                @keyframes rl-pulse {
                    0%, 100% { opacity: 1; transform: scale(1); }
                    50% { opacity: 0.45; transform: scale(0.8); }
                }
                .rl-champ { animation: rl-glow 2.4s ease-in-out infinite; }
                @keyframes rl-glow {
                    0%, 100% { filter: drop-shadow(0 0 4px var(--rl-cyan)); }
                    50% { filter: drop-shadow(0 0 14px var(--rl-cyan)); }
                }
                @media (prefers-reduced-motion: reduce) {
                    .rl-up { opacity: 1; animation: none; }
                    .rl-pulse, .rl-champ { animation: none; }
                }
            `}</style>
        </>
    );
}

function BracketArt() {
    const node =
        'fill-[var(--rl-navy)] stroke-[var(--rl-border)]';
    return (
        <svg
            viewBox="0 0 420 240"
            className="h-auto w-full"
            fill="none"
            strokeWidth="1.5"
        >
            {/* Conectores */}
            <path
                d="M120 28 H150 V64 H180 M120 100 H150 V64 M120 140 H150 V176 H180 M120 212 H150 V176 M290 64 H310 V120 H340 M290 176 H310 V120"
                className="stroke-[var(--rl-blue)]/50"
            />

            {/* Ronda 1 */}
            {[14, 86, 126, 198].map((y) => (
                <rect
                    key={y}
                    x="10"
                    y={y}
                    width="110"
                    height="28"
                    rx="7"
                    className={node}
                />
            ))}
            {/* Semifinales */}
            <rect x="180" y="50" width="110" height="28" rx="7" className={node} />
            <rect x="180" y="162" width="110" height="28" rx="7" className={node} />
            {/* Campeón */}
            <rect
                x="340"
                y="106"
                width="72"
                height="28"
                rx="7"
                className="rl-champ fill-[var(--rl-cyan)]/15 stroke-[var(--rl-cyan)]"
            />

            {/* Barras internas que simulan nombres de robots */}
            {[
                [14, 70],
                [86, 52],
                [126, 64],
                [198, 58],
            ].map(([y, w]) => (
                <rect
                    key={`b${y}`}
                    x="22"
                    y={y + 11}
                    width={w}
                    height="6"
                    rx="3"
                    className="fill-[var(--rl-muted)]/40 stroke-none"
                />
            ))}
            <rect x="192" y="61" width="64" height="6" rx="3" className="fill-[var(--rl-blue)]/70 stroke-none" />
            <rect x="192" y="173" width="58" height="6" rx="3" className="fill-[var(--rl-blue)]/70 stroke-none" />
            {/* Trofeo del campeón */}
            <circle cx="358" cy="120" r="5" className="fill-[var(--rl-cyan)] stroke-none" />
            <rect x="372" y="117" width="30" height="6" rx="3" className="fill-[var(--rl-cyan)]/80 stroke-none" />
        </svg>
    );
}
