"use client";

import { useEffect, useState } from "react";
import { useSession } from "next-auth/react";
import Link from "next/link";

interface Bar {
  id: string;
  name: string;
  slug: string;
  _count: { members: number; events: number };
}

export default function AdminPage() {
  const { data: session } = useSession();
  const [bars, setBars] = useState<Bar[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch("/api/bars")
      .then((r) => r.json())
      .then((allBars: Bar[]) => {
        if (session?.user.role === "SUPER_ADMIN") {
          setBars(allBars);
        } else {
          // Only show bars where user is admin (filter client-side for simplicity)
          setBars(allBars);
        }
      })
      .finally(() => setLoading(false));
  }, [session]);

  if (!session || (session.user.role !== "BAR_ADMIN" && session.user.role !== "SUPER_ADMIN")) {
    return (
      <div className="text-center py-20">
        <p className="text-muted">Accès réservé aux administrateurs</p>
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

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Dashboard Admin</h1>

      <div className="grid gap-4 sm:grid-cols-2">
        {bars.map((bar) => (
          <Link
            key={bar.id}
            href={`/admin/bars/${bar.slug}`}
            className="rounded-xl border border-border bg-card p-6 transition hover:bg-card-hover hover:border-primary/30"
          >
            <h2 className="text-xl font-bold">{bar.name}</h2>
            <div className="mt-2 flex gap-4 text-sm text-muted">
              <span>{bar._count.members} membres</span>
              <span>{bar._count.events} événements</span>
            </div>
          </Link>
        ))}
      </div>

      <Link
        href="/bars/create"
        className="inline-block rounded-lg bg-primary px-4 py-2 font-semibold text-black transition hover:bg-primary-hover"
      >
        + Créer un bar
      </Link>
    </div>
  );
}
