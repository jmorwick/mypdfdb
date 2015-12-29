# mypdfdb
mypdfdb - A simple, personal, filesystem-based PDF organizing web application

## The Impetus
I'm generally not very good at the things computers are good at, which is probably why I originally began programming. In particular, keeping track of paper files has never been a strength of mine. I've sought to rectify this by scanning all of my files and storing them as PDF's, and have in the past written a mysql-based applicaiton to organize these files, but found it cumbersome for backups, encryption, adapting to new requirements, etc. I ended up keeping track of these PDF's manually, using the filesystem, since I couldn't find another free and open-source application I liked to assist me in this task. 

For my second attempt (mypdfdb), I decided to build an app which merely manages and annotates a directory of PDF's (possibly with subdirectories of arbitrary depth) with a sqlite file at the root. This way I could still use the filesystem to perform backups, encrypt my files, etc, but could also have something better than file attributes and PDF metadata to organize my files. This application is really meant for my family and I, but I decided to open-source it on github for most of the usual good reasons. It's also an excuse to play around with jquery and sqlite a bit. 

## The Design Philosophy

With all that in mind, I don't intend for this to be a terribly feature-rich application. It's important that the schema is very simple (both the sqlite schema, and the method of storing other data (i.e pdf files)) so that mypdfdb is easy to extend and its data is easy to work with for any future code I write to help me keep organized. With that in mind, if anyone else decides to play with this, pull requests are welcome as long as they don't alter the method of data storage much or at all.

## Development

The app is currently under development. Check the milestones for my (potentially shifting) expectations of when something usable will be produced. 
