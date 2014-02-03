BEGIN;

CREATE TABLE "tmp_sms" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "phone" text NOT NULL,
  "body" text NOT NULL DEFAULT '',
  "user_id" integer NOT NULL,
  "status" integer NOT NULL,
  "created_at" integer NOT NULL,
  FOREIGN KEY ("user_id") REFERENCES "user" ("id") ON DELETE RESTRICT
);
INSERT INTO "tmp_sms" ("id", "body", "user_id", "created_at") SELECT "id", "body", "user_id", "created_at" FROM "sms";
DROP TABLE "sms";
ALTER TABLE "tmp_sms" RENAME TO "sms";


CREATE TABLE "tmp_user" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "name" text(250) NOT NULL,
  "password" text(100) NOT NULL,
  "roles" text(100) NOT NULL,
  "phone" text(10) NOT NULL,
  "token" text(100) NOT NULL,
  "created_at" integer NOT NULL
);
INSERT INTO "tmp_user" ("id", "name", "phone", "token", "created_at", "password", "roles")
    SELECT "id", "name", "phone", "token", "created_at", "", "" FROM "user";
DROP TABLE "user";
ALTER TABLE "tmp_user" RENAME TO "user";
CREATE UNIQUE INDEX "user_name" ON "user" ("name");
CREATE UNIQUE INDEX "user_token" ON "user" ("token");

COMMIT;
