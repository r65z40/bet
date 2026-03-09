"use client";

import { useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { useSession } from "next-auth/react";

interface Stats {
  totalMembers: number;
  totalEvents: number;
  totalPredictions: number;
  activeEvents: number;
}

interface Outcome {
  id: string;
  label: string;
  odds: number;
  isCorrect: boolean;
}

interface Event {
  id: string;
  title: string;
  status: string;
  startTime: string;
  tokenAllowance: number;
  outcomes: Outcome[];
  _count: { predictions: number };
}

export default function AdminBarPage() {
  const params = useParams();
  const { data: session } = useSession();
  const slug = params.slug as string;

  const [stats, setStats] = useState<Stats | null>(null);
  const [events, setEvents] = useState<Event[]>([]);
  const [loading, setLoading] = useState(true);

  // New event form
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [startTime, setStartTime] = useState("");
  const [tokenAllowance, setTokenAllowance] = useState(100);
  const [outcomes, setOutcomes] = useState([
    { label: "", odds: 2.0 },
    { label: "", odds: 2.0 },
  ]);
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState("");

  // Resolve
  const [resolvingId, setResolvingId] = useState("");

  useEffect(() => {
    Promise.all([
      fetch(`/api/bars/${slug}/stats`).then((r) => r.json()),
      fetch(`/api/bars/${slug}/events`).then((r) => r.json()),
    ])
      .then(([s, e]) => {
        setStats(s);
        setEvents(e);
      })
      .finally(() => setLoading(false));
  }, [slug]);

  async function createEvent(e: React.FormEvent) {
    e.preventDefault();
    setCreating(true);
    setError("");

    const res = await fetch(`/api/bars/${slug}/events`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        title,
        description,
        startTime,
        tokenAllowance,
        outcomes: outcomes.filter((o) => o.label.trim()),
      }),
    });

    const data = await res.json();
    if (!res.ok) {
      setError(data.error);
    } else {
      setEvents([data, ...events]);
      setTitle("");
      setDescription("");
      setStartTime("");
      setOutcomes([
        { label: "", odds: 2.0 },
        { label: "", odds: 2.0 },
      ]);
    }
    setCreating(false);
  }

  async function resolveEvent(eventId: string, winningOutcomeId: string) {
    setResolvingId(eventId);
    const res = await fetch(`/api/events/${eventId}/resolve`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ winningOutcomeId }),
    });

    if (res.ok) {
      const updated = await res.json();
      setEvents(events.map((ev) => (ev.id === eventId ? updated : ev)));
    }
    setResolvingId("");
  }

  if (!session) return null;

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
      </div>
    );
  }

  return (
    <div className="space-y-8">
      <h1 className="text-2xl font-bold">Admin - {slug}</h1>

      {stats && (
        <div className="grid gap-4 grid-cols-2 sm:grid-cols-4">
          {[
            { label: "Membres", value: stats.totalMembers },
            { label: "Événements", value: stats.totalEvents },
            { label: "Pronostics", value: stats.totalPredictions },
            { label: "En cours", value: stats.activeEvents },
          ].map((s) => (
            <div
              key={s.label}
              className="rounded-xl border border-border bg-card p-4 text-center"
            >
              <p className="text-2xl font-bold text-primary">{s.value}</p>
              <p className="text-sm text-muted">{s.label}</p>
            </div>
          ))}
        </div>
      )}

      <form
        onSubmit={createEvent}
        className="rounded-xl border border-border bg-card p-6 space-y-4"
      >
        <h2 className="text-xl font-bold">Créer un événement</h2>

        {error && (
          <p className="rounded-lg bg-danger/10 px-4 py-2 text-sm text-danger">
            {error}
          </p>
        )}

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <label className="text-sm text-muted">Titre</label>
            <input
              type="text"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              required
              className="w-full rounded-lg border border-border bg-background px-4 py-3 text-foreground focus:border-primary focus:outline-none"
              placeholder="PSG vs OM"
            />
          </div>
          <div className="space-y-2">
            <label className="text-sm text-muted">Date & Heure</label>
            <input
              type="datetime-local"
              value={startTime}
              onChange={(e) => setStartTime(e.target.value)}
              required
              className="w-full rounded-lg border border-border bg-background px-4 py-3 text-foreground focus:border-primary focus:outline-none"
            />
          </div>
        </div>

        <div className="space-y-2">
          <label className="text-sm text-muted">Description (optionnel)</label>
          <input
            type="text"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            className="w-full rounded-lg border border-border bg-background px-4 py-3 text-foreground focus:border-primary focus:outline-none"
            placeholder="Ligue 1 - Journée 28"
          />
        </div>

        <div className="space-y-2">
          <label className="text-sm text-muted">Jetons max par mise</label>
          <input
            type="number"
            value={tokenAllowance}
            onChange={(e) => setTokenAllowance(Number(e.target.value))}
            min={1}
            className="w-32 rounded-lg border border-border bg-background px-4 py-3 text-foreground focus:border-primary focus:outline-none"
          />
        </div>

        <div className="space-y-2">
          <label className="text-sm text-muted">Options de pronostic</label>
          {outcomes.map((o, i) => (
            <div key={i} className="flex gap-2">
              <input
                type="text"
                value={o.label}
                onChange={(e) => {
                  const next = [...outcomes];
                  next[i] = { ...next[i], label: e.target.value };
                  setOutcomes(next);
                }}
                className="flex-1 rounded-lg border border-border bg-background px-4 py-3 text-foreground focus:border-primary focus:outline-none"
                placeholder={`Option ${i + 1}`}
              />
              <input
                type="number"
                value={o.odds}
                onChange={(e) => {
                  const next = [...outcomes];
                  next[i] = { ...next[i], odds: parseFloat(e.target.value) || 1 };
                  setOutcomes(next);
                }}
                step={0.1}
                min={1}
                className="w-20 rounded-lg border border-border bg-background px-3 py-3 text-foreground focus:border-primary focus:outline-none"
              />
            </div>
          ))}
          <button
            type="button"
            onClick={() => setOutcomes([...outcomes, { label: "", odds: 2.0 }])}
            className="text-sm text-primary hover:underline"
          >
            + Ajouter une option
          </button>
        </div>

        <button
          type="submit"
          disabled={creating}
          className="w-full rounded-lg bg-primary py-4 text-lg font-bold text-black transition hover:bg-primary-hover disabled:opacity-50"
        >
          {creating ? "Création..." : "Créer l'événement"}
        </button>
      </form>

      <div className="space-y-4">
        <h2 className="text-xl font-bold">Événements</h2>
        {events.map((event) => (
          <div
            key={event.id}
            className="rounded-xl border border-border bg-card p-6 space-y-3"
          >
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-bold">{event.title}</h3>
              <span
                className={`rounded-full px-3 py-1 text-xs font-semibold ${
                  event.status === "OPEN"
                    ? "bg-success/20 text-success"
                    : event.status === "FINISHED"
                      ? "bg-primary/20 text-primary"
                      : "bg-muted/20 text-muted"
                }`}
              >
                {event.status === "OPEN"
                  ? "Ouvert"
                  : event.status === "FINISHED"
                    ? "Terminé"
                    : event.status}
              </span>
            </div>

            <p className="text-sm text-muted">
              {event._count.predictions} pronostic(s)
            </p>

            {(event.status === "OPEN" || event.status === "CLOSED") && (
              <div className="space-y-2">
                <p className="text-sm font-semibold">Résoudre - Choisir le gagnant :</p>
                <div className="flex flex-wrap gap-2">
                  {event.outcomes.map((o) => (
                    <button
                      key={o.id}
                      onClick={() => resolveEvent(event.id, o.id)}
                      disabled={resolvingId === event.id}
                      className="rounded-lg border border-border bg-background px-4 py-2 text-sm transition hover:border-success hover:bg-success/10 disabled:opacity-50"
                    >
                      {o.label}
                    </button>
                  ))}
                </div>
              </div>
            )}

            {event.status === "FINISHED" && (
              <div className="flex flex-wrap gap-2">
                {event.outcomes.map((o) => (
                  <span
                    key={o.id}
                    className={`rounded-lg px-3 py-1 text-sm ${
                      o.isCorrect
                        ? "bg-success/20 text-success font-semibold"
                        : "bg-card text-muted"
                    }`}
                  >
                    {o.label} {o.isCorrect && "- Gagnant"}
                  </span>
                ))}
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
