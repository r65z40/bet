"use client";

import { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import { useSession } from "next-auth/react";
import Link from "next/link";

interface Outcome {
  id: string;
  label: string;
  odds: number;
  _count: { predictions: number };
}

interface Event {
  id: string;
  title: string;
  description: string | null;
  status: string;
  startTime: string;
  tokenAllowance: number;
  outcomes: Outcome[];
  _count: { predictions: number };
}

interface BarData {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  qrCode: string | null;
  address: string | null;
  adminId: string;
  _count: { members: number; events: number };
  events: Event[];
}

export default function BarPage() {
  const params = useParams();
  const router = useRouter();
  const { data: session } = useSession();
  const [bar, setBar] = useState<BarData | null>(null);
  const [loading, setLoading] = useState(true);
  const [joining, setJoining] = useState(false);
  const [showQR, setShowQR] = useState(false);

  const slug = params.slug as string;

  useEffect(() => {
    fetch(`/api/bars/${slug}`)
      .then((r) => r.json())
      .then(setBar)
      .finally(() => setLoading(false));
  }, [slug]);

  async function handleJoin() {
    setJoining(true);
    await fetch(`/api/bars/${slug}/join`, { method: "POST" });
    setJoining(false);
    router.refresh();
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
      </div>
    );
  }

  if (!bar) {
    return <p className="text-center py-20 text-muted">Bar introuvable</p>;
  }

  const isAdmin = session?.user?.id === bar.adminId;

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-3xl font-bold">{bar.name}</h1>
          {bar.description && (
            <p className="mt-1 text-muted">{bar.description}</p>
          )}
          {bar.address && (
            <p className="mt-1 text-sm text-muted">{bar.address}</p>
          )}
          <div className="mt-2 flex gap-4 text-sm text-muted">
            <span>{bar._count.members} membres</span>
            <span>{bar._count.events} événements</span>
          </div>
        </div>
        <div className="flex gap-2">
          {bar.qrCode && (
            <button
              onClick={() => setShowQR(!showQR)}
              className="rounded-lg border border-border bg-card px-3 py-2 text-sm transition hover:bg-card-hover"
            >
              QR Code
            </button>
          )}
          {session && !isAdmin && (
            <button
              onClick={handleJoin}
              disabled={joining}
              className="rounded-lg bg-primary px-4 py-2 font-semibold text-black transition hover:bg-primary-hover disabled:opacity-50"
            >
              {joining ? "..." : "Rejoindre"}
            </button>
          )}
        </div>
      </div>

      {showQR && bar.qrCode && (
        <div className="flex justify-center rounded-xl border border-border bg-card p-6">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={bar.qrCode} alt="QR Code" className="w-64 h-64" />
        </div>
      )}

      <div className="flex gap-4">
        <Link
          href={`/bars/${slug}/leaderboard`}
          className="rounded-lg border border-border bg-card px-4 py-2 text-sm transition hover:bg-card-hover"
        >
          Classement
        </Link>
        {isAdmin && (
          <Link
            href={`/admin/bars/${slug}`}
            className="rounded-lg border border-primary/30 bg-card px-4 py-2 text-sm text-primary transition hover:bg-card-hover"
          >
            Gérer
          </Link>
        )}
      </div>

      <h2 className="text-xl font-bold mt-8">Événements</h2>

      {bar.events.length === 0 ? (
        <p className="text-muted text-center py-8">Aucun événement pour le moment</p>
      ) : (
        <div className="space-y-4">
          {bar.events.map((event) => (
            <Link
              key={event.id}
              href={`/events/${event.id}`}
              className="block rounded-xl border border-border bg-card p-6 transition hover:bg-card-hover hover:border-primary/30"
            >
              <div className="flex items-center justify-between">
                <h3 className="text-lg font-bold">{event.title}</h3>
                <span
                  className={`rounded-full px-3 py-1 text-xs font-semibold ${
                    event.status === "OPEN"
                      ? "bg-success/20 text-success"
                      : event.status === "CLOSED"
                        ? "bg-primary/20 text-primary"
                        : "bg-muted/20 text-muted"
                  }`}
                >
                  {event.status === "OPEN"
                    ? "Ouvert"
                    : event.status === "CLOSED"
                      ? "Fermé"
                      : event.status}
                </span>
              </div>
              {event.description && (
                <p className="mt-1 text-sm text-muted">{event.description}</p>
              )}
              <div className="mt-3 flex flex-wrap gap-2">
                {event.outcomes.map((o) => (
                  <span
                    key={o.id}
                    className="rounded-lg border border-border px-3 py-1 text-sm"
                  >
                    {o.label}{" "}
                    <span className="text-primary font-semibold">x{o.odds}</span>
                  </span>
                ))}
              </div>
              <p className="mt-2 text-xs text-muted">
                {event._count.predictions} pronostics | Max {event.tokenAllowance} jetons
              </p>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
