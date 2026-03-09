"use client";

import { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import { useSession } from "next-auth/react";
import Link from "next/link";

export default function JoinBarPage() {
  const params = useParams();
  const router = useRouter();
  const { data: session, status } = useSession();
  const [message, setMessage] = useState("");
  const [joining, setJoining] = useState(false);

  const slug = params.slug as string;

  useEffect(() => {
    if (status === "authenticated" && !joining) {
      setJoining(true);
      fetch(`/api/bars/${slug}/join`, { method: "POST" })
        .then((r) => r.json())
        .then((data) => {
          setMessage(data.message);
          setTimeout(() => router.push(`/bars/${slug}`), 1500);
        })
        .catch(() => setMessage("Erreur lors de la connexion"));
    }
  }, [status, slug, router, joining]);

  if (status === "unauthenticated") {
    return (
      <div className="flex flex-col items-center gap-4 py-20">
        <h1 className="text-2xl font-bold">Rejoindre le bar</h1>
        <p className="text-muted">Connectez-vous pour rejoindre ce bar</p>
        <Link
          href={`/login?callbackUrl=/bars/${slug}/join`}
          className="rounded-lg bg-primary px-6 py-3 font-bold text-black transition hover:bg-primary-hover"
        >
          Se connecter
        </Link>
      </div>
    );
  }

  return (
    <div className="flex flex-col items-center gap-4 py-20">
      {message ? (
        <>
          <div className="text-4xl">&#127881;</div>
          <h1 className="text-2xl font-bold">{message}</h1>
          <p className="text-muted">Redirection en cours...</p>
        </>
      ) : (
        <>
          <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
          <p className="text-muted">Connexion au bar...</p>
        </>
      )}
    </div>
  );
}
