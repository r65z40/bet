"use client";

import { useEffect, useState } from "react";
import { useParams } from "next/navigation";

interface LeaderEntry {
  rank: number;
  userId: string;
  name: string | null;
  image: string | null;
  points: number;
}

export default function BarLeaderboardPage() {
  const params = useParams();
  const [entries, setEntries] = useState<LeaderEntry[]>([]);
  const [loading, setLoading] = useState(true);

  const slug = params.slug as string;

  useEffect(() => {
    fetch(`/api/bars/${slug}/leaderboard`)
      .then((r) => r.json())
      .then(setEntries)
      .finally(() => setLoading(false));
  }, [slug]);

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Classement</h1>

      {entries.length === 0 ? (
        <p className="text-center py-12 text-muted">Pas encore de classement</p>
      ) : (
        <div className="space-y-2">
          {entries.map((entry) => (
            <div
              key={entry.userId}
              className={`flex items-center gap-4 rounded-xl border border-border bg-card p-4 ${
                entry.rank <= 3 ? "border-primary/30" : ""
              }`}
            >
              <span
                className={`flex h-10 w-10 items-center justify-center rounded-full font-bold ${
                  entry.rank === 1
                    ? "bg-yellow-500/20 text-yellow-500"
                    : entry.rank === 2
                      ? "bg-gray-300/20 text-gray-300"
                      : entry.rank === 3
                        ? "bg-orange-600/20 text-orange-600"
                        : "bg-card text-muted"
                }`}
              >
                {entry.rank}
              </span>
              <div className="flex-1">
                <p className="font-semibold">{entry.name || "Anonyme"}</p>
              </div>
              <span className="text-lg font-bold text-primary">
                {entry.points} pts
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
