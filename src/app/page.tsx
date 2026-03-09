"use client";

import Link from "next/link";
import { useSession } from "next-auth/react";

export default function Home() {
  const { data: session } = useSession();

  return (
    <div className="flex flex-col items-center gap-8 py-12 text-center">
      <div className="space-y-4">
        <h1 className="text-5xl font-extrabold tracking-tight">
          <span className="text-primary">Bar</span>Bet
        </h1>
        <p className="text-xl text-muted max-w-lg mx-auto">
          Pronostics sportifs en bar. Misez vos jetons virtuels, grimpez le
          classement, gagnez des récompenses.
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-3 w-full max-w-2xl">
        <div className="rounded-xl border border-border bg-card p-6">
          <div className="text-3xl mb-2">&#127942;</div>
          <h3 className="font-bold text-lg">Pronostiquez</h3>
          <p className="text-sm text-muted mt-1">
            Choisissez votre favori et misez vos jetons
          </p>
        </div>
        <div className="rounded-xl border border-border bg-card p-6">
          <div className="text-3xl mb-2">&#127941;</div>
          <h3 className="font-bold text-lg">Gagnez</h3>
          <p className="text-sm text-muted mt-1">
            Accumulez des points et montez au classement
          </p>
        </div>
        <div className="rounded-xl border border-border bg-card p-6">
          <div className="text-3xl mb-2">&#127866;</div>
          <h3 className="font-bold text-lg">Récompenses</h3>
          <p className="text-sm text-muted mt-1">
            Badges, boissons offertes, réductions exclusives
          </p>
        </div>
      </div>

      <div className="flex gap-4">
        {session ? (
          <Link
            href="/bars"
            className="rounded-xl bg-primary px-8 py-4 text-lg font-bold text-black transition hover:bg-primary-hover"
          >
            Voir les bars
          </Link>
        ) : (
          <>
            <Link
              href="/register"
              className="rounded-xl bg-primary px-8 py-4 text-lg font-bold text-black transition hover:bg-primary-hover"
            >
              Commencer
            </Link>
            <Link
              href="/login"
              className="rounded-xl border border-border bg-card px-8 py-4 text-lg font-bold transition hover:bg-card-hover"
            >
              Connexion
            </Link>
          </>
        )}
      </div>

      <p className="text-xs text-muted max-w-md">
        Les jetons sont 100% virtuels et ne peuvent être convertis en argent.
        BarBet est un système de divertissement pour les bars.
      </p>
    </div>
  );
}
