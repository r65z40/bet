import { PrismaClient } from "../src/generated/prisma/client";
import { PrismaPg } from "@prisma/adapter-pg";
import { hash } from "bcryptjs";

const adapter = new PrismaPg({ connectionString: process.env.DATABASE_URL! });
const prisma = new PrismaClient({ adapter });

async function main() {
  console.log("Seeding database...");

  // Create badges
  const badges = await Promise.all([
    prisma.badge.upsert({
      where: { name: "Premier Pari" },
      update: {},
      create: {
        name: "Premier Pari",
        description: "A placé son premier pronostic",
        icon: "🎯",
        condition: "first_prediction",
      },
    }),
    prisma.badge.upsert({
      where: { name: "Habitué" },
      update: {},
      create: {
        name: "Habitué",
        description: "A participé à 10 événements",
        icon: "⭐",
        condition: "10_predictions",
      },
    }),
    prisma.badge.upsert({
      where: { name: "Champion" },
      update: {},
      create: {
        name: "Champion",
        description: "A atteint 1000 points",
        icon: "🏆",
        condition: "1000_points",
      },
    }),
    prisma.badge.upsert({
      where: { name: "Lucky" },
      update: {},
      create: {
        name: "Lucky",
        description: "3 pronostics gagnants d'affilée",
        icon: "🍀",
        condition: "3_wins_streak",
      },
    }),
    prisma.badge.upsert({
      where: { name: "Social" },
      update: {},
      create: {
        name: "Social",
        description: "A rejoint 3 bars différents",
        icon: "🍻",
        condition: "3_bars_joined",
      },
    }),
  ]);

  // Create admin user
  const adminPassword = await hash("admin123", 12);
  const admin = await prisma.user.upsert({
    where: { email: "admin@barbet.fr" },
    update: {},
    create: {
      name: "Admin BarBet",
      email: "admin@barbet.fr",
      passwordHash: adminPassword,
      role: "SUPER_ADMIN",
      totalPoints: 0,
    },
  });

  // Create bar admin
  const barAdminPassword = await hash("bar123", 12);
  const barAdmin = await prisma.user.upsert({
    where: { email: "patron@lepenalty.fr" },
    update: {},
    create: {
      name: "Jean Patron",
      email: "patron@lepenalty.fr",
      passwordHash: barAdminPassword,
      role: "BAR_ADMIN",
      totalPoints: 0,
    },
  });

  // Create regular users
  const userPassword = await hash("user123", 12);
  const users = await Promise.all(
    ["Alice", "Bob", "Charlie", "Diana", "Eric"].map((name) =>
      prisma.user.upsert({
        where: { email: `${name.toLowerCase()}@test.fr` },
        update: {},
        create: {
          name,
          email: `${name.toLowerCase()}@test.fr`,
          passwordHash: userPassword,
          role: "USER",
          totalPoints: 0,
        },
      })
    )
  );

  // Create a bar
  const bar = await prisma.bar.upsert({
    where: { slug: "le-penalty" },
    update: {},
    create: {
      name: "Le Penalty",
      slug: "le-penalty",
      description:
        "Le bar sportif incontournable ! Écrans géants, ambiance garantie.",
      address: "42 rue du Stade, 75015 Paris",
      adminId: barAdmin.id,
    },
  });

  // Add members to bar
  for (const user of [barAdmin, ...users]) {
    await prisma.barMember.upsert({
      where: { userId_barId: { userId: user.id, barId: bar.id } },
      update: {},
      create: { userId: user.id, barId: bar.id, points: 0 },
    });
  }

  // Create an open event
  await prisma.event.create({
    data: {
      title: "PSG vs Marseille",
      description: "Ligue 1 - Le Classique, Journée 28",
      barId: bar.id,
      status: "OPEN",
      startTime: new Date(Date.now() + 3600 * 1000 * 2),
      tokenAllowance: 100,
      outcomes: {
        create: [
          { label: "Victoire PSG", odds: 1.5 },
          { label: "Match nul", odds: 3.5 },
          { label: "Victoire OM", odds: 4.0 },
        ],
      },
    },
  });

  // Create a second event
  await prisma.event.create({
    data: {
      title: "Lyon vs Monaco",
      description: "Ligue 1 - Journée 28",
      barId: bar.id,
      status: "OPEN",
      startTime: new Date(Date.now() + 3600 * 1000 * 4),
      tokenAllowance: 50,
      outcomes: {
        create: [
          { label: "Victoire Lyon", odds: 2.2 },
          { label: "Match nul", odds: 3.0 },
          { label: "Victoire Monaco", odds: 2.8 },
        ],
      },
    },
  });

  // Give badge to first user
  await prisma.userBadge.create({
    data: { userId: users[0].id, badgeId: badges[0].id },
  });

  console.log("Seed completed!");
  console.log("");
  console.log("=== Comptes de test ===");
  console.log("Super Admin: admin@barbet.fr / admin123");
  console.log("Bar Admin:   patron@lepenalty.fr / bar123");
  console.log("Utilisateur: alice@test.fr / user123");
  console.log("            (bob, charlie, diana, eric)@test.fr / user123");
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());
