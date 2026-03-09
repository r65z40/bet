import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { getSession, unauthorized, forbidden, notFound } from "@/lib/api-utils";

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ slug: string }> }
) {
  const session = await getSession();
  if (!session) return unauthorized();

  const { slug } = await params;
  const bar = await prisma.bar.findUnique({ where: { slug } });
  if (!bar) return notFound("Bar introuvable");

  if (bar.adminId !== session.user.id && session.user.role !== "SUPER_ADMIN") {
    return forbidden();
  }

  const [totalMembers, totalEvents, totalPredictions, activeEvents] =
    await Promise.all([
      prisma.barMember.count({ where: { barId: bar.id } }),
      prisma.event.count({ where: { barId: bar.id } }),
      prisma.prediction.count({
        where: { event: { barId: bar.id } },
      }),
      prisma.event.count({
        where: { barId: bar.id, status: "OPEN" },
      }),
    ]);

  return NextResponse.json({
    totalMembers,
    totalEvents,
    totalPredictions,
    activeEvents,
  });
}
