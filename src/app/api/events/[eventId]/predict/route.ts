import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { getSession, unauthorized, badRequest, notFound } from "@/lib/api-utils";

export async function POST(
  req: NextRequest,
  { params }: { params: Promise<{ eventId: string }> }
) {
  const session = await getSession();
  if (!session) return unauthorized();

  const { eventId } = await params;
  const { outcomeId, tokens } = await req.json();

  if (!outcomeId || !tokens || tokens <= 0) {
    return badRequest("Option et mise requis");
  }

  const event = await prisma.event.findUnique({
    where: { id: eventId },
    include: { outcomes: true, bar: true },
  });

  if (!event) return notFound("Événement introuvable");
  if (event.status !== "OPEN") return badRequest("Les pronostics sont fermés");
  if (tokens > event.tokenAllowance) {
    return badRequest(`Mise max: ${event.tokenAllowance} jetons`);
  }

  const outcome = event.outcomes.find((o: { id: string }) => o.id === outcomeId);
  if (!outcome) return badRequest("Option invalide");

  // Check if user is a member
  const member = await prisma.barMember.findUnique({
    where: {
      userId_barId: { userId: session.user.id, barId: event.barId },
    },
  });
  if (!member) return badRequest("Rejoignez d'abord le bar");

  // Check if already predicted
  const existing = await prisma.prediction.findUnique({
    where: {
      userId_eventId: { userId: session.user.id, eventId },
    },
  });
  if (existing) return badRequest("Vous avez déjà pronostiqué");

  const prediction = await prisma.prediction.create({
    data: {
      userId: session.user.id,
      eventId,
      outcomeId,
      tokens,
    },
    include: { outcome: true },
  });

  return NextResponse.json(prediction, { status: 201 });
}
