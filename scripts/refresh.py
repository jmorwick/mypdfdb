#!/usr/local/bin/python3
# updates the database to include all pdfs in the data directory
# command-line arg: data-directory

import sys, os, sqlite3, re, hashlib

def md5(fname): 
	hash = hashlib.md5()
	with open(fname, "rb") as f:
		for chunk in iter(lambda: f.read(4096), b""):
			hash.update(chunk)
			return hash.hexdigest()

datadir = sys.argv[1]
connection = sqlite3.connect(datadir + '/.mypdfdb')

for root, dirs, files in os.walk(datadir):
	for filename in files:
		if(re.search("\.(pdf|PDF)$", filename)):
			fullpath = root + "/" + filename
			relativepath = fullpath[len(datadir)+1:]
			pdfHash = md5(fullpath)
			c = connection.cursor()
			c.execute('SELECT COUNT(*) FROM files WHERE path = ?;', (relativepath,))
			foundPath = c.fetchone()[0] == 1
			c = connection.cursor()
			c.execute('SELECT COUNT(*) FROM files WHERE md5 = ? AND path IS NULL;', (pdfHash,))
			foundHash = c.fetchone()[0] == 1
			if foundHash and not foundPath:
				c.execute('UPDATE files SET path=? WHERE md5 = ? ;', (relativepath,pdfHash,))
				connection.commit()
				print(fullpath + ' found orphaned record, updating...')
			elif not foundPath:
				c.execute('INSERT INTO files (path,md5) VALUES (?,?);', (relativepath,pdfHash,))
				connection.commit()
				print(fullpath + ' inserting in db...')
			else:
				print(fullpath + ' already in db, skipping...')
