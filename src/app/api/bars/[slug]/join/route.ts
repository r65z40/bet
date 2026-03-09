import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { getSession, unauthorized, notFound } from "@/lib/api-utils";

export async function POST(
  _req: NextRequest,
  { params }: { params: Promise<{ slug: string }> }
) {
  const session = await getSession();
  if (!session) return unauthorized();

  const { slug } = await params;
  const bar = await prisma.bar.findUnique({ where: { slug } });
  if (!bar) return notFound("Bar introuvable");

  const existing = await prisma.barMember.findUnique({
    where: { userId_barId: { userId: session.user.id, barId: bar.id } },
  });

  if (existing) {
    return NextResponse.json({ message: "Déjà membre", barId: bar.id });
  }

  await prisma.barMember.create({
    data: { userId: session.user.id, barId: bar.id },
  });

  return NextResponse.json({ message: "Bienvenue!", barId: bar.id }, { status: 201 });
}
