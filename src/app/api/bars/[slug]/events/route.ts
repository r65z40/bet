import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { getSession, unauthorized, forbidden, badRequest, notFound } from "@/lib/api-utils";

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ slug: string }> }
) {
  const { slug } = await params;
  const bar = await prisma.bar.findUnique({ where: { slug } });
  if (!bar) return notFound("Bar introuvable");

  const events = await prisma.event.findMany({
    where: { barId: bar.id },
    orderBy: { startTime: "desc" },
    include: {
      outcomes: true,
      _count: { select: { predictions: true } },
    },
  });

  return NextResponse.json(events);
}

export async function POST(
  req: NextRequest,
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

  const { title, description, startTime, endTime, tokenAllowance, outcomes } =
    await req.json();

  if (!title || !startTime || !outcomes || outcomes.length < 2) {
    return badRequest("Titre, date de début et au moins 2 options sont requis");
  }

  const event = await prisma.event.create({
    data: {
      title,
      description,
      barId: bar.id,
      startTime: new Date(startTime),
      endTime: endTime ? new Date(endTime) : null,
      tokenAllowance: tokenAllowance || 100,
      status: "OPEN",
      outcomes: {
        create: outcomes.map((o: { label: string; odds?: number }) => ({
          label: o.label,
          odds: o.odds || 2.0,
        })),
      },
    },
    include: { outcomes: true },
  });

  return NextResponse.json(event, { status: 201 });
}
