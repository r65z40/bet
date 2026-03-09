"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";

export default function CreateBarPage() {
  const router = useRouter();
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [address, setAddress] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError("");

    const res = await fetch("/api/bars", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name, description, address }),
    });

    const data = await res.json();

    if (!res.ok) {
      setError(data.error);
      setLoading(false);
      return;
    }

    router.push(`/bars/${data.slug}`);
  }

  return (
    <div className="flex min-h-[60vh] items-center justify-center">
      <form
        onSubmit={handleSubmit}
        className="w-full max-w-md space-y-6 rounded-xl border border-border bg-card p-8"
      >
        <h1 className="text-2xl font-bold text-center">Créer un bar</h1>

        {error && (
          <p className="rounded-lg bg-danger/10 px-4 py-2 text-sm text-danger">
            {error}
          </p>
        )}

        <div className="space-y-2">
          <label className="text-sm text-muted">Nom du bar</label>
          <input
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            required
            className="w-full rounded-lg border border-border bg-background px-4 py-3 text-foreground focus:border-primary focus:outline-none"
            placeholder="Le Penalty"
          />
        </div>

        <div className="space-y-2">
          <label className="text-sm text-muted">Description</label>
          <textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            rows={3}
            className="w-full rounded-lg border border-border bg-background px-4 py-3 text-foreground focus:border-primary focus:outline-none resize-none"
            placeholder="Bar sportif au coeur de la ville..."
          />
        </div>

        <div className="space-y-2">
          <label className="text-sm text-muted">Adresse</label>
          <input
            type="text"
            value={address}
            onChange={(e) => setAddress(e.target.value)}
            className="w-full rounded-lg border border-border bg-background px-4 py-3 text-foreground focus:border-primary focus:outline-none"
            placeholder="12 rue du Stade, Paris"
          />
        </div>

        <button
          type="submit"
          disabled={loading}
          className="w-full rounded-lg bg-primary py-4 text-lg font-bold text-black transition hover:bg-primary-hover disabled:opacity-50"
        >
          {loading ? "Création..." : "Créer le bar"}
        </button>
      </form>
    </div>
  );
}
