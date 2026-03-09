import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { notFound } from "@/lib/api-utils";

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ slug: string }> }
) {
  const { slug } = await params;
  const bar = await prisma.bar.findUnique({
    where: { slug },
    include: {
      admin: { select: { id: true, name: true } },
      _count: { select: { members: true, events: true } },
      events: {
        where: { status: { in: ["OPEN", "CLOSED"] } },
        orderBy: { startTime: "desc" },
        take: 10,
        include: { outcomes: true, _count: { select: { predictions: true } } },
      },
    },
  });

  if (!bar) return notFound("Bar introuvable");
  return NextResponse.json(bar);
}
