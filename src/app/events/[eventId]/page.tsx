"use client";

import { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import { useSession } from "next-auth/react";
import Link from "next/link";

interface Outcome {
  id: string;
  label: string;
  odds: number;
  isCorrect: boolean;
  _count: { predictions: number };
}

interface EventData {
  id: string;
  title: string;
  description: string | null;
  status: string;
  startTime: string;
  tokenAllowance: number;
  bar: { id: string; name: string; slug: string };
  outcomes: Outcome[];
  _count: { predictions: number };
}

export default function EventPage() {
  const params = useParams();
  const router = useRouter();
  const { data: session } = useSession();
  const [event, setEvent] = useState<EventData | null>(null);
  const [selectedOutcome, setSelectedOutcome] = useState("");
  const [tokens, setTokens] = useState(10);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  const eventId = params.eventId as string;

  useEffect(() => {
    fetch(`/api/events/${eventId}`)
      .then((r) => r.json())
      .then(setEvent)
      .finally(() => setLoading(false));
  }, [eventId]);

  async function handlePredict(e: React.FormEvent) {
    e.preventDefault();
    if (!selectedOutcome) return;

    setSubmitting(true);
    setError("");

    const res = await fetch(`/api/events/${eventId}/predict`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ outcomeId: selectedOutcome, tokens }),
    });

    const data = await res.json();

    if (!res.ok) {
      setError(data.error);
    } else {
      setSuccess("Pronostic enregistré !");
      setTimeout(() => router.refresh(), 1000);
    }
    setSubmitting(false);
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
      </div>
    );
  }

  if (!event) {
    return <p className="text-center py-20 text-muted">Événement introuvable</p>;
  }

  const selected = event.outcomes.find((o) => o.id === selectedOutcome);

  return (
    <div className="space-y-6">
      <Link
        href={`/bars/${event.bar.slug}`}
        className="text-sm text-muted hover:text-primary transition"
      >
        &larr; {event.bar.name}
      </Link>

      <div>
        <div className="flex items-center justify-between">
          <h1 className="text-3xl font-bold">{event.title}</h1>
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
              : event.status === "CLOSED"
                ? "Fermé"
                : event.status === "FINISHED"
                  ? "Terminé"
                  : event.status}
          </span>
        </div>
        {event.description && (
          <p className="mt-2 text-muted">{event.description}</p>
        )}
        <p className="mt-1 text-sm text-muted">
          {new Date(event.startTime).toLocaleDateString("fr-FR", {
            weekday: "long",
            day: "numeric",
            month: "long",
            hour: "2-digit",
            minute: "2-digit",
          })}
        </p>
      </div>

      <div className="space-y-3">
        <h2 className="text-xl font-bold">Options</h2>
        {event.outcomes.map((outcome) => (
          <button
            key={outcome.id}
            onClick={() =>
              event.status === "OPEN" && setSelectedOutcome(outcome.id)
            }
            disabled={event.status !== "OPEN"}
            className={`w-full rounded-xl border p-5 text-left transition ${
              outcome.isCorrect
                ? "border-success bg-success/10"
                : selectedOutcome === outcome.id
                  ? "border-primary bg-primary/10"
                  : "border-border bg-card hover:bg-card-hover"
            } ${event.status !== "OPEN" ? "cursor-default" : "cursor-pointer"}`}
          >
            <div className="flex items-center justify-between">
              <span className="text-lg font-semibold">{outcome.label}</span>
              <span className="text-xl font-bold text-primary">
                x{outcome.odds}
              </span>
            </div>
            <p className="mt-1 text-sm text-muted">
              {outcome._count.predictions} pronostic(s)
              {outcome.isCorrect && (
                <span className="ml-2 text-success font-semibold">
                  Résultat gagnant
                </span>
              )}
            </p>
          </button>
        ))}
      </div>

      {event.status === "OPEN" && session && (
        <form
          onSubmit={handlePredict}
          className="rounded-xl border border-border bg-card p-6 space-y-4"
        >
          <h3 className="text-lg font-bold">Placer un pronostic</h3>

          {error && (
            <p className="rounded-lg bg-danger/10 px-4 py-2 text-sm text-danger">
              {error}
            </p>
          )}
          {success && (
            <p className="rounded-lg bg-success/10 px-4 py-2 text-sm text-success">
              {success}
            </p>
          )}

          <div className="space-y-2">
            <label className="text-sm text-muted">
              Jetons à miser (max {event.tokenAllowance})
            </label>
            <input
              type="range"
              min={1}
              max={event.tokenAllowance}
              value={tokens}
              onChange={(e) => setTokens(Number(e.target.value))}
              className="w-full accent-primary"
            />
            <div className="flex justify-between text-sm">
              <span>{tokens} jetons</span>
              {selected && (
                <span className="text-primary">
                  Gain potentiel: {Math.floor(tokens * selected.odds)} pts
                </span>
              )}
            </div>
          </div>

          <button
            type="submit"
            disabled={!selectedOutcome || submitting}
            className="w-full rounded-lg bg-primary py-4 text-lg font-bold text-black transition hover:bg-primary-hover disabled:opacity-50"
          >
            {submitting
              ? "Envoi..."
              : selectedOutcome
                ? `Miser ${tokens} jetons sur "${selected?.label}"`
                : "Sélectionnez une option"}
          </button>
        </form>
      )}

      {event.status === "OPEN" && !session && (
        <div className="rounded-xl border border-border bg-card p-6 text-center">
          <p className="text-muted mb-4">Connectez-vous pour pronostiquer</p>
          <Link
            href="/login"
            className="rounded-lg bg-primary px-6 py-3 font-bold text-black transition hover:bg-primary-hover"
          >
            Se connecter
          </Link>
        </div>
      )}
    </div>
  );
}
