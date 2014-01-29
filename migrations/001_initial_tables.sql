PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
CREATE TABLE "sms" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "body" text(500) NOT NULL DEFAULT '',
  "user_id" integer NOT NULL,
  "created_at" integer NOT NULL
);
CREATE TABLE "user" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "name" text(250) NOT NULL DEFAULT '',
  "phone" text(10) NOT NULL DEFAULT '',
  "token" text(50) NOT NULL DEFAULT '',
  "created_at" integer NOT NULL
);
INSERT INTO "user" VALUES(1,'user0','88000000','123',strftime('%s', 'now'));
INSERT INTO "user" VALUES(2,'user1','99437911','444',strftime('%s', 'now'));
DELETE FROM sqlite_sequence;
INSERT INTO "sqlite_sequence" VALUES('user',2);
COMMIT;
