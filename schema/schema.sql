BEGIN TRANSACTION;
CREATE TABLE "tags" (
	`file_id`	INTEGER NOT NULL,
	`tag`	TEXT NOT NULL,
	PRIMARY KEY(file_id,tag),
	FOREIGN KEY(`file_id`) REFERENCES files ( id )
);
CREATE TABLE "tag_info" (
	`tag`	TEXT NOT NULL,
	`parent`	TEXT,
	`description`	TEXT,
	PRIMARY KEY(tag)
);
CREATE TABLE `full_text` (
	`file_id`	INTEGER,
	`full_text`	TEXT NOT NULL,
	PRIMARY KEY(file_id),
	FOREIGN KEY(`file_id`) REFERENCES files(id)
);
CREATE TABLE "files" (
	`id`	INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	`path`	TEXT UNIQUE,
	`title`	TEXT,
	`md5`	INTEGER,
	`date`	TEXT,
	`pages`	INTEGER,
	`origin`	TEXT,
	`recipient`	TEXT
);
COMMIT;
