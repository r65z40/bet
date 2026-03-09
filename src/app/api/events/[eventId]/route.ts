import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { notFound } from "@/lib/api-utils";

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ eventId: string }> }
) {
  const { eventId } = await params;
  const event = await prisma.event.findUnique({
    where: { id: eventId },
    include: {
      bar: { select: { id: true, name: true, slug: true } },
      outcomes: {
        include: { _count: { select: { predictions: true } } },
      },
      _count: { select: { predictions: true } },
    },
  });

  if (!event) return notFound("Événement introuvable");
  return NextResponse.json(event);
}
