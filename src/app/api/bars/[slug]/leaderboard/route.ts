import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { notFound } from "@/lib/api-utils";

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ slug: string }> }
) {
  const { slug } = await params;
  const bar = await prisma.bar.findUnique({ where: { slug } });
  if (!bar) return notFound("Bar introuvable");

  const members = await prisma.barMember.findMany({
    where: { barId: bar.id },
    orderBy: { points: "desc" },
    take: 50,
    include: {
      user: { select: { id: true, name: true, image: true } },
    },
  });

  return NextResponse.json(
    members.map((m: typeof members[number], i: number) => ({
      rank: i + 1,
      userId: m.user.id,
      name: m.user.name,
      image: m.user.image,
      points: m.points,
    }))
  );
}
