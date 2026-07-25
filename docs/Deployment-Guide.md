# Deployment Guide — Smart Discussion Forum

> This document explains everything we did to deploy the app to the internet, why we did it, and how to explain it during a presentation. Written so anyone can understand it.

---

## The Big Picture — What "Deployment" Actually Means

Before this, the app only ran on one computer. You had to open a terminal, type `php artisan serve`, and the app would only be accessible at `http://localhost:8000` — meaning only the person sitting at that computer could use it.

**Deployment** means taking the app off your laptop and putting it on a server that runs 24/7 on the internet. After deployment:

- Anyone in the world can open a browser and use the app
- The desktop app connects to the live server instead of localhost
- You don't need to have your laptop open for the app to work

Think of it like the difference between cooking a meal only for yourself at home versus opening a restaurant — deployment is opening the restaurant.

---

## The Platform We Used — Render

**Render** (render.com) is a cloud hosting platform. It reads your code from GitHub, builds it, and runs it on its servers. It has a free tier which is what we used.

**Why Render and not something else?**
- It connects directly to GitHub — push your code and it deploys automatically
- It can create a PostgreSQL database for you automatically
- It's beginner-friendly compared to AWS or Google Cloud
- The free tier is enough for a demo/presentation

**The catch with the free tier:** The app "sleeps" after 15 minutes of no activity. The first person to visit after it's been sleeping will wait 30–50 seconds for it to wake up. After that it's fast. This is normal on the free plan.

---

## Why We Switched from MySQL to PostgreSQL

Your app was originally using **MySQL** as its database. Render's free database offering is **PostgreSQL** — a different (but very similar) database system.

**What's the difference?** Both are relational databases. Most queries work identically. The main differences are in some advanced syntax. Laravel supports both, so the switch was mostly a configuration change.

**What we changed:**
- Added `DB_CONNECTION=pgsql` environment variable on Render — tells Laravel to use PostgreSQL
- Added `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — the individual connection details from Render's database panel
- Fixed one query in `GroupStatisticsService.php` that used MySQL-specific date formatting (`DATE_FORMAT`) and changed it to PostgreSQL's equivalent (`TO_CHAR`)

---

## Every File We Created or Changed

### 1. `Dockerfile` — NEW FILE

**What it is:** A set of instructions that tells a service how to build and run your app inside a container.

**Why we needed it:** Render doesn't natively support PHP. It supports Docker — a technology that lets you package an app with everything it needs to run (PHP, Apache web server, Composer, Node.js). The Dockerfile is the recipe.

**What it does, line by line in plain English:**
- Starts from an official PHP 8.4 + Apache base image (like starting with a pre-built kitchen)
- Installs the PostgreSQL driver so PHP can talk to PostgreSQL
- Installs Composer (PHP's package manager) and Node.js/npm (JavaScript's package manager)
- Copies all your project files into the container
- Runs `composer install` to install PHP packages
- Runs `npm run build` to compile your CSS and JavaScript into files the browser can use
- Sets Apache to serve from Laravel's `public/` folder (not the project root)
- On every startup: clears config cache, runs database migrations, seeds the database, then starts Apache

**Why the `npm run build` step matters:** Your CSS and JavaScript are written in a modern format and need to be "compiled" into files browsers understand. On your local machine you do this manually (`npm run dev`). On the server, Docker does it automatically. Without this step, the app crashed with "View not found" because Laravel couldn't find the compiled assets.

---

### 2. `render.yaml` — MODIFIED FILE

**What it is:** A configuration file that tells Render what services to create and what environment variables to set.

**Why it exists:** Instead of clicking through Render's dashboard manually, this file describes everything in code. Render reads it and sets everything up automatically.

**What's in it:**
```yaml
services:
  - type: web
    name: smart-discussion-forum
    env: docker          # Use Docker (not native PHP)
    plan: free

    envVars:
      - key: APP_NAME
        value: Smart Discussion Forum
      - key: APP_ENV
        value: production   # Tells Laravel this is a live server, not development
      - key: APP_DEBUG
        value: false         # Hides error details from users (security)
      - key: APP_URL
        value: https://smart-discussion-forum-g23.onrender.com
      - key: APP_KEY
        sync: false          # Secret — entered manually in Render dashboard
      - key: DB_CONNECTION
        value: pgsql         # Use PostgreSQL
      - key: DATABASE_URL
        fromDatabase:
          name: studdit-db   # Render auto-fills this from the linked database
      - key: SESSION_DRIVER
        value: database      # Store sessions in the database
      - key: QUEUE_CONNECTION
        value: sync          # Process queued jobs immediately (no separate worker)
      - key: CACHE_STORE
        value: database      # Cache in the database
      - key: LOG_CHANNEL
        value: stderr        # Send logs to Render's log viewer

databases:
  - name: studdit-db
    databaseName: studdit
    user: studdit
    plan: free
```

**Key decisions explained:**
- `APP_DEBUG: false` — on a live server you never show error details to users. It's a security risk.
- `SESSION_DRIVER: database` — stores user session data in the database, not in files. Files don't persist reliably on Render's free tier.
- `APP_KEY: sync: false` — the app key encrypts user sessions and cookies. It must never be committed to GitHub (anyone who sees it can decrypt your users' data). `sync: false` tells Render to ask for it manually.

---

### 3. `.dockerignore` — NEW FILE

**What it is:** Tells Docker which files NOT to copy into the container.

**Why it matters:** Some files should never go to the server:
- `.env` — contains your local database passwords and app key
- `node_modules/` — thousands of files that get regenerated during build
- `vendor/` — PHP packages that get reinstalled during build
- `tests/` — test files aren't needed in production
- `storage/logs/*` — your local log files are irrelevant on the server

Excluding these makes the build faster and keeps secrets off the server.

---

### 4. `.renderignore` — NEW FILE

Similar to `.dockerignore` but for Render's file upload process. Tells Render not to upload certain files when deploying.

---

### 5. `bootstrap/app.php` — MODIFIED FILE

**What changed:** Added two middleware configurations:

```php
$middleware->trustProxies(at: '*');
$middleware->remove(\Illuminate\Http\Middleware\TrustHosts::class);
```

**Why:**
- `trustProxies(at: '*')` — Render runs your app behind a load balancer (a server that distributes traffic). Laravel needs to be told to trust headers from this proxy, otherwise it gets confused about the real user's IP address and the request URL.
- Removing `TrustHosts` — this middleware was too strict and was blocking requests because the incoming host header didn't exactly match `APP_URL`. Removing it allows the app to respond to requests normally.

---

### 6. `database/seeders/DatabaseSeeder.php` — MODIFIED FILE

**What changed:** Removed the call to `DemoDataSeeder`.

**Why:** `DemoDataSeeder.php` is in `.gitignore` — it was never pushed to GitHub (it probably contained sensitive test data). When the server tried to run it during deployment, it crashed with "Target class DemoDataSeeder does not exist". Removing the reference from `DatabaseSeeder.php` fixed the crash.

---

### 7. `app/Services/GroupStatisticsService.php` — MODIFIED FILE

**What changed:** Changed `DATE_FORMAT(created_at, '%Y-%u')` to `TO_CHAR(created_at, 'IYYY-IW')`.

**Why:** `DATE_FORMAT` is MySQL-specific syntax. PostgreSQL doesn't understand it. `TO_CHAR` is the PostgreSQL equivalent. This would have caused the Group Statistics page to crash after deployment.

---

### 8. `resources/views/layouts/app.blade.php` — MODIFIED FILE

**What changed:** Fixed the hamburger menu toggle for mobile.

**The bug:** On mobile, clicking the hamburger (☰) button was toggling `is-collapsed` (a desktop class that shrinks the sidebar). On mobile, the sidebar needs `is-open` class to slide in from the left.

**The fix:** Added a check — if the screen is mobile size (≤768px), toggle `is-open`. If desktop, toggle `is-collapsed`.

---

### 9. `ApiClient.java` (Desktop App) — MODIFIED FILE

**What changed:**
```java
// Before
private static final String BASE_URL = "http://localhost:8000/api/v1";

// After
private static final String BASE_URL = "https://smart-discussion-forum-g23.onrender.com/api/v1";
```

**Why:** After deployment, the Laravel backend is no longer running on your laptop. It's running on Render's servers. The desktop app needs to know the new address. This one-line change means the desktop app now talks to the live server instead of localhost — so it works on any computer, not just yours.

---

## The Deployment Process Step by Step

Here's the exact sequence of what we did:

1. Created `Dockerfile`, `render.yaml`, `.dockerignore`, `.renderignore`
2. Fixed `DatabaseSeeder.php` to remove the missing seeder reference
3. Committed and pushed everything to the `main` branch on GitHub
4. Created a Render account (signed up with GitHub)
5. Created a new Web Service on Render, connected it to the GitHub repository, selected Docker as the language
6. Added environment variables manually (including `APP_KEY` which is too sensitive for the yaml file)
7. Created a PostgreSQL database called `studdit-db` on Render
8. Added the database connection details (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) as environment variables on the web service
9. Triggered a deploy — it failed because the frontend assets weren't built
10. Updated the Dockerfile to include `npm ci && npm run build`
11. Pushed the fix, deployed again — it worked
12. The app became accessible at `https://smart-discussion-forum-g23.onrender.com`
13. Updated the desktop app's `BASE_URL` to the live URL
14. Enabled Auto-Deploy on Render (On Commit) so future pushes to `main` deploy automatically

---

## Auto-Deploy — How Updates Work Now

Render is set to **"On Commit"** auto-deploy. This means:

- Anyone on the team pushes to the `main` branch → Render automatically detects it → starts a new build → deploys the new version
- Takes 5–8 minutes per deploy
- The old version keeps running until the new one is ready

**The team workflow:**
1. Each person works on their own branch
2. When ready, they create a pull request to merge into `main`
3. Once merged, Render deploys automatically
4. The live site updates within minutes

---

## The Live Database vs Local Database

This is important to understand:

- **Local machine** — was using SQLite (`database/database.sqlite`). All test accounts you created locally are here.
- **Render's server** — uses PostgreSQL. This is a completely separate database. It only contains what the seeders created: roles, groups, and the super admin account.

**Default super admin credentials on the live server:**
- Email: `superadmin@example.com`
- Password: `password`

To create other accounts on the live server, either register through the web app or log in as super admin and create users via the admin panel.

---

## How to Explain This During Your Presentation

Here is a suggested script. Use your own words — don't read this verbatim.

---

### Opening (30 seconds)

> "I'll talk about how we deployed our application to the internet so it's accessible to anyone, anywhere.
>
> Before deployment, the app only ran on one laptop. You had to be sitting at that specific computer to use it. Deployment takes the app off the laptop and puts it on a server that runs 24/7."

---

### The platform (30 seconds)

> "We used a platform called Render to host the backend. Render connects to our GitHub repository — whenever we push new code to the main branch, Render automatically picks it up, rebuilds the app, and deploys the new version. We didn't need to set up any servers or infrastructure ourselves."

---

### The technical challenge (1 minute)

> "Render doesn't support PHP directly — it uses a technology called Docker. Docker lets you package an app with everything it needs to run: the PHP runtime, the web server, the database driver, and even the frontend build tools.
>
> We wrote a Dockerfile — think of it as a recipe — that tells Docker: install PHP 8.4, install Apache web server, install Composer for PHP packages, install Node.js to compile the CSS and JavaScript, then run migrations to set up the database, and start the server.
>
> The main challenge we hit was that our frontend assets — the CSS and JavaScript — need to be compiled before the app can use them. On a local machine you do this manually. In Docker, we added a step to compile them automatically as part of the build process."

---

### The database (30 seconds)

> "We also switched from MySQL — which we used locally — to PostgreSQL, which is what Render provides for free. Laravel supports both databases, so the switch was mostly a configuration change. We updated the connection settings and fixed one database query that used MySQL-specific syntax."

---

### The result (30 seconds)

> "The result is that our app is now live at smart-discussion-forum-g23.onrender.com. The web app is accessible in any browser. The desktop app was updated with one line of code — changing the server address from localhost to the live URL — so it now connects to the same backend. Both apps share the same database."

---

### If asked about cost

> "We're on the free tier, which is enough for a demo. The limitation is that the app sleeps after 15 minutes of inactivity, so the first request after it's been idle takes about 30 seconds to respond. For a production deployment you'd upgrade to a paid plan."

---

### If asked why not use Docker locally

> "We do have Docker support available, but for a beginner-friendly setup Render handles all the infrastructure for us. We just write the Dockerfile and push to GitHub — Render does the rest."

---

## Quick Reference

| Item | Value |
|---|---|
| Live URL | https://smart-discussion-forum-g23.onrender.com |
| Login page | https://smart-discussion-forum-g23.onrender.com/login |
| Super admin email | superadmin@example.com |
| Super admin password | password |
| GitHub repo | Da-code135/Smart-Discussion-Forum-G23 |
| Deploy branch | main |
| Auto-deploy | On Commit (automatic) |
| Database | PostgreSQL on Render (studdit-db) |
| Desktop app API URL | https://smart-discussion-forum-g23.onrender.com/api/v1 |
