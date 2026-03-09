"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useSession } from "next-auth/react";

interface Bar {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  _count: { members: number; events: number };
}

export default function BarsPage() {
  const { data: session } = useSession();
  const [bars, setBars] = useState<Bar[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch("/api/bars")
      .then((r) => r.json())
      .then(setBars)
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Bars</h1>
        {session && (
          <Link
            href="/bars/create"
            className="rounded-lg bg-primary px-4 py-2 font-semibold text-black transition hover:bg-primary-hover"
          >
            + Créer un bar
          </Link>
        )}
      </div>

      {bars.length === 0 ? (
        <p className="text-center text-muted py-12">
          Aucun bar pour le moment. Soyez le premier !
        </p>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2">
          {bars.map((bar) => (
            <Link
              key={bar.id}
              href={`/bars/${bar.slug}`}
              className="rounded-xl border border-border bg-card p-6 transition hover:bg-card-hover hover:border-primary/30"
            >
              <h2 className="text-xl font-bold">{bar.name}</h2>
              {bar.description && (
                <p className="mt-1 text-sm text-muted line-clamp-2">
                  {bar.description}
                </p>
              )}
              <div className="mt-4 flex gap-4 text-sm text-muted">
                <span>{bar._count.members} membres</span>
                <span>{bar._count.events} événements</span>
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
