import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { getSession, unauthorized, badRequest } from "@/lib/api-utils";
import * as QRCode from "qrcode";

export async function GET() {
  const bars = await prisma.bar.findMany({
    include: { _count: { select: { members: true, events: true } } },
    orderBy: { createdAt: "desc" },
  });
  return NextResponse.json(bars);
}

export async function POST(req: NextRequest) {
  const session = await getSession();
  if (!session) return unauthorized();

  const { name, description, address } = await req.json();
  if (!name) return badRequest("Le nom est requis");

  const slug = name
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/(^-|-$)/g, "");

  const existingSlug = await prisma.bar.findUnique({ where: { slug } });
  if (existingSlug) return badRequest("Ce nom est déjà pris");

  const bar = await prisma.bar.create({
    data: {
      name,
      slug,
      description,
      address,
      adminId: session.user.id,
    },
  });

  // Generate QR code
  const joinUrl = `${process.env.NEXTAUTH_URL || "http://localhost:3000"}/bars/${bar.slug}/join`;
  const qrCode = await QRCode.toDataURL(joinUrl, { width: 300, margin: 2 });

  const updatedBar = await prisma.bar.update({
    where: { id: bar.id },
    data: { qrCode },
  });

  // Make the creator a BAR_ADMIN
  await prisma.user.update({
    where: { id: session.user.id },
    data: { role: "BAR_ADMIN" },
  });

  // Auto-join the bar
  await prisma.barMember.create({
    data: { userId: session.user.id, barId: bar.id },
  });

  return NextResponse.json(updatedBar, { status: 201 });
}
