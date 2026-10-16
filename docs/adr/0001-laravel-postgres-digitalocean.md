# Laravel + Postgres on a DigitalOcean droplet

Server-rendered Laravel (PHP 8.x) with Postgres, deployed to a single DigitalOcean droplet (start at 2 GB), with images in DigitalOcean Spaces behind its CDN and Imports/Watchlist checks as queued jobs. The target is 1000 simultaneous users; this holds only if Catalog pages are cached and images never touch the app server. The user had no stack preference beyond a mild lean to Laravel.
