import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { getSession, unauthorized, forbidden, badRequest, notFound } from "@/lib/api-utils";

export async function POST(
  req: NextRequest,
  { params }: { params: Promise<{ eventId: string }> }
) {
  const session = await getSession();
  if (!session) return unauthorized();

  const { eventId } = await params;
  const { winningOutcomeId } = await req.json();

  if (!winningOutcomeId) return badRequest("Résultat gagnant requis");

  const event = await prisma.event.findUnique({
    where: { id: eventId },
    include: { bar: true, outcomes: true },
  });

  if (!event) return notFound("Événement introuvable");

  if (event.bar.adminId !== session.user.id && session.user.role !== "SUPER_ADMIN") {
    return forbidden();
  }

  if (event.status === "FINISHED") {
    return badRequest("Événement déjà résolu");
  }

  const winOutcome = event.outcomes.find((o: { id: string }) => o.id === winningOutcomeId);
  if (!winOutcome) return badRequest("Option invalide");

  // Mark correct outcome
  await prisma.outcome.update({
    where: { id: winningOutcomeId },
    data: { isCorrect: true },
  });

  // Get all predictions for this event
  const predictions = await prisma.prediction.findMany({
    where: { eventId },
  });

  // Process each prediction
  for (const pred of predictions) {
    const isWinner = pred.outcomeId === winningOutcomeId;
    const payout = isWinner ? Math.floor(pred.tokens * winOutcome.odds) : 0;

    await prisma.prediction.update({
      where: { id: pred.id },
      data: {
        status: isWinner ? "WON" : "LOST",
        payout,
      },
    });

    if (isWinner) {
      // Update bar member points
      await prisma.barMember.update({
        where: {
          userId_barId: { userId: pred.userId, barId: event.barId },
        },
        data: { points: { increment: payout } },
      });

      // Update user total points
      await prisma.user.update({
        where: { id: pred.userId },
        data: { totalPoints: { increment: payout } },
      });
    }
  }

  // Mark event as finished
  const updated = await prisma.event.update({
    where: { id: eventId },
    data: { status: "FINISHED" },
    include: { outcomes: true },
  });

  return NextResponse.json(updated);
}
