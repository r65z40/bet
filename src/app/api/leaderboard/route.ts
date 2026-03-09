import { NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";

export async function GET() {
  const users = await prisma.user.findMany({
    where: { totalPoints: { gt: 0 } },
    orderBy: { totalPoints: "desc" },
    take: 100,
    select: {
      id: true,
      name: true,
      image: true,
      totalPoints: true,
    },
  });

  return NextResponse.json(
    users.map((u: typeof users[number], i: number) => ({ rank: i + 1, ...u }))
  );
}
