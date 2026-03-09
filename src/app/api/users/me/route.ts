import { NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { getSession, unauthorized } from "@/lib/api-utils";

export async function GET() {
  const session = await getSession();
  if (!session) return unauthorized();

  const user = await prisma.user.findUnique({
    where: { id: session.user.id },
    select: {
      id: true,
      name: true,
      email: true,
      role: true,
      totalPoints: true,
      image: true,
      createdAt: true,
      barMemberships: {
        include: {
          bar: { select: { id: true, name: true, slug: true } },
        },
      },
      predictions: {
        orderBy: { createdAt: "desc" },
        take: 20,
        include: {
          event: { select: { id: true, title: true } },
          outcome: { select: { label: true } },
        },
      },
      badges: {
        include: { badge: true },
      },
    },
  });

  if (!user) return unauthorized();
  return NextResponse.json(user);
}
