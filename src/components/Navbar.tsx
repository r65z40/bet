"use client";

import Link from "next/link";
import { useSession, signOut } from "next-auth/react";
import { useState } from "react";

export default function Navbar() {
  const { data: session } = useSession();
  const [menuOpen, setMenuOpen] = useState(false);

  return (
    <nav className="sticky top-0 z-50 border-b border-border bg-background/95 backdrop-blur">
      <div className="mx-auto flex h-16 max-w-5xl items-center justify-between px-4">
        <Link href="/" className="text-xl font-bold text-primary">
          BarBet
        </Link>

        {session ? (
          <div className="relative">
            <button
              onClick={() => setMenuOpen(!menuOpen)}
              className="flex items-center gap-2 rounded-lg bg-card px-3 py-2 text-sm transition hover:bg-card-hover"
            >
              <span className="h-7 w-7 rounded-full bg-primary/20 flex items-center justify-center text-primary font-bold text-xs">
                {session.user.name?.[0]?.toUpperCase() || "U"}
              </span>
              <span className="hidden sm:inline">{session.user.name}</span>
            </button>

            {menuOpen && (
              <div className="absolute right-0 top-12 w-48 rounded-lg border border-border bg-card shadow-lg">
                <Link
                  href="/profile"
                  onClick={() => setMenuOpen(false)}
                  className="block px-4 py-3 text-sm hover:bg-card-hover transition"
                >
                  Mon profil
                </Link>
                <Link
                  href="/bars"
                  onClick={() => setMenuOpen(false)}
                  className="block px-4 py-3 text-sm hover:bg-card-hover transition"
                >
                  Mes bars
                </Link>
                <Link
                  href="/leaderboard"
                  onClick={() => setMenuOpen(false)}
                  className="block px-4 py-3 text-sm hover:bg-card-hover transition"
                >
                  Classement
                </Link>
                {(session.user.role === "BAR_ADMIN" ||
                  session.user.role === "SUPER_ADMIN") && (
                  <Link
                    href="/admin"
                    onClick={() => setMenuOpen(false)}
                    className="block px-4 py-3 text-sm text-primary hover:bg-card-hover transition"
                  >
                    Admin
                  </Link>
                )}
                <button
                  onClick={() => signOut()}
                  className="block w-full px-4 py-3 text-left text-sm text-danger hover:bg-card-hover transition"
                >
                  Déconnexion
                </button>
              </div>
            )}
          </div>
        ) : (
          <div className="flex gap-2">
            <Link
              href="/login"
              className="rounded-lg bg-card px-4 py-2 text-sm transition hover:bg-card-hover"
            >
              Connexion
            </Link>
            <Link
              href="/register"
              className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-black transition hover:bg-primary-hover"
            >
              Inscription
            </Link>
          </div>
        )}
      </div>
    </nav>
  );
}
