"use client";

import { useEffect, useState } from "react";
import { useSession } from "next-auth/react";
import Link from "next/link";

interface UserProfile {
  id: string;
  name: string;
  email: string;
  role: string;
  totalPoints: number;
  createdAt: string;
  barMemberships: {
    id: string;
    points: number;
    bar: { id: string; name: string; slug: string };
  }[];
  predictions: {
    id: string;
    tokens: number;
    status: string;
    payout: number;
    createdAt: string;
    event: { id: string; title: string };
    outcome: { label: string };
  }[];
  badges: {
    id: string;
    earnedAt: string;
    badge: { name: string; description: string; icon: string };
  }[];
}

export default function ProfilePage() {
  const { data: session } = useSession();
  const [profile, setProfile] = useState<UserProfile | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (session) {
      fetch("/api/users/me")
        .then((r) => r.json())
        .then(setProfile)
        .finally(() => setLoading(false));
    }
  }, [session]);

  if (!session) {
    return (
      <div className="text-center py-20">
        <p className="text-muted">Connectez-vous pour voir votre profil</p>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
      </div>
    );
  }

  if (!profile) return null;

  return (
    <div className="space-y-8">
      <div className="rounded-xl border border-border bg-card p-6">
        <div className="flex items-center gap-4">
          <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary/20 text-2xl font-bold text-primary">
            {profile.name?.[0]?.toUpperCase() || "U"}
          </div>
          <div>
            <h1 className="text-2xl font-bold">{profile.name}</h1>
            <p className="text-muted">{profile.email}</p>
            <p className="mt-1 text-sm">
              <span className="text-primary font-bold text-lg">
                {profile.totalPoints}
              </span>{" "}
              <span className="text-muted">points au total</span>
            </p>
          </div>
        </div>
      </div>

      {profile.badges.length > 0 && (
        <div>
          <h2 className="text-xl font-bold mb-4">Badges</h2>
          <div className="flex flex-wrap gap-3">
            {profile.badges.map((ub) => (
              <div
                key={ub.id}
                className="flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2"
              >
                <span className="text-2xl">{ub.badge.icon}</span>
                <div>
                  <p className="text-sm font-semibold">{ub.badge.name}</p>
                  <p className="text-xs text-muted">{ub.badge.description}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      <div>
        <h2 className="text-xl font-bold mb-4">Mes bars</h2>
        {profile.barMemberships.length === 0 ? (
          <p className="text-muted">Aucun bar rejoint</p>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2">
            {profile.barMemberships.map((m) => (
              <Link
                key={m.id}
                href={`/bars/${m.bar.slug}`}
                className="flex items-center justify-between rounded-xl border border-border bg-card p-4 transition hover:bg-card-hover"
              >
                <span className="font-semibold">{m.bar.name}</span>
                <span className="text-primary font-bold">{m.points} pts</span>
              </Link>
            ))}
          </div>
        )}
      </div>

      <div>
        <h2 className="text-xl font-bold mb-4">Historique des pronostics</h2>
        {profile.predictions.length === 0 ? (
          <p className="text-muted">Aucun pronostic</p>
        ) : (
          <div className="space-y-2">
            {profile.predictions.map((p) => (
              <Link
                key={p.id}
                href={`/events/${p.event.id}`}
                className="flex items-center justify-between rounded-xl border border-border bg-card p-4 transition hover:bg-card-hover"
              >
                <div>
                  <p className="font-semibold">{p.event.title}</p>
                  <p className="text-sm text-muted">
                    {p.outcome.label} | {p.tokens} jetons
                  </p>
                </div>
                <span
                  className={`rounded-full px-3 py-1 text-xs font-semibold ${
                    p.status === "WON"
                      ? "bg-success/20 text-success"
                      : p.status === "LOST"
                        ? "bg-danger/20 text-danger"
                        : "bg-muted/20 text-muted"
                  }`}
                >
                  {p.status === "WON"
                    ? `+${p.payout} pts`
                    : p.status === "LOST"
                      ? "Perdu"
                      : "En attente"}
                </span>
              </Link>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
