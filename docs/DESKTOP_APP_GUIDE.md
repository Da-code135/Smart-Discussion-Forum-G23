# JavaFX Desktop App — Complete Step-by-Step Guide

**Team:** Build a desktop client for the Smart Discussion Forum
**Backend:** Laravel API at `http://localhost:8000/api/v1/`
**Prerequisites:** You know PHP/Laravel but NOT Java or JavaFX
**Team size:** 5 people

---

## Table of Contents

1. [What We're Building](#1-what-were-building)
2. [Install Prerequisites](#2-install-prerequisites)
3. [Phase 0 — Project Setup (ALL 5 TOGETHER)](#3-phase-0--project-setup-all-5-together)
4. [Phase 1 — API Communication Layer (Person 1)](#4-phase-1--api-communication-layer-person-1)
5. [Phase 2 — Login Screen (Person 1)](#5-phase-2--login-screen-person-1)
6. [Phase 3 — Dashboard Shell (Person 1, Others Extend)](#6-phase-3--dashboard-shell-person-1-others-extend)
7. [Code Deep Dive — How Each File Connects to the Backend](#7-code-deep-dive--how-each-file-connects-to-the-backend)
8. [Person 1 — Notifications, Profile, Password](#8-person-1--remaining-screens-notifications-profile-password)
9. [Person 2 — Forum Topics, Posts, Conversations, Messages](#9-person-2--forum-topics-posts-conversations-messages)
10. [Person 3 — Quizzes (Lecturer + Student)](#10-person-3--quizzes-lecturer--student)
11. [Person 4 — Groups, Sync Engine, Recommendations](#11-person-4--groups-sync-engine-recommendations)
12. [Person 5 — Admin Features](#12-person-5--admin-features)
13. [Testing Your Feature](#13-testing-your-feature)
14. [Final Verification](#14-final-verification)
15. [Common Gotchas](#15-common-gotchas-read-this-before-starting)
16. [Quick Reference: Pattern for Every Screen](#16-quick-reference-pattern-for-every-screen)
17. [API Response Reference](#17-api-response-reference)

---

## 1. What We're Building

### The Big Picture

```
Your Desktop App (JavaFX)
    │
    │  HTTP requests (JSON)
    │  via OkHttp library
    ▼
Your Laravel Server (http://localhost:8000)
    │
    │  Handles all logic,
    │  database queries, auth
    ▼
MySQL Database
```

**The desktop app NEVER talks to the database.** Every click, every search, every save goes through the REST API. The Laravel server must be running (`php artisan serve`) for the desktop app to work.

### What is JavaFX?

JavaFX is Java's toolkit for building desktop applications. Think of it as:

| Concept | PHP/Laravel Equivalent | What it is in JavaFX |
|---------|----------------------|---------------------|
| **Stage** | The browser window | The actual window frame (title bar, close button) |
| **Scene** | The HTML page | What's inside the window |
| **Node** | An HTML element (div, button) | A UI piece: Button, TextField, Label |
| **Scene Graph** | The DOM tree | The tree of UI elements |
| **Controller** | A Laravel controller method | Code that runs when you click a button |
| **FXML** | A Blade template | An XML file describing the layout (we won't use it) |

**Our approach:** We will build every screen in **pure Java code** (no FXML files). This means one less thing to learn. Instead of switching between XML layouts and Java controllers, you write everything in one place.

### What is Maven?

Maven is a **build tool** — like Composer for PHP, but for Java. It:

- Downloads dependencies (libraries) automatically from the internet
- Compiles your Java code
- Runs your application

Instead of installing Maven globally, we use the **Maven Wrapper** (`mvnw.cmd`) — a small script that auto-downloads the right Maven version. You run `mvnw.cmd clean javafx:run` and it just works.

### Folder Structure

The desktop app lives in a **separate folder** outside the Laravel project. The two never mix:

```
C:\Users\hp\Desktop\
├── smart-discussion-forum/             ← your existing Laravel repo
│   ├── app/
│   ├── routes/
│   ├── ... (Laravel files)
│   └── .git/
│
└── smart-discussion-forum-desktop/     ← NEW: the desktop app (own git repo)
    ├── pom.xml                         ← 🟢 dependency file (like composer.json)
    ├── mvnw.cmd                        ← 🟢 Maven Wrapper (auto-downloads Maven)
    ├── mvnw                            ← 🟢 Linux version of the wrapper
    ├── .mvn/                           ← 🟢 Maven config folder
    │
    └── src/main/
        ├── java/com/yourforum/
        │   ├── App.java                ← 🟢 Entry point (main method)
        │   ├── api/                    ← 🟢 HTTP & Auth layer
        │   │   ├── ApiClient.java
        │   │   └── AuthManager.java
        │   ├── models/                 ← 🟢 Data classes matching API responses
        │   │   ├── User.java
        │   │   └── ...
        │   ├── views/                  ← 🟢 One file per screen (layout + logic)
        │   │   ├── LoginView.java
        │   │   ├── RegisterView.java
        │   │   ├── DashboardView.java
        │   │   └── ...
        │   └── utils/                  ← 🟢 Helper classes
        │       └── TokenStorage.java
        │
        └── resources/
            └── styles/
                └── app.css             ← 🟢 Colors, fonts, sizes
```

**Why a separate folder?**
- Clean separation of languages (PHP vs Java) — no confusion
- Each project gets its own git repository (the assignment says "Create private git repositories" — plural)
- Laravel's `vendor/` and Java's Maven downloads don't interfere
- You can give different team members access to different repos

---

## 2. Install Prerequisites

### Step 2.1: Install JDK 21

**What is JDK?** The Java Development Kit — you need it to compile (`javac`) and run (`java`) Java code. JDK 21 is the latest Long Term Support version.

**Download:** https://adoptium.net/ (Temurin 21 — LTS)

Scroll down, click **"Latest LTS Release"**, choose **Windows x64 MSI installer**.

**Install:** Run the installer. Default settings are fine. It adds `java` and `javac` to your PATH automatically.

**Verify it worked.** Open a **new** Command Prompt or PowerShell (close and reopen):
```cmd
java --version
```
You should see something like:
```
openjdk 21.0.1 2023-10-17 LTS
```

Then check the compiler:
```cmd
javac --version
```
Should show `javac 21.0.1`.

**If you get "'java' is not recognized":** The installer didn't add Java to your PATH.
1. Press Windows key, type "environment variables"
2. Click "Edit environment variables for your account"
3. Under "System variables", find `Path`, click Edit
4. Add `C:\Program Files\Eclipse Adoptium\jdk-21.0.1.9-hotspot\bin` (or wherever it installed)
5. Click OK on all windows, reopen terminal, try again

### Step 2.2: Verify Git

You already have git (it's in your project). Verify:
```cmd
git --version
```

### What about Maven?

You do NOT install Maven. The Maven Wrapper (`mvnw.cmd`) handles it. We download it in Phase 0.

---

## 3. Phase 0 — Project Setup (ALL 5 TOGETHER)

**Goal:** Create the `smart-discussion-forum-desktop` folder, write `pom.xml`, download Maven Wrapper, and compile a window that says "Hello from Smart Discussion Forum."

**Time:** ~1 hour with all 5 working together.

### Step 3.1: Create the folder structure

Navigate to your desktop (or wherever you keep your projects) and create a new folder:

```cmd
cd C:\Users\hp\Desktop
mkdir smart-discussion-forum-desktop
cd smart-discussion-forum-desktop
```

Now create the Java source folders inside it:

```cmd
mkdir src\main\java\com\yourforum\api
mkdir src\main\java\com\yourforum\models
mkdir src\main\java\com\yourforum\views
mkdir src\main\java\com\yourforum\utils
mkdir src\main\resources\styles
```

Your result should look like this on disk:
```
C:\Users\hp\Desktop\smart-discussion-forum-desktop\
├── src\
│   ├── main\
│   │   ├── java\com\yourforum\
│   │   │   ├── api\
│   │   │   ├── models\
│   │   │   ├── views\
│   │   │   └── utils\
│   │   └── resources\styles\
```

**Why this folder structure?**

- `src/main/java` — This is the Maven convention for Java source code. `src/main/resources` is for non-code files (CSS, images, etc.).
- `com/yourforum` — A **package name**. In Java, packages prevent naming conflicts. If two people both create a class called `User`, they go in different packages. We use `com.yourforum` but you can change it to your team name (e.g. `com.teamname`).
- Each subfolder has a purpose:
  - `api/` — Classes that talk to the Laravel server
  - `models/` — Classes that represent data (User, Topic, Post, etc.)
  - `views/` — One file per screen. Contains BOTH the layout code and the logic code (no FXML)
  - `utils/` — Utility/helper classes (token storage, alerts, etc.)

### Step 3.2: Create pom.xml

**What is a POM?** `pom.xml` stands for **Project Object Model**. It's Maven's version of `composer.json` — it lists your project's dependencies (libraries) and how to build it.

Create the file `pom.xml` (in the `smart-discussion-forum-desktop` folder — not inside `src/`, it sits at the root) and paste this:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<project xmlns="http://maven.apache.org/POM/4.0.0"
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://maven.apache.org/POM/4.0.0 http://maven.apache.org/xsd/maven-4.0.0.xsd">
    <modelVersion>4.0.0</modelVersion>

    <!-- YOUR TEAM'S IDENTIFIER -->
    <groupId>com.yourforum</groupId>
    <artifactId>smart-discussion-forum-desktop</artifactId>
    <version>1.0-SNAPSHOT</version>

    <!-- JAVA VERSION -->
    <properties>
        <maven.compiler.source>21</maven.compiler.source>
        <maven.compiler.target>21</maven.compiler.target>
        <javafx.version>21.0.1</javafx.version>
        <project.build.sourceEncoding>UTF-8</project.build.sourceEncoding>
    </properties>

    <!-- DEPENDENCIES: libraries we download and use -->
    <dependencies>
        <!-- JavaFX Controls: gives us Button, TextField, Label, VBox, etc.
             Without this, we can only print text to the console. -->
        <dependency>
            <groupId>org.openjfx</groupId>
            <artifactId>javafx-controls</artifactId>
            <version>${javafx.version}</version>
        </dependency>

        <!-- JavaFX Web: needed for the WebView component
             (used when we preview PDF exports or show embedded content) -->
        <dependency>
            <groupId>org.openjfx</groupId>
            <artifactId>javafx-web</artifactId>
            <version>${javafx.version}</version>
        </dependency>

        <!-- OkHttp: the library that makes HTTP requests.
             Think of it as GuzzleHttp or Axios for Java.
             We use it to call POST /api/v1/login, GET /api/v1/topics, etc. -->
        <dependency>
            <groupId>com.squareup.okhttp3</groupId>
            <artifactId>okhttp</artifactId>
            <version>4.12.0</version>
        </dependency>

        <!-- Gson: turns JSON (what the API returns) into Java objects.
             When the API sends back {"id":1,"full_name":"John"},
             Gson creates a User object with id=1 and fullName="John". -->
        <dependency>
            <groupId>com.google.code.gson</groupId>
            <artifactId>gson</artifactId>
            <version>2.10.1</version>
        </dependency>

        <!-- JUnit 5: for writing tests (optional but recommended).
             Tests ensure your API calls return the right data. -->
        <dependency>
            <groupId>org.junit.jupiter</groupId>
            <artifactId>junit-jupiter</artifactId>
            <version>5.10.1</version>
            <scope>test</scope>
        </dependency>
    </dependencies>

    <!-- BUILD PLUGINS: tools Maven uses during compilation -->
    <build>
        <plugins>
            <!-- JavaFX Maven Plugin: lets us run "mvn javafx:run"
                 to launch the app. Without this plugin, starting a
                 JavaFX app from the command line is complicated. -->
            <plugin>
                <groupId>org.openjfx</groupId>
                <artifactId>javafx-maven-plugin</artifactId>
                <version>0.0.8</version>
                <configuration>
                    <mainClass>com.yourforum.App</mainClass>
                </configuration>
            </plugin>
        </plugins>
    </build>
</project>
```

**What each dependency does (plain English):**

| Dependency | Why we need it |
|------------|----------------|
| `javafx-controls` | The actual UI toolkit — `Button`, `TextField`, `TableView`, `VBox`, etc. |
| `okhttp` | Makes HTTP requests to the Laravel API. Handles headers, timeouts, errors. |
| `gson` | Parses JSON responses into Java objects. Without it, we'd parse JSON manually. |
| `junit-jupiter` | Testing framework. We'll write tests later to check our code works. |

### Step 3.3: Download Maven Wrapper

In your `smart-discussion-forum-desktop` folder (you should already be here from the previous step), run:

```cmd
cd C:\Users\hp\Desktop\smart-discussion-forum-desktop
```

Then download the Maven Wrapper files. Since you may not have curl, create these files manually:

**`smart-discussion-forum-desktop/.mvn/wrapper/maven-wrapper.properties`** — Create the `.mvn/wrapper` folder first, then this file:

```properties
distributionUrl=https://repo.maven.apache.org/maven2/org/apache/maven/apache-maven/3.9.6/apache-maven-3.9.6-bin.zip
wrapperUrl=https://repo.maven.apache.org/maven2/org/apache/maven/wrapper/maven-wrapper/3.2.0/maven-wrapper-3.2.0.jar
```

**`smart-discussion-forum-desktop/mvnw.cmd`** — Download from:
https://raw.githubusercontent.com/apache/maven-wrapper/maven-wrapper-3.2.0/mvnw.cmd

Right-click → Save as → `mvnw.cmd` in the `smart-discussion-forum-desktop` folder.

**`smart-discussion-forum-desktop/mvnw`** — The Linux/Mac version. Same URL but with `mvnw` at the end.

**Alternative:** If you have Node.js or curl, you can generate the wrapper by running this from the `smart-discussion-forum-desktop` folder (if you have a Java JDK, the wrapper works):

```
mvn -N wrapper:wrapper
```

But since you don't have Maven installed yet, this won't work. So use the manual method above.

### Step 3.4: Create App.java — the entry point

**What is App.java?** The starting point of the application. Every JavaFX app needs a class that extends `Application` and overrides the `start()` method. This is like `index.php` or `public/index.php` — it's where execution begins.

Create `src/main/java/com/yourforum/App.java` (inside your `smart-discussion-forum-desktop` folder):

```java
// Line 1: Package declaration. Must match the folder structure.
// If the file is in src/main/java/com/yourforum/App.java,
// the package is com.yourforum.
package com.yourforum;

// Import: these bring in the JavaFX classes we need.
// Without imports, we'd have to write javafx.application.Application
// every time instead of just Application.
import javafx.application.Application;
import javafx.scene.Scene;
import javafx.scene.control.Label;
import javafx.scene.layout.VBox;
import javafx.stage.Stage;

/**
 * App — the entry point for the Smart Discussion Forum Desktop App.
 *
 * Every JavaFX application extends Application.
 * JavaFX calls the start() method automatically after main().
 * Think of it like Laravel's public/index.php — it bootstraps everything.
 */
public class App extends Application {

    /**
     * start() is called by JavaFX after launch().
     *
     * @param stage The main window. Think of it as the browser window frame —
     *              it has a title bar, a close button, and holds content.
     */
    @Override
    public void start(Stage stage) {
        // Set the window title (appears in the title bar)
        stage.setTitle("Smart Discussion Forum");

        // Set the window size: 1024 pixels wide, 768 pixels tall
        stage.setWidth(1024);
        stage.setHeight(768);

        // --- Build a simple "Hello" screen ---
        // VBox is a vertical box layout. Children stack from top to bottom.
        // The 10 is spacing (pixels) between children.
        VBox root = new VBox(10);

        // Label: a piece of text on the screen
        Label welcomeLabel = new Label("Welcome to Smart Discussion Forum");
        // Add the label to the VBox
        root.getChildren().add(welcomeLabel);

        // Scene: the content inside the window.
        // Think of it as the <body> tag in HTML.
        Scene scene = new Scene(root, 1024, 768);

        // Apply our CSS stylesheet (we'll create it next)
        // getClass().getResource() finds the file in src/main/resources
        String css = getClass().getResource("/styles/app.css").toExternalForm();
        scene.getStylesheets().add(css);

        // Put the scene in the stage (window)
        stage.setScene(scene);

        // Show the window
        stage.show();
    }

    /**
     * main() — the actual entry point of any Java program.
     *
     * launch() tells JavaFX to:
     * 1. Create the JavaFX runtime
     * 2. Call start() with a new Stage (window)
     * 3. Keep the app running until the window closes
     */
    public static void main(String[] args) {
        launch();
    }
}
```

**Why every line matters (summary):**

| Line | Why |
|------|-----|
| `extends Application` | Tells Java this is a JavaFX app, not a regular console program |
| `start(Stage stage)` | Called automatically when the app launches. You build your UI here. |
| `stage.setTitle(...)` | Sets what appears in the window's title bar |
| `VBox root = new VBox(10)` | Creates a layout container. All UI elements go inside. |
| `Label welcome = new Label(...)` | Creates text on screen |
| `Scene scene = new Scene(...)` | Packages your layout into a "page" that goes in the window |
| `stage.setScene(scene)` | Puts the page inside the window |
| `stage.show()` | Makes the window visible |
| `main()` with `launch()` | The JVM calls main(), which calls launch(), which calls start() |

### Step 3.5: Create app.css — the stylesheet

**What is app.css?** Just like CSS for a website, this file defines colors, fonts, spacing, and sizes for your desktop app.

Create `src/main/resources/styles/app.css`:

```css
/* ========================================
   Smart Discussion Forum — Desktop Theme
   ======================================== */

/* Root pane: applies to the main background */
.root {
    -fx-font-family: "Segoe UI", "Arial", sans-serif;
    -fx-background-color: #f4f6f9;
}

/* Labels (text elements) */
.label {
    -fx-text-fill: #2c3e50;
}

/* Buttons */
.button {
    -fx-background-color: #3498db;
    -fx-text-fill: white;
    -fx-padding: 8 16;
    -fx-background-radius: 4;
    -fx-cursor: hand;
}

/* Button hover effect */
.button:hover {
    -fx-background-color: #2980b9;
}

/* Text input fields */
.text-field, .password-field {
    -fx-padding: 8;
    -fx-background-radius: 4;
    -fx-border-radius: 4;
    -fx-border-color: #bdc3c7;
    -fx-border-width: 1;
}

/* Text input focused state */
.text-field:focused, .password-field:focused {
    -fx-border-color: #3498db;
}

/* Table views (lists of data) */
.table-view {
    -fx-background-color: white;
    -fx-border-color: #e0e0e0;
    -fx-border-radius: 4;
}

/* Sidebar background */
.sidebar {
    -fx-background-color: #2c3e50;
    -fx-padding: 10;
}

/* Sidebar buttons */
.sidebar .button {
    -fx-background-color: transparent;
    -fx-text-fill: white;
    -fx-alignment: center-left;
    -fx-pref-width: 180;
}

.sidebar .button:hover {
    -fx-background-color: #34495e;
}
```

### Step 3.6: Compile and Run!

Open a terminal in your `smart-discussion-forum-desktop` folder and run:

```cmd
mvnw.cmd clean javafx:run
```

**What happens:**
1. `mvnw.cmd` downloads Maven (if not cached)
2. Maven reads `pom.xml` and downloads all dependencies (JavaFX, OkHttp, Gson) from the internet
3. `clean` deletes any previous compiled files
4. `javafx:run` compiles your Java code and launches the app
5. A window titled "Smart Discussion Forum" appears with "Welcome to Smart Discussion Forum"

**First run will be slow** (downloading Maven + dependencies). Subsequent runs are fast.

### Troubleshooting Phase 0

**"mvnw.cmd is not recognized"** — You're in the wrong folder. Make sure you're in `smart-discussion-forum-desktop\`, not the Laravel root.

**"JavaFX runtime components are missing"** — You downloaded a JRE, not a JDK. Install the JDK from Adoptium.

**"Maven download failed"** — No internet connection. The wrapper downloads Maven from the internet on first run.

**"javac: not found"** — JDK not installed or not on PATH. Run `java --version` first. If that works but `javac` doesn't, reinstall the JDK.

---

## 4. Phase 1 — API Communication Layer (Person 1)

**Goal:** Create the classes that talk to the Laravel API.

**Time:** ~3 hours for Person 1.

### Step 4.1: Understand the Architecture

Every screen in the desktop app follows the same pattern:

```
User clicks button
    → Controller runs
    → Controller calls ApiClient.get("/topics")
    → ApiClient sends HTTP request to Laravel (with Bearer token)
    → Laravel returns JSON
    → Gson parses JSON into Model objects (Topic, User, etc.)
    → Controller updates the UI with the data
```

`ApiClient.java` is the **single class everyone uses**. It's a **singleton** — there's only ONE instance shared by the whole app. This ensures every screen uses the same server URL, the same auth token, and the same HTTP settings.

### Step 4.2: Create ApiClient.java

**What it does:** A reusable HTTP client with methods for GET, POST, PUT, DELETE. It automatically attaches the auth token to every request.

**Why singleton:** If every screen created its own HTTP client, they'd each need their own token copy, and changing the token (logout) would require updating every object.

Create `src/main/java/com/yourforum/api/ApiClient.java`:

```java
package com.yourforum.api;

// OkHttp imports — the library that makes HTTP requests
import okhttp3.MediaType;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.RequestBody;
import okhttp3.Response;

// Gson imports — parses JSON
import com.google.gson.Gson;
import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;

// Java imports
import java.io.IOException;
import java.util.concurrent.TimeUnit;

/**
 * ApiClient — handles ALL communication with the Laravel backend.
 *
 * SINGLETON: Only one instance exists for the entire app.
 * Use ApiClient.getInstance() to access it.
 *
 * Every method:
 * 1. Builds an HTTP request with the auth token
 * 2. Sends it to the Laravel API
 * 3. Returns the JSON response as a Gson object
 */
public class ApiClient {

    // ──────────────────────────────────────────────
    // SINGLETON PATTERN
    // ──────────────────────────────────────────────

    // The single instance (static = shared across the whole app)
    private static ApiClient instance;

    // OkHttpClient — the actual HTTP engine.
    // We configure it once: timeouts, follow redirects, etc.
    private final OkHttpClient client;

    // Gson — parses JSON strings into Java objects
    private final Gson gson;

    // The base URL of the Laravel API.
    // Change this if your server runs on a different port or IP.
    private static final String BASE_URL = "http://localhost:8000/api/v1";

    // The auth token. Set after login, cleared on logout.
    // Attached as "Authorization: Bearer <token>" to every request.
    private String authToken = null;

    // ──────────────────────────────────────────────
    // CONSTRUCTOR — private so nobody can call "new ApiClient()"
    // ──────────────────────────────────────────────
    private ApiClient() {
        // Configure the HTTP client:
        // - 30 second connect timeout (server is down or unreachable)
        // - 60 second read timeout (slow response or large data)
        // - Follow redirects (just in case)
        this.client = new OkHttpClient.Builder()
                .connectTimeout(30, TimeUnit.SECONDS)
                .readTimeout(60, TimeUnit.SECONDS)
                .followRedirects(true)
                .build();

        this.gson = new Gson();
    }
    /*What it is: The actual HTTP tool that sends requests
Configuration explained:
SettingWhat It DoesWhy?connectTimeout(30, SECONDS)Waits max 30 seconds to connect to serverIf server is down, don't wait forever 
readTimeout(60, SECONDS)Waits max 60 seconds for responseIf response is slow/large, don't wait forever
followRedirects(true)Automatically follow HTTP redirectsIf server redirects (301, 302), follow it*/

    /**
     * Get the singleton instance.
     * If it doesn't exist yet, create it.
     */
    public static synchronized ApiClient getInstance() {
        if (instance == null) {
            instance = new ApiClient();
        }
        return instance;
    }

    // ──────────────────────────────────────────────
    // AUTH TOKEN MANAGEMENT
    // ──────────────────────────────────────────────

    /** Store the auth token after login */
    public void setAuthToken(String token) {
        this.authToken = token;
    }

    /** Clear the auth token (on logout) */
    public void clearAuthToken() {
        this.authToken = null;
    }

    /** Check if we have a token */
    public boolean hasAuthToken() {
        return authToken != null && !authToken.isEmpty();
    }

    // ──────────────────────────────────────────────
    // HTTP METHODS
    // ──────────────────────────────────────────────

    /**
     * GET request — fetch data from the API.
     *
     * @param endpoint The API path, e.g. "/topics" or "/me"
     * @return The JSON response as a JsonObject
     * @throws ApiException if the server returns an error or is unreachable
     */
    public JsonObject get(String endpoint) throws ApiException {
        // Build the URL: BASE_URL + endpoint = "http://localhost:8000/api/v1/topics"
        String url = BASE_URL + endpoint;

        // Build the request
        Request.Builder requestBuilder = new Request.Builder()
                .url(url)
                .get();

        // Attach auth token if we have one
        attachToken(requestBuilder);

        // Execute and parse
        return execute(requestBuilder.build());//Sends the request to the server and returns the JSON response.
    }

    /**
     * POST request — create new data.
     *
     * @param endpoint The API path
     * @param body     A JsonObject with the data to send
     * @return The JSON response
     */
    public JsonObject post(String endpoint, JsonObject body) throws ApiException {
        String url = BASE_URL + endpoint;

        // Convert the JsonObject to a JSON string
        String jsonBody = gson.toJson(body);

        // Create the request body with JSON content type
        RequestBody requestBody = RequestBody.create(//package the data for sending
                jsonBody,//the actual data
                MediaType.parse("application/json; charset=utf-8")// tells server "this is JSON, use UTF-8 encoding"
        );

        Request.Builder requestBuilder = new Request.Builder()
                .url(url)
                .post(requestBody);//This is a POST request (sending data to create/change something)

        attachToken(requestBuilder);

        return execute(requestBuilder.build());//Sends the request and returns the JSON response from server.
    }

    /**
     * PUT request — update existing data.
     */
    public JsonObject put(String endpoint, JsonObject body) throws ApiException {
        String url = BASE_URL + endpoint;
        String jsonBody = gson.toJson(body);
        RequestBody requestBody = RequestBody.create(
                jsonBody,
                MediaType.parse("application/json; charset=utf-8")
        );

        Request.Builder requestBuilder = new Request.Builder()
                .url(url)
                .put(requestBody);

        attachToken(requestBuilder);

        return execute(requestBuilder.build());
    }

    /**
     * DELETE request — remove data.
     */
    public JsonObject delete(String endpoint) throws ApiException {
        String url = BASE_URL + endpoint;

        Request.Builder requestBuilder = new Request.Builder()
                .url(url)
                .delete();

        attachToken(requestBuilder);

        return execute(requestBuilder.build());
    }

    /**
     * POST with raw JSON string body (used for multipart or special cases).
     */
    public JsonObject postRaw(String endpoint, String jsonBody) throws ApiException {
        String url = BASE_URL + endpoint;
        RequestBody requestBody = RequestBody.create(
                jsonBody,
                MediaType.parse("application/json; charset=utf-8")
        );

        Request.Builder requestBuilder = new Request.Builder()
                .url(url)
                .post(requestBody);

        attachToken(requestBuilder);

        return execute(requestBuilder.build());
    }

    // ──────────────────────────────────────────────
    // INTERNAL HELPERS
    // ──────────────────────────────────────────────

    /**
     * Attach the Bearer token to a request, if we have one.
     * This adds the header: Authorization: Bearer 1|abc123def456...
     */
    private void attachToken(Request.Builder builder) {
        if (authToken != null && !authToken.isEmpty()) {
            builder.addHeader("Authorization", "Bearer " + authToken);
        }
    }

    /**
     * Execute the HTTP request and parse the response.
     *
     * This is the core method. It:
     * 1. Sends the request to the server
     * 2. Reads the response body
     * 3. Checks for errors (4xx, 5xx)
     * 4. Parses the JSON body
     * 5. Returns the parsed JSON
     */
    private JsonObject execute(Request request) throws ApiException {
        try {
            // Send the request and get the response
            Response response = client.newCall(request).execute();

            // Read the response body as a string
            String responseBody = response.body() != null
                    ? response.body().string()
                    : "{}";

            // Check if the server returned an error (4xx or 5xx status code)
            if (!response.isSuccessful()) {
                // Try to extract the error message from the JSON body
                String errorMessage;
                try {
                    JsonObject errorJson = JsonParser.parseString(responseBody).getAsJsonObject();
                    errorMessage = errorJson.has("message")
                            ? errorJson.get("message").getAsString()
                            : "Unknown error";
                } catch (Exception e) {
                    errorMessage = "Server error (HTTP " + response.code() + ")";
                }

                // Throw an exception with the server's error message
                throw new ApiException(errorMessage, response.code(), responseBody);
            }

            // Parse the JSON response body into a JsonObject
            return JsonParser.parseString(responseBody).getAsJsonObject();

        } catch (ApiException e) {
            // Re-throw API errors (they already have the right message)
            throw e;
        } catch (java.net.ConnectException e) {
            // Server is not running
            throw new ApiException(
                    "Cannot connect to the server. Make sure 'php artisan serve' is running.",
                    0,
                    null
            );
        } catch (java.net.SocketTimeoutException e) {
            // Server didn't respond in time
            throw new ApiException(
                    "Server is not responding. Try again later.",
                    0,
                    null
            );
        } catch (IOException e) {
            // General network error
            throw new ApiException(
                    "Network error: " + e.getMessage(),
                    0,
                    null
            );
        }
    }

    // ──────────────────────────────────────────────
    // GETTER for base URL (used by other classes)
    // ──────────────────────────────────────────────
    public String getBaseUrl() {
        return BASE_URL;
    }
}
```

**Key concepts explained:**

| Concept | What it means |
|---------|---------------|
| `Singleton` | Only ONE instance of ApiClient exists. Everyone shares it. |
| `synchronized` | Prevents two threads from creating the instance at the same time. |
| `OkHttpClient` | The actual HTTP library. Handles connections, timeouts, SSL. |
| `Gson` | Parses JSON. `gson.toJson(obj)` = object → JSON string. `JsonParser.parseString(text)` = text → JSON object. |
| `Bearer token` | The standard way to send auth tokens in HTTP headers. |
| `try-catch` | If the server is down, we catch the error and show a friendly message instead of crashing. |

### Step 4.3: Create ApiException.java

**What it is:** A custom error class. When the API returns an error (wrong password, server down, permission denied), this exception carries the error message and HTTP status code so the UI can show it to the user.

Create `src/main/java/com/yourforum/api/ApiException.java`:

```java
package com.yourforum.api;

/**
 * ApiException — thrown when the API returns an error.
 *
 * Contains:
 * - message: user-friendly error text (e.g. "Invalid credentials")
 * - statusCode: HTTP status code (401, 403, 404, 422, 500, etc.)
 * - responseBody: the raw JSON response (for debugging)
 */
public class ApiException extends Exception {

    private final int statusCode;
    private final String responseBody;

    public ApiException(String message, int statusCode, String responseBody) {
        super(message);
        this.statusCode = statusCode;
        this.responseBody = responseBody;
    }

    public int getStatusCode() {
        return statusCode;
    }

    public String getResponseBody() {
        return responseBody;
    }
}
```

### Step 4.4: Create TokenStorage.java

**What it does:** Remembers the auth token even after the app is closed and reopened. The user logs in once; next time they open the app, they're still logged in.

**How:** Uses `java.util.prefs.Preferences` — a built-in Java feature that stores small data in the Windows Registry (on Windows) or a config file (on Linux/Mac). No files to manage.

Create `src/main/java/com/yourforum/utils/TokenStorage.java`:

```java
package com.yourforum.utils;

import java.util.prefs.Preferences;

/**
 * TokenStorage — saves and loads the auth token between sessions.
 *
 * Uses Java's Preferences API to store the token.
 * On Windows, this stores in the Registry at:
 *   HKCU\Software\JavaSoft\Prefs\com\yourforum
 *
 * Why not a file? The Preferences API:
 * - Survives app restarts
 * - Works on all operating systems
 * - Doesn't leave .token files lying around
 * - Is automatically cleaned up if the JVM is uninstalled
 */
public class TokenStorage {

    // The Preferences node for our app.
    // Think of it as a folder in the Registry where we store our settings.
    private static final Preferences prefs = Preferences.userNodeForPackage(
            com.yourforum.App.class
    );

    // The key under which we store the token
    private static final String TOKEN_KEY = "auth_token";

    /**
     * Save the token to persistent storage.
     * Called after successful login.
     */
    public static void saveToken(String token) {
        prefs.put(TOKEN_KEY, token);
    }

    /**
     * Load the saved token (if any).
     * Called when the app starts to check if the user is still logged in.
     *
     * @return The saved token, or null if none exists.
     */
    public static String loadToken() {
        return prefs.get(TOKEN_KEY, null);
    }

    /**
     * Delete the saved token.
     * Called on logout.
     */
    public static void clearToken() {
        prefs.remove(TOKEN_KEY);
    }

    /**
     * Check if a token exists in storage.
     */
    public static boolean hasToken() {
        return loadToken() != null;
    }
}
```

### Step 4.5: Create AuthManager.java

**What it does:** The single place where login and logout logic lives.

**Why a separate class:** The login flow has special cases:
1. Normal login (200): success, return token + user
2. Warned account (403): user must acknowledge the warning first
3. Blacklisted (403): account is frozen until a date
4. Rate limited (429): too many attempts, wait N seconds

These cases are too complex to inline in the Login screen code.

Create `src/main/java/com/yourforum/api/AuthManager.java`:

```java
package com.yourforum.api;

import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import com.yourforum.models.LoginResponse;
import com.yourforum.models.User;
import com.yourforum.utils.TokenStorage;

/**
 * AuthManager — handles user authentication.
 *
 * Responsibilities:
 * 1. Login: send credentials, receive token + user info
 * 2. Logout: clear token from memory and storage
 * 3. Session restore: load saved token on app start
 * 4. Current user: fetch full profile from /me endpoint
 *
 * Like ApiClient, this is a singleton — one instance shared by the whole app.
 */
public class AuthManager {

    private static AuthManager instance;

    private final ApiClient apiClient;

    // The currently logged-in user (null if not logged in)
    private User currentUser = null;

    /*"Get THE ONLY AuthManager instance
If it doesn't exist yet, create it
If it already exists, return the existing one"
Key word: synchronized means only one thread can access this at a time (thread-safe).*/
    private AuthManager() {
        this.apiClient = ApiClient.getInstance();
    }

    public static synchronized AuthManager getInstance() {
        if (instance == null) {
            instance = new AuthManager();
        }
        return instance;
    }

    /**
     * Login with email and password.
     *
     * This is the MOST IMPORTANT method in the app.
     *
     * Possible outcomes:
     * 1. ✅ 200: LoginResponse with token + user
     * 2. ⚠️ 403 with requires_warning_acknowledgement: warned, must acknowledge
     * 3. ⚠️ 403 (blacklisted): account frozen
     * 4. ❌ 401: wrong credentials
     * 5. ⏳ 429: rate limited
     *
     * @throws ApiException with the server's error message
     * @throws WarnedException if the user must acknowledge a warning first
     */
    public LoginResponse login(String email, String password) throws ApiException {
        // Build the JSON body: {"email": "...", "password": "..."}
        // This matches what Laravel expects in AuthController@login
        JsonObject body = new JsonObject();
        body.addProperty("email", email);
        body.addProperty("password", password);

        try {
            // Send POST /api/v1/login
            JsonObject response = apiClient.post("/login", body);

            // Success! Parse the response.
            String token = response.get("token").getAsString();
            JsonObject userJson = response.getAsJsonObject("user");

            // Save the token to persistent storage
            TokenStorage.saveToken(token);

            // Set the token in ApiClient so all subsequent requests use it
            apiClient.setAuthToken(token);

            // Parse the user from the response
            User user = User.fromLoginJson(userJson);//Converts the JSON user data into a User Java object
            this.currentUser = user;

            return new LoginResponse(token, user);

        } catch (ApiException e) {
            // Handle special case: warned account (403 with user info)
            if (e.getStatusCode() == 403 && e.getResponseBody() != null) {
                try {
                    JsonObject errorJson = JsonParser.parseString(e.getResponseBody()).getAsJsonObject();

                    // Check if this is a "needs warning acknowledgement" case
                    if (errorJson.has("requires_warning_acknowledgement")
                            && errorJson.get("requires_warning_acknowledgement").getAsBoolean()) {

                        // Parse the user from the error response
                        JsonObject userJson = errorJson.getAsJsonObject("user");
                        User warnedUser = User.fromLoginJson(userJson);

                        // Throw a special exception with the user info
                        throw new WarnedException(e.getMessage(), warnedUser);
                    }

                    // Check for blacklisted
                    if (e.getMessage() != null && e.getMessage().toLowerCase().contains("blacklisted")) {
                        throw new ApiException(e.getMessage(), e.getStatusCode(), e.getResponseBody());
                    }
                } catch (WarnedException we) {
                    throw we; // re-throw warned exception
                } catch (Exception parseError) {
                    // Couldn't parse the error body, fall through to default handling
                }
            }

            // Re-throw the original error (bad credentials, rate limited, etc.)
            throw e;
        }
    }

    /**
     * Acknowledge a warning and then log in.
     * Called when the user sees "Your account is warned" and clicks "Acknowledge".
     */
    public LoginResponse acknowledgeWarningAndLogin(String email, String password)
            throws ApiException {

        // First, log in normally (this will fail with 403 warned)
        // But we already know about the warning, so we handle it:
        JsonObject body = new JsonObject();
        body.addProperty("email", email);
        body.addProperty("password", password);

        try {
            JsonObject response = apiClient.post("/login", body);
            // If we get here, login succeeded this time
            String token = response.get("token").getAsString();
            TokenStorage.saveToken(token);
            apiClient.setAuthToken(token);
            User user = User.fromLoginJson(response.getAsJsonObject("user"));
            this.currentUser = user;
            return new LoginResponse(token, user);

        } catch (ApiException e) {
            if (e.getStatusCode() == 403) {
                // This is the warned response. First, acknowledge the warning.
                try {
                    apiClient.post("/warnings/acknowledge", new JsonObject());
                } catch (Exception ex) {
                    throw new ApiException("Failed to acknowledge warning.", 0, null);
                }

                // Now try login again — it should work now
                JsonObject retryResponse = apiClient.post("/login", body);
                String token = retryResponse.get("token").getAsString();
                TokenStorage.saveToken(token);
                apiClient.setAuthToken(token);
                User user = User.fromLoginJson(retryResponse.getAsJsonObject("user"));
                this.currentUser = user;
                return new LoginResponse(token, user);
            }
            throw e;
        }
    }

    /**
     * Logout: clear the token from memory and storage.
     * Also tells the server to revoke the token.
     */
    public void logout() throws ApiException {
        try {
            apiClient.post("/logout", new JsonObject());
        } catch (ApiException e) {
            // Even if the server call fails, clear local state
        } finally {
            apiClient.clearAuthToken();
            TokenStorage.clearToken();
            this.currentUser = null;
        }
    }

    /**
     * Fetch the full user profile from /me endpoint.
     *
     * The /me endpoint returns MORE fields than the login response:
     * - Login: role is a STRING ("Student")
     * - /me: role is an OBJECT {id: 3, name: "Student"}
     *
     * This method fetches the rich profile and updates our cached user.
     */
    public User fetchCurrentUser() throws ApiException {
        JsonObject response = apiClient.get("/me");
        JsonObject userJson = response.getAsJsonObject("user");
        this.currentUser = User.fromMeJson(userJson);
        return this.currentUser;
    }

    /**
     * Get the cached current user (may be null if not logged in).
     * Call fetchCurrentUser() to get the latest data from the server.
     */
    public User getCurrentUser() {
        return currentUser;
    }

    /**
     * Try to restore a session from saved token.
     * Called when the app starts.
     *
     * @return true if a valid session was restored
     */
    public boolean restoreSession() {
        String savedToken = TokenStorage.loadToken();
        if (savedToken == null || savedToken.isEmpty()) {
            return false;
        }

        // Set the token and try to fetch the current user
        apiClient.setAuthToken(savedToken);
        try {
            fetchCurrentUser();
            return true;
        } catch (ApiException e) {
            // Token expired or invalid — clear it
            TokenStorage.clearToken();
            apiClient.clearAuthToken();
            return false;
        }
    }

    /**
     * Check if the user is currently logged in.
     */
    public boolean isLoggedIn() {
        return currentUser != null && apiClient.hasAuthToken();
    }
}
```

### Step 4.6: Create WarnedException.java

This special exception carries the user info even though login failed (because they have an unacknowledged warning):

```java
package com.yourforum.api;

import com.yourforum.models.User;

/**
 * WarnedException — thrown when login fails because the user
 * has an unacknowledged warning.
 *
 * Carries the User object so the UI can show their name/email
 * and offer to acknowledge the warning.
 */
public class WarnedException extends Exception {

    private final User user;

    public WarnedException(String message, User user) {
        super(message);
        this.user = user;
    }

    public User getUser() {
        return user;
    }
}
```

### Step 4.7: Create the Model Classes

**What are Models?** Plain Java classes that hold data. They mirror the JSON that the API returns. Gson fills them automatically when parsing responses.

#### LoginResponse.java

Create `src/main/java/com/yourforum/models/LoginResponse.java`:

```java
package com.yourforum.models;

/**
 * LoginResponse — what the server returns after successful login.
 *
 * JSON shape:
 * {
 *     "message": "Login successful",
 *     "token": "1|abc123def456...",
 *     "user": { "id": 1, "full_name": "...", ... }
 * }
 *
 * We don't need @SerializedName here because we build this manually
 * from the parsed JSON in AuthManager.
 */
public class LoginResponse {

    private final String token;
    private final User user;

    public LoginResponse(String token, User user) {
        this.token = token;
        this.user = user;
    }

    public String getToken() {
        return token;
    }

    public User getUser() {
        return user;
    }
}
```

#### User.java — The Most Important Model

**CRITICAL:** The login endpoint and the `/me` endpoint return DIFFERENT shapes for `role` and `group`:

- **Login/Register:** `"role": "Student"` (a plain string) and `"group": "General"` (a plain string)
- **/me:** `"role": {"id": 3, "name": "Student"}` (an object) and `"group": {"id": 1, "name": "General"}` (an object)

We handle this by storing `role` and `group` as `Object` (which can hold either a String or a JsonObject) and providing helper methods.

Create `src/main/java/com/yourforum/models/User.java`:

```java
package com.yourforum.models;

import com.google.gson.JsonObject;
import com.google.gson.annotations.SerializedName;

/**
 * User — represents a forum user.
 *
 * IMPORTANT: The API returns role/group in TWO different shapes:
 * - Login response:  "role": "Student" (flat string)
 * - /me response:    "role": {"id": 3, "name": "Student"} (object)
 *
 * We handle this by storing role/group as Object and providing
 * helper methods (getRoleName(), getGroupName()) that work with both shapes.
 */
public class User {

    // Fields present in BOTH login and /me responses
    @SerializedName("id")
    private int id;

    @SerializedName("full_name")
    private String fullName;

    @SerializedName("email")
    private String email;

    @SerializedName("account_status")
    private String accountStatus;

    @SerializedName("email_verified_at")
    private String emailVerifiedAt;

    @SerializedName("last_active_at")
    private String lastActiveAt;

    // Login returns STRING, /me returns OBJECT {id, name}
    // We store as Object and check the type when reading
    @SerializedName("role")
    private Object role;

    @SerializedName("group")
    private Object group;

    // Fields present ONLY in /me response
    @SerializedName("profile_picture")
    private String profilePicture;

    @SerializedName("created_at")
    private String createdAt;

    @SerializedName("updated_at")
    private String updatedAt;

    // ──────────────────────────────────────────────
    // FACTORY METHODS — Create User from different API responses
    // ──────────────────────────────────────────────

    /**
     * Create a User from the login response JSON.
     * Login returns role/group as simple strings.
     */
    public static User fromLoginJson(JsonObject json) {
        User user = new User();
        user.id = json.get("id").getAsInt();
        user.fullName = getStringSafe(json, "full_name");
        user.email = getStringSafe(json, "email");
        user.accountStatus = getStringSafe(json, "account_status");
        user.emailVerifiedAt = getStringSafe(json, "email_verified_at");
        user.lastActiveAt = getStringSafe(json, "last_active_at");

        // Role is a string in login response
        if (json.has("role") && !json.get("role").isJsonNull()) {
            user.role = json.get("role").getAsString();
        }

        // Group is a string in login response
        if (json.has("group") && !json.get("group").isJsonNull()) {
            user.group = json.get("group").getAsString();
        }

        return user;
    }

    /**
     * Create a User from the /me response JSON.
     * /me returns role/group as objects {id, name}.
     */
    public static User fromMeJson(JsonObject json) {
        User user = new User();
        user.id = json.get("id").getAsInt();
        user.fullName = getStringSafe(json, "full_name");
        user.email = getStringSafe(json, "email");
        user.accountStatus = getStringSafe(json, "account_status");
        user.emailVerifiedAt = getStringSafe(json, "email_verified_at");
        user.lastActiveAt = getStringSafe(json, "last_active_at");
        user.profilePicture = getStringSafe(json, "profile_picture");
        user.createdAt = getStringSafe(json, "created_at");
        user.updatedAt = getStringSafe(json, "updated_at");

        // Role is an OBJECT in /me response: {"id": 3, "name": "Student"}
        if (json.has("role") && !json.get("role").isJsonNull()) {
            user.role = json.get("role").getAsJsonObject();
        }

        // Group is an OBJECT in /me response: {"id": 1, "name": "General"}
        if (json.has("group") && !json.get("group").isJsonNull()) {
            user.group = json.get("group").getAsJsonObject();
        }

        return user;
    }

    /**
     * Safe string getter — returns null instead of crashing if field is missing.
     */
    private static String getStringSafe(JsonObject json, String key) {
        if (json.has(key) && !json.get(key).isJsonNull()) {
            return json.get(key).getAsString();
        }
        return null;
    }

    // ──────────────────────────────────────────────
    // GETTERS — role/group handle both shapes
    // ──────────────────────────────────────────────

    /**
     * Get the role name regardless of whether it came from login or /me.
     *
     * - Login: role is a String → return it directly
     * - /me: role is a JsonObject {id, name} → extract the "name" field
     */
    public String getRoleName() {
        if (role instanceof String) {
            return (String) role;
        }
        if (role instanceof JsonObject) {
            JsonObject roleObj = (JsonObject) role;
            return roleObj.has("name") ? roleObj.get("name").getAsString() : null;
        }
        return null;
    }

    /**
     * Get the group name regardless of shape.
     */
    public String getGroupName() {
        if (group instanceof String) {
            return (String) group;
        }
        if (group instanceof JsonObject) {
            JsonObject groupObj = (JsonObject) group;
            return groupObj.has("name") ? groupObj.get("name").getAsString() : null;
        }
        return null;
    }

    /**
     * Check if the user has a specific role.
     */
    public boolean hasRole(String roleName) {
        String name = getRoleName();
        return name != null && name.equalsIgnoreCase(roleName);
    }

    /**
     * Check if user is a System Administrator.
     */
    public boolean isSystemAdmin() {
        return hasRole("System Administrator");
    }

    /**
     * Check if user is a Group Administrator.
     */
    public boolean isGroupAdmin() {
        return hasRole("Group Administrator");
    }

    /**
     * Check if user is any kind of admin.
     */
    public boolean isAdmin() {
        return isSystemAdmin() || isGroupAdmin();
    }

    /**
     * Check if user is a Lecturer.
     */
    public boolean isLecturer() {
        return hasRole("Lecturer");
    }

    /**
     * Check if user is a Student.
     */
    public boolean isStudent() {
        return hasRole("Student");
    }

    // ──────────────────────────────────────────────
    // REGULAR GETTERS
    // ──────────────────────────────────────────────

    public int getId() { return id; }
    public String getFullName() { return fullName; }
    public String getEmail() { return email; }
    public String getAccountStatus() { return accountStatus; }
    public String getEmailVerifiedAt() { return emailVerifiedAt; }
    public String getLastActiveAt() { return lastActiveAt; }
    public String getProfilePicture() { return profilePicture; }
    public String getCreatedAt() { return createdAt; }
    public String getUpdatedAt() { return updatedAt; }
}
```

**Why the role/group handling is complex:**

```java
// Login returns:   "role": "Student"
// This is a STRING. Gson puts it in the Object field as a String.

// /me returns:    "role": {"id": 3, "name": "Student"}
// This is an OBJECT. Gson puts it in the Object field as a JsonObject.

// getRoleName() checks: is role a String? Or is it a JsonObject?
// Either way, return the actual name.
```

---

## 5. Phase 2 — Login Screen (Person 1)

**Goal:** Build the login window that talks to the Laravel API.

**Time:** ~4 hours for Person 1.

### Step 5.1: Create the Login View + Controller

In JavaFX, a "View" is the code that builds the screen layout. A "Controller" handles what happens when the user interacts (clicks buttons, types text).

We combine them in one file for simplicity. Here's the pattern:

```java
public class SomeScreenView {
    /**
     * The create() method builds and returns the layout.
     * It's called by App.java or the Dashboard to show this screen.
     */
    public static Parent create(Stage stage) {
        // 1. Create layout container (VBox, HBox, BorderPane, etc.)
        // 2. Create UI elements (Label, TextField, Button, etc.)
        // 3. Wire up event handlers (button.setOnAction(...))
        // 4. Return the layout
    }
}
```

Create `src/main/java/com/yourforum/views/LoginView.java`:

```java
package com.yourforum.views;

import com.yourforum.api.ApiException;
import com.yourforum.api.AuthManager;
import com.yourforum.api.WarnedException;
import com.yourforum.models.LoginResponse;
import com.yourforum.models.User;

import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import javafx.stage.Stage;

/**
 * LoginView — the login screen.
 *
 * WHAT IT DOES:
 * - Shows email and password fields
 * - Sends credentials to POST /api/v1/login
 * - On success: switches to the Dashboard
 * - On warned: offers to acknowledge the warning
 * - On error: shows the error message
 *
 * WHY PROGRAMMATIC UI:
 * We build the layout in Java code instead of FXML.
 * This means you see exactly what creates each element —
 * no mystery XML files to debug.
 */
public class LoginView {

    /**
     * Create the login screen layout.
     *
     * @param stage The main window (needed to switch scenes after login)
     * @return The login screen layout
     */
    public static Parent create(Stage stage) {
        // ── MAIN CONTAINER ──
        // VBox stacks children vertically. 15 = gap between elements.
        VBox root = new VBox(15);
        root.setAlignment(Pos.CENTER);          // Center everything
        root.setPadding(new Insets(40));         // 40px padding around edges
        root.setPrefWidth(400);                  // Preferred width

        // ── TITLE ──
        Label titleLabel = new Label("Smart Discussion Forum");
        titleLabel.setStyle("-fx-font-size: 24px; -fx-font-weight: bold;");

        Label subtitleLabel = new Label("Sign in to your account");
        subtitleLabel.setStyle("-fx-font-size: 14px;");

        // ── EMAIL FIELD ──
        TextField emailField = new TextField();
        emailField.setPromptText("Email address");

        // ── PASSWORD FIELD ──
        // PasswordField hides the characters as the user types
        PasswordField passwordField = new PasswordField();
        passwordField.setPromptText("Password");

        // ── ERROR LABEL ──
        // Hidden by default. Shown when login fails.
        Label errorLabel = new Label();
        errorLabel.setStyle("-fx-text-fill: red; -fx-font-size: 12px;");
        errorLabel.setVisible(false);
        errorLabel.setManaged(false);  // Don't take space when hidden

        // ── LOGIN BUTTON ──
        Button loginButton = new Button("Sign In");
        loginButton.setDefaultButton(true);  // Pressing Enter triggers this button
        loginButton.setPrefWidth(200);

        // ── REGISTER LINK ──
        Hyperlink registerLink = new Hyperlink("Don't have an account? Register");
        registerLink.setStyle("-fx-font-size: 12px;");

        // ── LOADING INDICATOR ──
        // Shows a spinning animation while the API call is in progress
        ProgressIndicator loadingIndicator = new ProgressIndicator();
        loadingIndicator.setVisible(false);
        loadingIndicator.setManaged(false);
        loadingIndicator.setPrefSize(30, 30);

        // ── BUTTON ACTION ──
        // This is where the magic happens.
        // When the user clicks "Sign In", we:
        // 1. Validate fields aren't empty
        // 2. Show loading indicator
        // 3. Start a BACKGROUND thread (so the UI doesn't freeze)
        // 4. Call AuthManager.login()
        // 5. Switch to Dashboard on success
        // 6. Show error on failure
        loginButton.setOnAction(event -> {
            String email = emailField.getText().trim();
            String password = passwordField.getText();

            // Basic validation
            if (email.isEmpty() || password.isEmpty()) {
                showError(errorLabel, "Please enter both email and password.");
                return;
            }

            // Hide any previous error
            hideError(errorLabel);

            // Show loading, disable button
            loadingIndicator.setVisible(true);
            loginButton.setDisable(true);

            // ── BACKGROUND THREAD ──
            // WHY: Network calls can take seconds. If we run them on the
            // JavaFX thread (the one that draws the UI), the whole app
            // freezes until the server responds. The user can't even
            // close the window.
            //
            // Task runs on a background thread. On success/failure,
            // the setOnSucceeded/setOnFailed callbacks run back on
            // the JavaFX thread (so we can update the UI safely).
            //What is a Task? A Task is JavaFX's way of running code on a background thread
            javafx.concurrent.Task<LoginResponse> loginTask =
                    new javafx.concurrent.Task<>() {
                @Override
                protected LoginResponse call() throws Exception {
                    return AuthManager.getInstance().login(email, password);
                }
            };

            // What happens when login succeeds
            loginTask.setOnSucceeded(e -> {
                loadingIndicator.setVisible(false);
                loginButton.setDisable(false);

                // Switch to the dashboard
                // DashboardView is the main app shell (built next phase)
                Scene dashboardScene = new Scene(
                        DashboardView.create(stage),
                        1024, 768
                );
                stage.setScene(dashboardScene);
            });

            // What happens when login fails
            loginTask.setOnFailed(e -> {
                loadingIndicator.setVisible(false);
                loginButton.setDisable(false);

                // Get the exception that was thrown
                Throwable exception = loginTask.getException();

                if (exception instanceof WarnedException) {
                    // The user has an unacknowledged warning.
                    // Offer to acknowledge it and continue.
                    WarnedException warnedEx = (WarnedException) exception;
                    User warnedUser = warnedEx.getUser();

                    Alert alert = new Alert(Alert.AlertType.CONFIRMATION);
                    alert.setTitle("Warning Acknowledgment Required");
                    alert.setHeaderText("Hello " + warnedUser.getFullName());
                    alert.setContentText(
                            "Your account has an unacknowledged warning. " +
                            "You must acknowledge it before you can log in. " +
                            "Do you want to acknowledge this warning?"
                    );

                    alert.showAndWait().ifPresent(response -> {
                        if (response == ButtonType.OK) {
                            // User agreed — acknowledge warning and retry login
                            handleWarnedLogin(stage, email, password, errorLabel,
                                    loadingIndicator, loginButton);
                        }
                    });

                } else if (exception instanceof ApiException) {
                    ApiException apiEx = (ApiException) exception;
                    showError(errorLabel, apiEx.getMessage());
                } else {
                    showError(errorLabel, "An unexpected error occurred: "
                            + exception.getMessage());
                }
            });

            // START THE BACKGROUND THREAD
            new Thread(loginTask).start();
        });

        // ── REGISTER LINK ACTION ──
        registerLink.setOnAction(e -> {
            Scene registerScene = new Scene(
                    RegisterView.create(stage), 1024, 768
            );
            stage.setScene(registerScene);
        });

        // ── ASSEMBLE THE LAYOUT ──
        // Order matters: they appear top-to-bottom
        root.getChildren().addAll(
                titleLabel,
                subtitleLabel,
                emailField,
                passwordField,
                errorLabel,
                loginButton,
                loadingIndicator,
                registerLink
        );

        return root;
    }

    /**
     * Handle login for a warned user who just acknowledged the warning.
     */
    private static void handleWarnedLogin(Stage stage, String email, String password,
                                           Label errorLabel, ProgressIndicator loading,
                                           Button loginButton) {
        loading.setVisible(true);
        loginButton.setDisable(true);

        javafx.concurrent.Task<LoginResponse> task =
                new javafx.concurrent.Task<>() {
            @Override
            protected LoginResponse call() throws Exception {
                return AuthManager.getInstance()
                        .acknowledgeWarningAndLogin(email, password);
            }
        };

        task.setOnSucceeded(e -> {
            loading.setVisible(false);
            loginButton.setDisable(false);
            stage.setScene(new Scene(
                    DashboardView.create(stage), 1024, 768
            ));
        });

        task.setOnFailed(e -> {
            loading.setVisible(false);
            loginButton.setDisable(false);
            showError(errorLabel, task.getException().getMessage());
        });

        new Thread(task).start();
    }

    /**
     * Show an error message on the login form.
     */
    private static void showError(Label errorLabel, String message) {
        errorLabel.setText(message);
        errorLabel.setVisible(true);
        errorLabel.setManaged(true);
    }

    /**
     * Hide the error message.
     */
    private static void hideError(Label errorLabel) {
        errorLabel.setVisible(false);
        errorLabel.setManaged(false);
    }
}
```

### Step 5.2: Create the Register View

A simpler screen for creating a new account.

Create `src/main/java/com/yourforum/views/RegisterView.java`:

```java
package com.yourforum.views;

import com.google.gson.JsonObject;
import com.yourforum.api.ApiClient;
import com.yourforum.api.ApiException;
import com.yourforum.api.AuthManager;
import com.yourforum.models.LoginResponse;
import com.yourforum.models.User;

import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import javafx.stage.Stage;

/**
 * RegisterView — new user registration screen.
 *
 * Calls POST /api/v1/register with:
 *   { "full_name": "...", "email": "...", "password": "...", "password_confirmation": "..." }
 *
 * On success, auto-logs in and goes to Dashboard.
 */
public class RegisterView {

    public static Parent create(Stage stage) {
        VBox root = new VBox(15);
        root.setAlignment(Pos.CENTER);
        root.setPadding(new Insets(40));
        root.setPrefWidth(400);

        Label titleLabel = new Label("Create Account");
        titleLabel.setStyle("-fx-font-size: 24px; -fx-font-weight: bold;");

        TextField nameField = new TextField();
        nameField.setPromptText("Full name");

        TextField emailField = new TextField();
        emailField.setPromptText("Email address");

        PasswordField passwordField = new PasswordField();
        passwordField.setPromptText("Password (min 8 characters)");

        PasswordField confirmField = new PasswordField();
        confirmField.setPromptText("Confirm password");

        Label errorLabel = new Label();
        errorLabel.setStyle("-fx-text-fill: red; -fx-font-size: 12px;");
        errorLabel.setVisible(false);

        Button registerButton = new Button("Create Account");
        registerButton.setDefaultButton(true);
        registerButton.setPrefWidth(200);

        Hyperlink loginLink = new Hyperlink("Already have an account? Sign in");

        ProgressIndicator loading = new ProgressIndicator();
        loading.setVisible(false);

        // ── REGISTER BUTTON ACTION ──
        registerButton.setOnAction(e -> {
            String name = nameField.getText().trim();
            String email = emailField.getText().trim();
            String password = passwordField.getText();
            String confirm = confirmField.getText();

            // Validate
            if (name.isEmpty() || email.isEmpty() || password.isEmpty()) {
                showError(errorLabel, "All fields are required.");
                return;
            }
            if (!password.equals(confirm)) {
                showError(errorLabel, "Passwords do not match.");
                return;
            }
            if (password.length() < 8) {
                showError(errorLabel, "Password must be at least 8 characters.");
                return;
            }

            hideError(errorLabel);
            loading.setVisible(true);
            registerButton.setDisable(true);

            // Build the JSON body matching what AuthController@register expects
            JsonObject body = new JsonObject();
            body.addProperty("full_name", name);
            body.addProperty("email", email);
            body.addProperty("password", password);
            body.addProperty("password_confirmation", confirm);

            javafx.concurrent.Task<JsonObject> task = new javafx.concurrent.Task<>() {
                @Override
                protected JsonObject call() throws Exception {
                    return ApiClient.getInstance().post("/register", body);
                }
            };

            task.setOnSucceeded(event -> {
                loading.setVisible(false);
                registerButton.setDisable(false);

                // Parse the registration response
                JsonObject response = task.getValue();
                String token = response.get("token").getAsString();
                JsonObject userJson = response.getAsJsonObject("user");
                User user = User.fromLoginJson(userJson);

                // Set the auth state
                ApiClient.getInstance().setAuthToken(token);
                com.yourforum.utils.TokenStorage.saveToken(token);

                // Go to dashboard
                stage.setScene(new Scene(
                        DashboardView.create(stage), 1024, 768
                ));
            });

            task.setOnFailed(event -> {
                loading.setVisible(false);
                registerButton.setDisable(false);
                showError(errorLabel, task.getException().getMessage());
            });

            new Thread(task).start();
        });

        loginLink.setOnAction(e -> {
            stage.setScene(new Scene(LoginView.create(stage), 1024, 768));
        });

        root.getChildren().addAll(
                titleLabel, nameField, emailField,
                passwordField, confirmField, errorLabel,
                registerButton, loading, loginLink
        );

        return root;
    }

    private static void showError(Label label, String msg) {
        label.setText(msg);
        label.setVisible(true);
    }

    private static void hideError(Label label) {
        label.setText("");
        label.setVisible(false);
    }
}
```

### Step 5.3: Create AlertHelper.java

A utility that shows popup dialogs (error, success, confirmation). Every team member will use this.

Create `src/main/java/com/yourforum/utils/AlertHelper.java`:

```java
package com.yourforum.utils;

import javafx.scene.control.Alert;
import javafx.scene.control.ButtonType;

import java.util.Optional;

/**
 * AlertHelper — shows popup dialog boxes.
 *
 * WHY: Showing error dialogs requires 5+ lines of boilerplate every time.
 * This class reduces it to one line: AlertHelper.showError("Title", "Message");
 *
 * Everyone on the team should use this instead of raw Alert dialogs.
 */
public class AlertHelper {

    /**
     * Show an error dialog.
     */
    public static void showError(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.ERROR);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    /**
     * Show a success/info dialog.
     */
    public static void showInfo(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    /**
     * Show a confirmation dialog (Yes/No).
     *
     * @return true if the user clicked OK/Yes
     */
    public static boolean showConfirmation(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        Optional<ButtonType> result = alert.showAndWait();
        return result.isPresent() && result.get() == ButtonType.OK;
    }

    /**
     * Show a warning dialog.
     */
    public static void showWarning(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.WARNING);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }
}
```

### Step 5.4: Update App.java to Show Login First

Now update `App.java` to try restoring a session first. If a saved token exists and is valid, go straight to Dashboard. Otherwise, show the login screen:

```java
package com.yourforum;

import com.yourforum.api.AuthManager;
import com.yourforum.views.DashboardView;
import com.yourforum.views.LoginView;

import javafx.application.Application;
import javafx.scene.Scene;
import javafx.stage.Stage;

public class App extends Application {

    @Override
    public void start(Stage stage) {
        stage.setTitle("Smart Discussion Forum");
        stage.setWidth(1024);
        stage.setHeight(768);

        // Try to restore a previous session (saved token)
        boolean sessionRestored = AuthManager.getInstance().restoreSession();

        Scene scene;
        if (sessionRestored) {
            // User was logged in — go straight to dashboard
            scene = new Scene(DashboardView.create(stage), 1024, 768);
        } else {
            // No saved session — show login
            scene = new Scene(LoginView.create(stage), 1024, 768);
        }

        // Apply CSS
        String css = getClass().getResource("/styles/app.css").toExternalForm();
        scene.getStylesheets().add(css);

        stage.setScene(scene);
        stage.show();
    }

    public static void main(String[] args) {
        launch();
    }
}
```

### Step 5.5: TEST — Run and Login

1. Start the Laravel server: In your Laravel project root, run:
   ```cmd
   php artisan serve
   ```

2. Open a NEW terminal in the `smart-discussion-forum-desktop` folder and run:
   ```cmd
   mvnw.cmd clean javafx:run
   ```

3. The desktop app opens. Enter your email and password.

4. **What should happen:**
   - If credentials are correct → app switches to a blank dashboard
   - If wrong → red error text "Invalid credentials"
   - If server is not running → "Cannot connect to the server"
   - If warned → confirmation dialog to acknowledge warning

---

## 6. Phase 3 — Dashboard Shell (Person 1, Others Extend)

**Goal:** The main app window with a sidebar navigation and a content area.

**Note:** `DashboardView` is referenced in LoginView but doesn't exist yet. We're creating it now.

### Step 6.1: Create DashboardView.java

This is the main app shell. It has:
- A **sidebar** on the left with navigation buttons
- A **content area** on the right that changes based on which button is clicked

```java
package com.yourforum.views;

import com.yourforum.api.AuthManager;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.layout.*;
import javafx.stage.Stage;

/**
 * DashboardView — the main app window after login.
 *
 * LAYOUT:
 * ┌─────────────┬──────────────────────────────────┐
 * │  SIDEBAR    │     CONTENT AREA                 │
 * │             │                                  │
 * │  📋 Topics  │  (changes based on which         │
 * │  💬 Chat    │   sidebar button was clicked)    │
 * │  📝 Quizzes │                                  │
 * │  👥 Groups  │                                  │
 * │  🔔 Notifs  │                                  │
 * │  ⚙️ Admin   │                                  │
 * │             │                                  │
 * │  🚪 Logout  │                                  │
 * └─────────────┴──────────────────────────────────┘
 *
 * The sidebar buttons are pre-wired. As each team member builds
 * their feature, they just need to:
 * 1. Create their View class
 * 2. Add a button to the sidebar
 * 3. Wire it to switch the content area
 */
public class DashboardView {

    // The content area — this is what sidebar buttons swap out
    /*StackPane is a layout that stacks things on top of each other. We use it as a "slot" — when you click a sidebar button, we clear the slot and put a new screen in it.

Why static: Every screen needs to replace the content area. If a view is deep inside a topic, and the user clicks "Notifications" in the sidebar, the notification screen needs to replace whatever is currently showing. Since contentArea is static, any code anywhere can call DashboardView.getContentArea().clear() and add their own content.*/
    private static StackPane contentArea;


    /**
     * Build the dashboard layout.
     */
    public static Parent create(Stage stage) {
        // ── MAIN LAYOUT: BorderPane ──
        // BorderPane has 5 regions: top, bottom, left, center, right.
        // We put the sidebar on the LEFT and content in the CENTER.
        BorderPane root = new BorderPane();

        // ── SIDEBAR ──
        VBox sidebar = new VBox(5);  // 5px gap between buttons
        sidebar.getStyleClass().add("sidebar");
        sidebar.setPrefWidth(200);
        sidebar.setPadding(new Insets(10));

        // User info at the top of sidebar
        Label userLabel = new Label(
                AuthManager.getInstance().getCurrentUser() != null
                        ? AuthManager.getInstance().getCurrentUser().getFullName()
                        : "User"
        );
        userLabel.setStyle("-fx-text-fill: white; -fx-font-weight: bold; -fx-font-size: 14px;");
        VBox.setMargin(userLabel, new Insets(0, 0, 10, 0));

        // ── SIDEBAR NAVIGATION BUTTONS ──
        // Each button, when clicked, changes the content area.

        // Separator line
        Region separator1 = new Region();
        separator1.setPrefHeight(1);
        separator1.setStyle("-fx-background-color: #34495e;");
        VBox.setMargin(separator1, new Insets(5, 0, 5, 0));

        Button topicsBtn = createSidebarButton("📋  Topics");
        Button chatBtn = createSidebarButton("💬  Chat");
        Button quizzesBtn = createSidebarButton("📝  Quizzes");

        Region separator2 = new Region();
        separator2.setPrefHeight(1);
        separator2.setStyle("-fx-background-color: #34495e;");
        VBox.setMargin(separator2, new Insets(5, 0, 5, 0));

        Button groupsBtn = createSidebarButton("👥  Groups");
        Button notificationsBtn = createSidebarButton("🔔  Notifications");
        Button adminBtn = createSidebarButton("⚙️  Admin");

        // Spacer pushes the logout button to the bottom
        Region spacer = new Region();
        VBox.setVgrow(spacer, Priority.ALWAYS);

        Button logoutBtn = createSidebarButton("🚪  Logout");
        logoutBtn.setStyle(logoutBtn.getStyle() + "-fx-text-fill: #e74c3c;");

        // ── CONTENT AREA ──
        // StackPane holds whatever screen is currently active.
        // Initially shows a welcome message.
        contentArea = new StackPane();
        Label welcomeLabel = new Label("Welcome to Smart Discussion Forum");
        welcomeLabel.setStyle("-fx-font-size: 18px;");
        contentArea.getChildren().add(welcomeLabel);

        // ── WIRE SIDEBAR BUTTONS ──
        // Each button replaces the content area with its screen.

        // Topic button → Person 2 will replace this with TopicListView
        topicsBtn.setOnAction(e -> {
            contentArea.getChildren().clear();
            Label placeholder = new Label("Topics — coming soon");
            placeholder.setStyle("-fx-font-size: 16px;");
            contentArea.getChildren().add(placeholder);
        });

        // Chat button → Person 2 will replace with ConversationListView
        chatBtn.setOnAction(e -> {
            contentArea.getChildren().clear();
            Label placeholder = new Label("Chat — coming soon");
            placeholder.setStyle("-fx-font-size: 16px;");
            contentArea.getChildren().add(placeholder);
        });

        // Quizzes button → Person 3 will replace with QuizListView
        quizzesBtn.setOnAction(e -> {
            contentArea.getChildren().clear();
            Label placeholder = new Label("Quizzes — coming soon");
            placeholder.setStyle("-fx-font-size: 16px;");
            contentArea.getChildren().add(placeholder);
        });

        // Groups button → Person 4 will replace with GroupListView
        groupsBtn.setOnAction(e -> {
            contentArea.getChildren().clear();
            Label placeholder = new Label("Groups — coming soon");
            placeholder.setStyle("-fx-font-size: 16px;");
            contentArea.getChildren().add(placeholder);
        });

        // Notifications button → Person 1 will replace
        notificationsBtn.setOnAction(e -> {
            contentArea.getChildren().clear();
            showNotificationsPlaceholder();
        });

        // Admin button → Person 5 will replace
        adminBtn.setOnAction(e -> {
            contentArea.getChildren().clear();
            Label placeholder = new Label("Admin — coming soon");
            placeholder.setStyle("-fx-font-size: 16px;");
            contentArea.getChildren().add(placeholder);
        });

        // Logout
        logoutBtn.setOnAction(e -> {
            boolean confirmed = com.yourforum.utils.AlertHelper.showConfirmation(
                    "Logout",
                    "Are you sure you want to log out?"
            );
            if (confirmed) {
                try {
                    AuthManager.getInstance().logout();
                } catch (Exception ex) {
                    // Logout failed on server side, but local state is cleared
                }
                // Go back to login screen
                stage.setScene(new javafx.scene.Scene(
                        LoginView.create(stage), 1024, 768
                ));
            }
        });

        // ── ASSEMBLE SIDEBAR ──
        sidebar.getChildren().addAll(
                userLabel,
                separator1,
                topicsBtn, chatBtn, quizzesBtn,
                separator2,
                groupsBtn, notificationsBtn, adminBtn,
                spacer,
                logoutBtn
        );

        // ── ASSEMBLE MAIN LAYOUT ──
        root.setLeft(sidebar);
        root.setCenter(contentArea);

        return root;
    }

    /**
     * Helper to get the content area so other views can replace it.
     * Other team members will call this to swap in their screens.
     */
    public static StackPane getContentArea() {
        return contentArea;
    }

    /**
     * Create a styled sidebar button.
     */
    private static Button createSidebarButton(String text) {
        Button button = new Button(text);
        button.setMaxWidth(Double.MAX_VALUE);  // Stretch to full width
        button.setAlignment(Pos.CENTER_LEFT);
        button.setPrefHeight(40);
        return button;
    }

    /**
     * Show notifications placeholder.
     * Person 1 will replace this with the actual notification list.
     */
    private static void showNotificationsPlaceholder() {
        VBox notifBox = new VBox(10);
        notifBox.setPadding(new Insets(20));

        Label title = new Label("Notifications");
        title.setStyle("-fx-font-size: 18px; -fx-font-weight: bold;");

        Label placeholder = new Label("Notification list will appear here.");
        placeholder.setStyle("-fx-font-size: 14px;");

        notifBox.getChildren().addAll(title, placeholder);
        contentArea.getChildren().add(notifBox);
    }
}
```

### Step 6.2: IMPORTANT — How the Navigation Works

When Person 2 creates `TopicListView.java`, they wire it into the dashboard by editing `DashboardView.java`:

```java
// In DashboardView.java, replace the topics button handler:
topicsBtn.setOnAction(e -> {
    contentArea.getChildren().clear();
    contentArea.getChildren().add(TopicListView.create());
});
```

The entire app works this way. Each person builds their feature as a `create()` method that returns a `Parent` (layout), and they plug it into the sidebar.

---

## 7. Code Deep Dive — How Each File Connects to the Backend

Before splitting up, the whole team needs to understand how the existing Person 1 code works. Read this section together.

### 7.1 ApiClient.java — The HTTP Engine

**File location:** `api/ApiClient.java`
**What it does:** Every single HTTP request to the Laravel server goes through this class.

**How it connects to the backend:**

```
User clicks button
    → View calls ApiClient.get("/topics")
    → ApiClient builds: GET http://localhost:8000/api/v1/topics
    → Adds header: Authorization: Bearer 1|abc123...
    → Sends request via OkHttp library
    → Laravel processes it and returns JSON
    → ApiClient returns the JSON as a Java JsonObject
```

**Key sections explained:**

```java
private static ApiClient instance;
```
This is the singleton instance. Only ONE ApiClient exists for the whole app. Everyone shares it. That means everyone shares the same auth token, the same server URL, and the same HTTP settings. If you change the token (login/logout), every screen sees the change immediately.

```java
private static final String BASE_URL = "http://localhost:8000/api/v1";
```
This is where all requests go. Change this if your server runs on a different port or you deploy to production. For example, if you deploy to `https://forum.example.com`, change this to `https://forum.example.com/api/v1`.

```java
private String authToken = null;
```
The Bearer token. Set after login, cleared on logout. Every request checks this and adds `Authorization: Bearer <token>` to the header. This is how the Laravel server knows who you are.

```java
public JsonObject get(String endpoint) throws ApiException
```
The GET method. You pass it the endpoint path like `"/topics"` and it:
1. Builds the full URL: `http://localhost:8000/api/v1/topics`
2. Calls `attachToken()` which adds the Bearer header
3. Calls `execute()` which sends the request and handles errors

The `post()`, `put()`, and `delete()` methods work the same way but with different HTTP methods.

```java
private JsonObject execute(Request request) throws ApiException
```
This is where the actual network call happens. It:
1. Calls `client.newCall(request).execute()` — this sends the HTTP request and waits for the response
2. Checks `response.isSuccessful()` — if the HTTP status is 200-299, it's success; 400-599 means an error
3. If error: reads the `message` field from the JSON body and throws an `ApiException`
4. If success: parses the JSON body and returns it as a `JsonObject`

**How Person 2 calls it:**
```java
// In TopicListView.java — this is how everyone fetches data:
JsonObject response = ApiClient.getInstance().get("/topics");
JsonArray topicsArray = response.getAsJsonArray("data");
// Now parse each topic...
```

### 7.2 AuthManager.java — The Login Brain

**File location:** `api/AuthManager.java`
**What it does:** Handles the entire login/logout workflow. This is the most complex piece because the login endpoint can return 4 different outcomes.

**How it connects to the backend:**

**Login flow (normal case):**
```
LoginView.java calls: AuthManager.login("john@email.com", "password123")

AuthManager builds JSON: {"email": "john@email.com", "password": "password123"}
AuthManager calls: ApiClient.post("/login", body)

Laravel receives POST /api/v1/login
    → Validates email + password
    → Checks if blacklisted → returns 403
    → Checks if warned → returns 403 with user data
    → Checks credentials → wrong = 401, correct = 200
    → If 200: creates Sanctum token, returns:
      {
        "message": "Login successful",
        "token": "1|abc123...",
        "user": {
          "id": 1,
          "full_name": "John Doe",
          "email": "john@email.com",
          "account_status": "active",
          "role": "Student",      ← Note: STRING, not object
          "group": "Group A"       ← Note: STRING, not object
        }
      }

AuthManager receives the response:
    → Saves token to TokenStorage (Preferences API)
    → Sets token in ApiClient (so all future requests use it)
    → Parses user from JSON using User.fromLoginJson()
    → Returns LoginResponse(token, user)
```

**Why the warned account flow is special:**

When a user has an unacknowledged warning, the Laravel API returns HTTP 403 (not 200) but still includes user data:
```json
{
  "message": "Your account is warned...",
  "requires_warning_acknowledgement": true,
  "user": { "id": 1, "full_name": "John Doe", ... }
}
```

AuthManager catches this specific case:
```java
if (e.getStatusCode() == 403 && errorJson.has("requires_warning_acknowledgement")) {
    User warnedUser = User.fromLoginJson(userJson);
    throw new WarnedException(e.getMessage(), warnedUser);
}
```

Then `LoginView` catches the `WarnedException` and shows a confirmation dialog asking the user to acknowledge the warning. If they click OK, it calls `AuthManager.acknowledgeWarningAndLogin()` which:
1. Calls `POST /api/v1/warnings/acknowledge` first
2. Then calls `POST /api/v1/login` again — this time it succeeds

### 7.3 User.java — Handling Two Different API Shapes

**File location:** `models/User.java`
**What it does:** Represents a forum user. But there's a catch — the login endpoint and the `/me` endpoint return `role` and `group` in DIFFERENT shapes.

**The problem:**
```
Login returns:     "role": "Student"              (a plain string)
                    "group": "General"             (a plain string)

/me returns:       "role": {"id": 3, "name": "Student"}    (an object)
                    "group": {"id": 1, "name": "General"}   (an object)
```

**How the code handles this:**

```java
@SerializedName("role")
private Object role;  // Can be EITHER a String OR a JsonObject
```

The `Object` type means Java doesn't care what shape it is. Then we provide a helper method that checks the actual type at runtime:

```java
public String getRoleName() {
    if (role instanceof String) {
        return (String) role;  // It's from login — just return it
    }
    if (role instanceof JsonObject) {
        JsonObject roleObj = (JsonObject) role;
        return roleObj.get("name").getAsString();  // It's from /me — extract name
    }
    return null;
}
```

**Two factory methods handle the two JSON shapes:**

```java
// Call this when parsing login response:
User user = User.fromLoginJson(loginJson);

// Call this when parsing /me response:
User user = User.fromMeJson(meJson);
```

The `fromLoginJson()` stores `role` as a String. The `fromMeJson()` stores `role` as a JsonObject. Both work with `getRoleName()`.

### 7.4 LoginView.java — The Login Screen

**File location:** `views/LoginView.java`
**What it does:** Builds the login form and handles the login button click.

**How it connects to the backend:**

The login button has an `setOnAction` handler. When clicked, it:

1. Reads email and password from the text fields
2. Validates they're not empty
3. Creates a **Task** (background thread) — this prevents the UI from freezing during the network call
4. Inside the Task, calls `AuthManager.getInstance().login(email, password)`
5. When the Task succeeds (`setOnSucceeded`), switches the window to the Dashboard
6. When the Task fails (`setOnFailed`), checks what kind of error it was and shows the right message

**Why a Task (background thread) is necessary:**
```java
javafx.concurrent.Task<LoginResponse> loginTask = new javafx.concurrent.Task<>() {
    @Override
    protected LoginResponse call() throws Exception {
        // This runs on a BACKGROUND thread
        // Network calls can take 1-30 seconds
        // If we ran this on the main thread, the whole app would freeze
        return AuthManager.getInstance().login(email, password);
    }
};

loginTask.setOnSucceeded(e -> {
    // This runs on the JavaFX thread — safe to update UI here
    stage.setScene(new Scene(DashboardView.create(stage), 1024, 768));
});

loginTask.setOnFailed(e -> {
    // Also runs on JavaFX thread
    Throwable ex = loginTask.getException();
    if (ex instanceof WarnedException) {
        // Show acknowledge warning dialog
    } else if (ex instanceof ApiException) {
        // Show error message
    }
});

new Thread(loginTask).start();  // Start the background thread
```

**Every screen in the app follows this same Task pattern** for network calls.

### 7.5 DashboardView.java — The App Shell

**File location:** `views/DashboardView.java`
**What it does:** The main window after login. Has a sidebar with navigation buttons and a content area.

**How it works:**

The layout is a `BorderPane` with:
- **Left:** `VBox` containing sidebar buttons
- **Center:** `StackPane` that gets swapped out when you click a button

When a sidebar button is clicked:
```java
topicsBtn.setOnAction(e -> {
    contentArea.getChildren().clear();       // Remove whatever's there
    contentArea.getChildren().add(TopicListView.create());  // Add the new screen
});
```

The `contentArea` is a `static` field so ANY view can replace it:
```java
// From any view file, you can do:
DashboardView.getContentArea().getChildren().clear();
DashboardView.getContentArea().getChildren().add(MyNewScreen.create());
```

### 7.6 TokenStorage.java — Remembering Login

**File location:** `utils/TokenStorage.java`
**What it does:** Saves the auth token so the user doesn't have to log in every time they open the app.

**How it connects to the backend (indirectly):**

```java
// After successful login:
TokenStorage.saveToken("1|abc123...");
// This stores the token in Windows Registry (or a file on Linux/Mac)

// When app starts next time:
String savedToken = TokenStorage.loadToken();  // Retrieves the token
ApiClient.getInstance().setAuthToken(savedToken);  // Sets it
// Now try fetching /me — if it works, session is valid
// If it fails with 401, token expired — show login screen
```

This is checked in `App.java` when the app starts:
```java
boolean sessionRestored = AuthManager.getInstance().restoreSession();
if (sessionRestored) {
    // Go straight to dashboard
} else {
    // Show login screen
}
```

### 7.7 AlertHelper.java — Popup Dialogs

**File location:** `utils/AlertHelper.java`
**What it does:** Shows error, info, warning, and confirmation popup dialogs. Every team member uses this.

**How it's used:**
```java
// Show error:
AlertHelper.showError("Login Failed", "Invalid email or password.");

// Ask user to confirm:
boolean confirmed = AlertHelper.showConfirmation("Delete", "Are you sure?");
if (confirmed) {
    // Delete the item
}
```

---

## 8. Person 1 — Remaining Screens: Notifications, Profile, Password

Person 1 already built: ApiClient, AuthManager, TokenStorage, User, LoginView, RegisterView, DashboardView, AlertHelper.

Now build these three remaining screens:

### 8.1 NotificationListView.java

**File:** `views/NotificationListView.java`
**What it does:** Shows a list of notifications for the logged-in user.
**Backend endpoints:** `GET /api/v1/me/notifications`, `GET /api/v1/me/notifications/unread-count`
**Response shape:**
```json
{
    "data": [
        {
            "id": 1,
            "type": "topic_reply",
            "data": { "topic_id": 5, "post_id": 12 },
            "read_at": null,
            "created_at": "2025-01-15T10:30:00"
        }
    ]
}
```

```java
package com.yourforum.views;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.yourforum.api.ApiClient;
import com.yourforum.api.ApiException;
import com.yourforum.utils.AlertHelper;
import javafx.geometry.Insets;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;

/**
 * NotificationListView — shows the user's notifications.
 *
 * CONNECTS TO BACKEND:
 *   GET /api/v1/me/notifications          → list all notifications
 *   GET /api/v1/me/notifications/unread-count → badge count in sidebar
 *   POST /api/v1/notifications/{id}/read  → mark single as read
 *   POST /api/v1/notifications/read-all   → mark all as read
 *
 * HOW DATA FLOWS:
 *   Laravel DB → NotificationController → JSON → ApiClient → JsonArray → ListView
 */
public class NotificationListView {

    public static Parent create() {
        VBox root = new VBox(10);
        root.setPadding(new Insets(20));

        Label titleLabel = new Label("Notifications");
        titleLabel.setStyle("-fx-font-size: 20px; -fx-font-weight: bold;");

        // Mark all read button — sends POST to backend
        Button markAllReadBtn = new Button("Mark All as Read");
        markAllReadBtn.setOnAction(e -> markAllRead());

        // ListView shows notifications as rows.
        // Each row shows the notification type and creation time.
        ListView<String> listView = new ListView<>();
        listView.setPrefHeight(600);

        // Double-click a notification to mark it as read
        listView.setOnMouseClicked(e -> {
            if (e.getClickCount() == 2) {
                int selectedIndex = listView.getSelectionModel().getSelectedIndex();
                if (selectedIndex >= 0) {
                    // We need the notification ID. We'll store notification IDs
                    // in a separate list alongside the displayed strings.
                }
            }
        });

        ProgressIndicator loading = new ProgressIndicator();

        // Load notifications in background thread
        javafx.concurrent.Task<Void> loadTask = new javafx.concurrent.Task<>() {
            @Override
            protected Void call() throws Exception {
                // Step 1: Call the API
                // ApiClient sends: GET http://localhost:8000/api/v1/me/notifications
                // Laravel responds with JSON array of notifications
                JsonObject response = ApiClient.getInstance().get("/me/notifications");
                
                // Step 2: Extract the "data" array from the JSON
                // The response looks like: { "data": [ {...}, {...} ] }
                JsonArray dataArray = response.getAsJsonArray("data");
                
                // Step 3: Convert each notification JSON to a display string
                // And store IDs in a parallel list so we can mark them as read later
                java.util.List<String> displayItems = new java.util.ArrayList<>();
                for (JsonElement element : dataArray) {
                    JsonObject notif = element.getAsJsonObject();
                    String type = notif.get("type").getAsString();
                    String readAt = notif.has("read_at") && !notif.get("read_at").isJsonNull()
                            ? notif.get("read_at").getAsString()
                            : "Unread";
                    String createdAt = notif.get("created_at").getAsString();
                    
                    displayItems.add("[" + readAt + "] " + type + " — " + createdAt);
                }
                
                // Step 4: Update the ListView on the JavaFX thread
                javafx.application.Platform.runLater(() -> {
                    listView.getItems().setAll(displayItems);
                });
                
                return null;
            }
        };

        loadTask.setOnSucceeded(e -> loading.setVisible(false));
        loadTask.setOnFailed(e -> {
            loading.setVisible(false);
            AlertHelper.showError("Error", "Failed to load notifications.");
        });

        root.getChildren().addAll(titleLabel, markAllReadBtn, loading, listView);

        // Start loading
        loading.setVisible(true);
        new Thread(loadTask).start();

        return root;
    }

    private static void markAllRead() {
        javafx.concurrent.Task<Void> task = new javafx.concurrent.Task<>() {
            @Override
            protected Void call() throws Exception {
                // POST /api/v1/notifications/read-all — marks everything read
                // Empty JSON body because the endpoint doesn't need parameters
                ApiClient.getInstance().post("/notifications/read-all", new JsonObject());
                return null;
            }
        };
        task.setOnSucceeded(e -> AlertHelper.showInfo("Done", "All notifications marked as read."));
        task.setOnFailed(e -> AlertHelper.showError("Error", "Failed to mark as read."));
        new Thread(task).start();
    }
}
```

**Key concept — how data flows from Laravel to the ListView:**

```
1. User opens Notifications screen
2. Task runs in background thread
3. ApiClient.get("/me/notifications") sends HTTP request
4. Laravel's NotificationController queries the database
5. Laravel returns JSON: { "data": [ { "id": 1, "type": "reply", ... } ] }
6. The Task parses the JSON array: response.getAsJsonArray("data")
7. The Task converts each JSON object to a display string
8. Platform.runLater() updates the ListView on the JavaFX thread
9. User sees their notifications
```

### 8.2 ProfileView.java

**File:** `views/ProfileView.java`
**What it does:** Shows the current user's profile and lets them edit their name/email.
**Backend endpoints:** `GET /api/v1/me`, `POST /api/v1/profile`
**Response shapes:**
```json
// GET /me:  { "user": { "id": 1, "full_name": "John", "email": "john@...", ... } }
// POST /profile body: { "full_name": "John Updated", "email": "john@newemail.com" }
```

```java
package com.yourforum.views;

import com.google.gson.JsonObject;
import com.yourforum.api.ApiClient;
import com.yourforum.api.ApiException;
import com.yourforum.models.User;
import com.yourforum.utils.AlertHelper;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.GridPane;
import javafx.scene.layout.VBox;

/**
 * ProfileView — view and edit profile.
 *
 * CONNECTS TO BACKEND:
 *   GET /api/v1/me        → loads current profile
 *   POST /api/v1/profile  → saves changes
 *
 * The profile picture upload (POST /api/v1/profile/picture) is more complex
 * because it uses multipart/form-data instead of JSON. See Gotcha #5.
 */
public class ProfileView {

    public static Parent create() {
        VBox root = new VBox(20);
        root.setPadding(new Insets(20));
        root.setAlignment(Pos.TOP_CENTER);

        Label titleLabel = new Label("My Profile");
        titleLabel.setStyle("-fx-font-size: 20px; -fx-font-weight: bold;");

        // Form fields
        GridPane form = new GridPane();
        form.setHgap(10);
        form.setVgap(10);
        form.setAlignment(Pos.CENTER);

        Label nameLabel = new Label("Full Name:");
        TextField nameField = new TextField();
        nameField.setPrefWidth(300);

        Label emailLabel = new Label("Email:");
        TextField emailField = new TextField();
        emailField.setPrefWidth(300);

        Label roleLabel = new Label("Role:");
        Label roleValue = new Label();

        Label statusLabel = new Label("Status:");
        Label statusValue = new Label();

        form.add(nameLabel, 0, 0);
        form.add(nameField, 1, 0);
        form.add(emailLabel, 0, 1);
        form.add(emailField, 1, 1);
        form.add(roleLabel, 0, 2);
        form.add(roleValue, 1, 2);
        form.add(statusLabel, 0, 3);
        form.add(statusValue, 1, 3);

        // Save button
        Button saveBtn = new Button("Save Changes");
        saveBtn.setOnAction(e -> {
            // Build the JSON body matching what ProfileController@update expects
            JsonObject body = new JsonObject();
            body.addProperty("full_name", nameField.getText().trim());
            body.addProperty("email", emailField.getText().trim());

            javafx.concurrent.Task<Void> task = new javafx.concurrent.Task<>() {
                @Override
                protected Void call() throws Exception {
                    // POST /api/v1/profile with the updated fields
                    ApiClient.getInstance().post("/profile", body);
                    return null;
                }
            };
            task.setOnSucceeded(ev -> AlertHelper.showInfo("Success", "Profile updated."));
            task.setOnFailed(ev -> AlertHelper.showError("Error", task.getException().getMessage()));
            new Thread(task).start();
        });

        ProgressIndicator loading = new ProgressIndicator();
        loading.setVisible(true);

        root.getChildren().addAll(titleLabel, loading, form, saveBtn);

        // Load current profile in background
        javafx.concurrent.Task<User> loadTask = new javafx.concurrent.Task<>() {
            @Override
            protected User call() throws Exception {
                // GET /api/v1/me — returns { "user": { ... } }
                JsonObject response = ApiClient.getInstance().get("/me");
                JsonObject userJson = response.getAsJsonObject("user");
                return User.fromMeJson(userJson);
            }
        };
        loadTask.setOnSucceeded(e -> {
            loading.setVisible(false);
            User user = loadTask.getValue();
            // Fill the form with current values
            nameField.setText(user.getFullName());
            emailField.setText(user.getEmail());
            roleValue.setText(user.getRoleName());
            statusValue.setText(user.getAccountStatus());
        });
        loadTask.setOnFailed(e -> {
            loading.setVisible(false);
            AlertHelper.showError("Error", "Failed to load profile.");
        });
        new Thread(loadTask).start();

        return root;
    }
}
```

---

## 9. Person 2 — Forum Topics, Posts, Conversations, Messages

Person 2 builds all the discussion features: topics, replies, conversations, messages, and sharing.

### 9.1 Topic Model

**File:** `models/Topic.java`
**What it does:** Maps a forum topic from the API JSON into a Java object.

**Backend shape (from `GET /api/v1/topics`):**
```json
{
    "data": [
        {
            "id": 1,
            "title": "Welcome to the forum",
            "description": "Introduce yourself here",
            "status": "active",
            "post_type": "discussion",
            "is_pinned": false,
            "is_answered": false,
            "created_by": 1,
            "group_id": 1,
            "created_at": "2025-01-15T10:00:00",
            "updated_at": "2025-01-15T10:00:00",
            "creator": { "id": 1, "full_name": "John Doe" },
            "posts_count": 5
        }
    ]
}
```

Notice the JSON uses `snake_case` (e.g. `post_type`, `is_pinned`). Java uses `camelCase` (e.g. `postType`, `isPinned`). The `@SerializedName` annotation tells Gson how to translate between them.

```java
package com.yourforum.models;

import com.google.gson.annotations.SerializedName;

/**
 * Topic — a discussion topic in the forum.
 *
 * JSON field → Java field mapping (via @SerializedName):
 *   "post_type"   → postType
 *   "is_pinned"   → isPinned
 *   "is_answered" → isAnswered
 *   "created_by"  → createdBy
 *   "group_id"    → groupId
 *   "posts_count" → postsCount
 */
public class Topic {

    @SerializedName("id")
    private int id;

    @SerializedName("title")
    private String title;

    @SerializedName("description")
    private String description;

    @SerializedName("status")
    private String status;    // "active" or "archived"

    @SerializedName("post_type")
    private String postType;  // "discussion" or "question"

    @SerializedName("is_pinned")
    private boolean isPinned;

    @SerializedName("is_answered")
    private boolean isAnswered;

    @SerializedName("created_by")
    private int createdBy;

    @SerializedName("group_id")
    private int groupId;

    @SerializedName("created_at")
    private String createdAt;

    @SerializedName("updated_at")
    private String updatedAt;

    // Nested objects need special handling.
    // "creator" is an object: { "id": 1, "full_name": "John" }
    // We can store it as a String (just the name) or create a Creator class.
    @SerializedName("posts_count")
    private int postsCount;

    // ── Getters ──
    public int getId() { return id; }
    public String getTitle() { return title; }
    public String getDescription() { return description; }
    public String getStatus() { return status; }
    public String getPostType() { return postType; }
    public boolean isPinned() { return isPinned; }
    public boolean isAnswered() { return isAnswered; }
    public int getCreatedBy() { return createdBy; }
    public int getGroupId() { return groupId; }
    public String getCreatedAt() { return createdAt; }
    public String getUpdatedAt() { return updatedAt; }
    public int getPostsCount() { return postsCount; }

    /**
     * Parse a list of topics from a JSON response.
     * The API returns { "data": [ topic1, topic2, ... ] }
     */
    public static java.util.List<Topic> fromJsonArray(com.google.gson.JsonArray array) {
        com.google.gson.Gson gson = new com.google.gson.Gson();
        java.util.List<Topic> topics = new java.util.ArrayList<>();
        for (com.google.gson.JsonElement element : array) {
            topics.add(gson.fromJson(element, Topic.class));
        }
        return topics;
    }
}
```

### 9.2 TopicListView.java — List All Topics

**File:** `views/TopicListView.java`
**What it does:** Shows all topics in a table. User can click a topic to view its replies.
**Backend endpoint:** `GET /api/v1/topics`
**How data flows:**
```
User opens Topics
    → TopicListView.create()
    → Background Task: ApiClient.get("/topics")
    → Laravel's TopicController: queries topics table, returns JSON
    → Task parses JSON array into List<Topic>
    → Platform.runLater() populates TableView
    → User sees topics in rows
```

```java
package com.yourforum.views;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import com.yourforum.api.ApiClient;
import com.yourforum.api.ApiException;
import com.yourforum.models.Topic;
import com.yourforum.utils.AlertHelper;
import javafx.beans.property.SimpleStringProperty;
import javafx.geometry.Insets;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;

/**
 * TopicListView — shows all topics in a table.
 *
 * CONNECTS TO BACKEND:
 *   GET /api/v1/topics  →  returns { "data": [ { "id": 1, "title": "...", ... } ] }
 *
 * COLUMNS DISPLAYED:
 *   Title, Type (discussion/question), Status, Replies, Created
 *
 * Double-click a topic → opens TopicDetailView with its posts.
 * "New Topic" button → opens CreateTopicView form.
 */
public class TopicListView {

    public static Parent create() {
        VBox root = new VBox(10);
        root.setPadding(new Insets(20));

        // ── Title ──
        Label titleLabel = new Label("Topics");
        titleLabel.setStyle("-fx-font-size: 20px; -fx-font-weight: bold;");

        // ── Toolbar (New Topic + Refresh) ──
        HBox toolbar = new HBox(10);
        Button newTopicBtn = new Button("+ New Topic");
        Button refreshBtn = new Button("Refresh");

        toolbar.getChildren().addAll(newTopicBtn, refreshBtn);

        // ── Table ──
        // TableView<Topic> means each row represents one Topic object.
        // The columns read properties from the Topic object.
        TableView<Topic> table = new TableView<>();

        // Title column: displays topic.getTitle() in each cell
        TableColumn<Topic, String> titleCol = new TableColumn<>("Title");
        titleCol.setCellValueFactory(data ->
                new SimpleStringProperty(data.getValue().getTitle()));
        titleCol.setPrefWidth(300);

        // Type column: displays "Discussion" or "Question"
        TableColumn<Topic, String> typeCol = new TableColumn<>("Type");
        typeCol.setCellValueFactory(data ->
                new SimpleStringProperty(data.getValue().getPostType()));
        typeCol.setPrefWidth(100);

        // Status column
        TableColumn<Topic, String> statusCol = new TableColumn<>("Status");
        statusCol.setCellValueFactory(data ->
                new SimpleStringProperty(data.getValue().getStatus()));
        statusCol.setPrefWidth(80);

        // Replies count
        TableColumn<Topic, String> repliesCol = new TableColumn<>("Replies");
        repliesCol.setCellValueFactory(data ->
                new SimpleStringProperty(String.valueOf(data.getValue().getPostsCount())));
        repliesCol.setPrefWidth(80);

        table.getColumns().addAll(titleCol, typeCol, statusCol, repliesCol);
        table.setPrefHeight(500);

        // Double-click opens the topic
        table.setOnMouseClicked(event -> {
            if (event.getClickCount() == 2) {
                Topic selected = table.getSelectionModel().getSelectedItem();
                if (selected != null) {
                    // Replace content area with TopicDetailView
                    DashboardView.getContentArea().getChildren().clear();
                    DashboardView.getContentArea().getChildren().add(
                            TopicDetailView.create(selected.getId())
                    );
                }
            }
        });

        // ── Loading indicator ──
        ProgressIndicator loading = new ProgressIndicator();
        loading.setVisible(false);

        // ── New Topic button → switch to create form ──
        newTopicBtn.setOnAction(e -> {
            DashboardView.getContentArea().getChildren().clear();
            DashboardView.getContentArea().getChildren().add(CreateTopicView.create());
        });

        // ── Refresh button → reload data from API ──
        refreshBtn.setOnAction(e -> loadTopics(table, loading));

        root.getChildren().addAll(titleLabel, toolbar, loading, table);

        // Load data when screen opens
        loadTopics(table, loading);

        return root;
    }

    /**
     * Fetch topics from the API and populate the table.
     *
     * EXPLANATION:
     * 1. ApiClient sends GET /api/v1/topics to the Laravel server
     * 2. Laravel queries the topics table, returns JSON:
     *    { "data": [ { "id": 1, "title": "My Topic", ... }, ... ] }
     * 3. We extract the "data" array from the JSON response
     * 4. We convert each JSON object into a Topic Java object
     * 5. We put the Topic objects into the TableView
     */
    private static void loadTopics(TableView<Topic> table, ProgressIndicator loading) {
        loading.setVisible(true);

        javafx.concurrent.Task<java.util.List<Topic>> task = new javafx.concurrent.Task<>() {
            @Override
            protected java.util.List<Topic> call() throws Exception {
                // Step 1: Call the API
                JsonObject response = ApiClient.getInstance().get("/topics");

                // Step 2: The paginated response has "data" as an array
                // Note: Laravel returns paginated data as:
                // { "data": [...], "links": {...}, "meta": {...} }
                // The "data" key at the top level is the array of topics
                JsonArray dataArray = response.getAsJsonArray("data");

                // Step 3: Convert JSON array to List<Topic>
                return Topic.fromJsonArray(dataArray);
            }
        };

        task.setOnSucceeded(e -> {
            loading.setVisible(false);
            table.getItems().setAll(task.getValue());
        });

        task.setOnFailed(e -> {
            loading.setVisible(false);
            AlertHelper.showError("Error", "Failed to load topics: " +
                    task.getException().getMessage());
        });

        new Thread(task).start();
    }
}
```

### 9.3 TopicDetailView.java — View Topic + Posts

**File:** `views/TopicDetailView.java`
**What it does:** Shows a single topic and its replies (posts). Includes a reply form.
**Backend endpoints:**
- `GET /api/v1/topics/{id}` → returns topic + posts
- `POST /api/v1/topics/{id}/posts` → creates a reply

**Response shape:**
```json
{
    "data": {
        "topic": {
            "id": 1,
            "title": "Welcome",
            "description": "Say hello",
            "creator": { "id": 1, "full_name": "John" }
        },
        "posts": {
            "data": [
                {
                    "id": 10,
                    "body": "Hello everyone!",
                    "user": { "id": 2, "full_name": "Jane" },
                    "created_at": "2025-01-15T11:00:00"
                }
            ]
        }
    }
}
```

```java
package com.yourforum.views;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import com.yourforum.api.ApiClient;
import com.yourforum.api.ApiException;
import com.yourforum.utils.AlertHelper;
import javafx.geometry.Insets;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import javafx.scene.text.Text;
import javafx.scene.text.TextFlow;

/**
 * TopicDetailView — view a topic and its replies.
 *
 * HOW IT CONNECTS TO BACKEND:
 *   On load: GET /api/v1/topics/{topicId}
 *     → Laravel returns { "data": { "topic": {...}, "posts": { "data": [...] } } }
 *   On reply: POST /api/v1/topics/{topicId}/posts
 *     → Sends JSON: { "body": "My reply" }
 *     → Laravel saves and returns the new post
 *
 * LAYOUT:
 *   ┌─────────────────────────────────┐
 *   │  ← Back to Topics              │
 *   │                                 │
 *   │  Topic Title                    │
 *   │  Topic Description              │
 *   │  Posted by John                 │
 *   │  ─────────────────────────────  │
 *   │  Replies:                       │
 *   │  ┌─────────────────────────┐   │
 *   │  │ Jane: Hello everyone!   │   │
 *   │  └─────────────────────────┘   │
 *   │  ┌─────────────────────────┐   │
 *   │  │ Mike: Welcome Jane!     │   │
 *   │  └─────────────────────────┘   │
 *   │                                 │
 *   │  Reply: [________________] [Send]│
 *   └─────────────────────────────────┘
 */
public class TopicDetailView {

    public static Parent create(int topicId) {
        VBox root = new VBox(10);
        root.setPadding(new Insets(20));

        // ── Back button ──
        Button backBtn = new Button("← Back to Topics");
        backBtn.setOnAction(e -> {
            DashboardView.getContentArea().getChildren().clear();
            DashboardView.getContentArea().getChildren().add(TopicListView.create());
        });

        // ── Topic details ──
        Label titleLabel = new Label();
        titleLabel.setStyle("-fx-font-size: 20px; -fx-font-weight: bold;");

        TextFlow descriptionArea = new TextFlow();
        Label creatorLabel = new Label();
        creatorLabel.setStyle("-fx-font-size: 12px; -fx-text-fill: #7f8c8d;");

        // ── Replies list ──
        VBox repliesBox = new VBox(5);
        ScrollPane scrollPane = new ScrollPane(repliesBox);
        scrollPane.setFitToWidth(true);
        scrollPane.setPrefHeight(400);

        // ── Reply form ──
        Label replyLabel = new Label("Write a reply:");
        TextArea replyInput = new TextArea();
        replyInput.setPrefHeight(100);
        replyInput.setPromptText("Type your reply here...");

        Button sendBtn = new Button("Send Reply");
        sendBtn.setOnAction(e -> {
            String body = replyInput.getText().trim();
            if (body.isEmpty()) {
                AlertHelper.showWarning("Warning", "Reply cannot be empty.");
                return;
            }

            sendBtn.setDisable(true);

            javafx.concurrent.Task<Void> task = new javafx.concurrent.Task<>() {
                @Override
                protected Void call() throws Exception {
                    // POST /api/v1/topics/{topicId}/posts
                    JsonObject requestBody = new JsonObject();
                    requestBody.addProperty("body", body);
                    ApiClient.getInstance().post("/topics/" + topicId + "/posts", requestBody);
                    return null;
                }
            };

            task.setOnSucceeded(ev -> {
                sendBtn.setDisable(false);
                replyInput.clear();
                // Reload the posts to show the new reply
                loadPosts(topicId, repliesBox);
                AlertHelper.showInfo("Success", "Reply posted.");
            });

            task.setOnFailed(ev -> {
                sendBtn.setDisable(false);
                AlertHelper.showError("Error", task.getException().getMessage());
            });

            new Thread(task).start();
        });

        ProgressIndicator loading = new ProgressIndicator();
        loading.setVisible(true);

        root.getChildren().addAll(
                backBtn, titleLabel, creatorLabel, descriptionArea,
                new Label("Replies:"), loading, scrollPane,
                replyLabel, replyInput, sendBtn
        );

        // ── Load topic details and posts ──
        javafx.concurrent.Task<Void> loadTask = new javafx.concurrent.Task<>() {
            @Override
            protected Void call() throws Exception {
                // GET /api/v1/topics/{topicId}
                // Returns: { "data": { "topic": {...}, "posts": {...} } }
                JsonObject response = ApiClient.getInstance().get("/topics/" + topicId);
                JsonObject data = response.getAsJsonObject("data");

                // Extract topic info
                JsonObject topicJson = data.getAsJsonObject("topic");
                String title = topicJson.get("title").getAsString();
                String description = topicJson.has("description")
                        ? topicJson.get("description").getAsString()
                        : "";
                String creatorName = topicJson.has("creator")
                        ? topicJson.getAsJsonObject("creator").get("full_name").getAsString()
                        : "Unknown";

                // Extract posts
                JsonObject postsWrapper = data.getAsJsonObject("posts");
                JsonArray postsArray = postsWrapper.getAsJsonArray("data");

                // Update UI on JavaFX thread
                javafx.application.Platform.runLater(() -> {
                    titleLabel.setText(title);
                    Text descText = new Text(description);
                    descriptionArea.getChildren().setAll(descText);
                    creatorLabel.setText("Posted by " + creatorName);
                    loading.setVisible(false);

                    // Add each post to the replies box
                    for (com.google.gson.JsonElement elem : postsArray) {
                        JsonObject post = elem.getAsJsonObject();
                        String author = post.getAsJsonObject("user").get("full_name").getAsString();
                        String body = post.get("body").getAsString();
                        String date = post.get("created_at").getAsString();

                        VBox postCard = new VBox(3);
                        postCard.setStyle("-fx-border-color: #ecf0f1; -fx-border-radius: 5; "
                                + "-fx-padding: 10; -fx-background-color: white;");
                        Label authorLabel = new Label(author + " — " + date);
                        authorLabel.setStyle("-fx-font-weight: bold; -fx-font-size: 12px;");
                        Label bodyLabel = new Label(body);
                        bodyLabel.setWrapText(true);
                        postCard.getChildren().addAll(authorLabel, bodyLabel);
                        repliesBox.getChildren().add(postCard);
                    }
                });
                return null;
            }
        };

        loadTask.setOnFailed(e -> {
            loading.setVisible(false);
            AlertHelper.showError("Error", "Failed to load topic.");
        });

        new Thread(loadTask).start();

        return root;
    }

    private static void loadPosts(int topicId, VBox repliesBox) {
        // Same loading logic as above but only refreshes the posts section
        // (called after posting a reply)
        javafx.concurrent.Task<Void> task = new javafx.concurrent.Task<>() {
            @Override
            protected Void call() throws Exception {
                JsonObject response = ApiClient.getInstance().get("/topics/" + topicId);
                JsonObject data = response.getAsJsonObject("data");
                JsonObject postsWrapper = data.getAsJsonObject("posts");
                JsonArray postsArray = postsWrapper.getAsJsonArray("data");

                javafx.application.Platform.runLater(() -> {
                    repliesBox.getChildren().clear();
                    for (com.google.gson.JsonElement elem : postsArray) {
                        JsonObject post = elem.getAsJsonObject();
                        String author = post.getAsJsonObject("user").get("full_name").getAsString();
                        String body = post.get("body").getAsString();
                        String date = post.get("created_at").getAsString();

                        VBox postCard = new VBox(3);
                        postCard.setStyle("-fx-border-color: #ecf0f1; -fx-border-radius: 5; "
                                + "-fx-padding: 10; -fx-background-color: white;");
                        postCard.getChildren().addAll(
                                new Label(author + " — " + date),
                                new Label(body)
                        );
                        repliesBox.getChildren().add(postCard);
                    }
                });
                return null;
            }
        };
        new Thread(task).start();
    }
}
```

### 9.4 CreateTopicView.java — New Topic Form

**File:** `views/CreateTopicView.java`
**What it does:** A form to create a new forum topic.
**Backend endpoint:** `POST /api/v1/topics`
**Request body:**
```json
{ "title": "My Topic", "description": "Topic content here", "post_type": "discussion" }
```

```java
package com.yourforum.views;

import com.google.gson.JsonObject;
import com.yourforum.api.ApiClient;
import com.yourforum.api.ApiException;
import com.yourforum.utils.AlertHelper;
import javafx.geometry.Insets;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;

/**
 * CreateTopicView — form to create a new topic.
 *
 * CONNECTS TO BACKEND:
 *   POST /api/v1/topics
 *   Body: { "title": "...", "description": "...", "post_type": "discussion|question" }
 *
 * The "post_type" field determines whether the topic is a discussion or a question.
 * Questions can be marked as "answered" later.
 */
public class CreateTopicView {

    public static Parent create() {
        VBox root = new VBox(10);
        root.setPadding(new Insets(20));

        Button backBtn = new Button("← Back to Topics");

        Label titleLabel = new Label("Create New Topic");
        titleLabel.setStyle("-fx-font-size: 20px; -fx-font-weight: bold;");

        // Title input
        TextField titleField = new TextField();
        titleField.setPromptText("Topic title");

        // Type selector (RadioButtons: Discussion or Question)
        Label typeLabel = new Label("Type:");
        ToggleGroup typeGroup = new ToggleGroup();
        RadioButton discussionRadio = new RadioButton("Discussion");
        discussionRadio.setToggleGroup(typeGroup);
        discussionRadio.setSelected(true);
        RadioButton questionRadio = new RadioButton("Question");
        questionRadio.setToggleGroup(typeGroup);

        // Description input (larger text area)
        Label descLabel = new Label("Description:");
        TextArea descArea = new TextArea();
        descArea.setPrefHeight(200);
        descArea.setPromptText("Write your topic content here...");

        // Error label
        Label errorLabel = new Label();
        errorLabel.setStyle("-fx-text-fill: red;");
        errorLabel.setVisible(false);

        // Submit button
        Button submitBtn = new Button("Create Topic");

        ProgressIndicator loading = new ProgressIndicator();
        loading.setVisible(false);

        submitBtn.setOnAction(e -> {
            String title = titleField.getText().trim();
            String description = descArea.getText().trim();

            if (title.isEmpty()) {
                errorLabel.setText("Title is required.");
                errorLabel.setVisible(true);
                return;
            }

            errorLabel.setVisible(false);
            loading.setVisible(true);
            submitBtn.setDisable(true);

            // Determine post_type from the selected radio button
            String postType = questionRadio.isSelected() ? "question" : "discussion";

            // Build the JSON body matching TopicController@store validation
            JsonObject body = new JsonObject();
            body.addProperty("title", title);
            body.addProperty("description", description);
            body.addProperty("post_type", postType);

            javafx.concurrent.Task<Void> task = new javafx.concurrent.Task<>() {
                @Override
                protected Void call() throws Exception {
                    // POST /api/v1/topics
                    // Laravel's TopicController creates the topic in the database
                    // and returns: { "message": "Topic created.", "data": { "topic": {...} } }
                    ApiClient.getInstance().post("/topics", body);
                    return null;
                }
            };

            task.setOnSucceeded(ev -> {
                loading.setVisible(false);
                submitBtn.setDisable(false);
                AlertHelper.showInfo("Success", "Topic created!");
                // Go back to topics list
                DashboardView.getContentArea().getChildren().clear();
                DashboardView.getContentArea().getChildren().add(TopicListView.create());
            });

            task.setOnFailed(ev -> {
                loading.setVisible(false);
                submitBtn.setDisable(false);
                errorLabel.setText(task.getException().getMessage());
                errorLabel.setVisible(true);
            });

            new Thread(task).start();
        });

        root.getChildren().addAll(
                backBtn, titleLabel, titleField,
                typeLabel, discussionRadio, questionRadio,
                descLabel, descArea,
                errorLabel, submitBtn, loading
        );

        return root;
    }
}
```

### 9.5 Person 2 — Remaining Screens (Pattern Summary)

The remaining screens for Person 2 follow the exact same pattern:

| Screen | Backend Endpoint | What It Does |
|--------|-----------------|--------------|
| `ConversationListView.java` | `GET /api/v1/conversations` | Shows a TableView of conversations (same as TopicListView) |
| `ConversationDetailView.java` | `GET /api/v1/conversations/{id}`, `POST /api/v1/conversations/{id}/messages` | Shows messages + send form (same as TopicDetailView) |
| `CreateConversationView.java` | `POST /api/v1/conversations` | Form with type (direct/group), participant selection, name |
| `PostVisibilityView.java` | `GET/POST/DELETE /api/v1/posts/{id}/visibility` | Shows excluded users for a post, lets you add/remove |
| `ShareView.java` | `POST /api/v1/topics/{id}/share` | Generates a shareable link, shows it in a text field |
| `ReportView.java` | `POST /api/v1/reports` | Form with reason, content type, and content ID |

**They all follow this 3-step pattern:**
1. **On screen open:** Background Task → `ApiClient.get(url)` → parse JSON → populate UI
2. **On button click:** Background Task → `ApiClient.post(url, body)` → show success/error
3. **On delete:** Background Task → `ApiClient.delete(url)` → show success/error

---

## 10. Person 3 — Quizzes (Lecturer + Student)

Person 3 builds all quiz features. This is the most complex area because of the quiz timer and auto-submit.

### 10.1 Quiz Model

**CRITICAL:** Quiz uses `quiz_id` as its primary key, NOT `id`.

**File:** `models/Quiz.java`

```java
package com.yourforum.models;

import com.google.gson.annotations.SerializedName;

/**
 * Quiz — represents a quiz created by a lecturer.
 *
 * CRITICAL: The Laravel model sets $primaryKey = 'quiz_id',
 * so ALL quiz endpoints use quiz_id as the identifier, NOT id.
 *
 * JSON shape (from GET /api/v1/admin/quizzes):
 * {
 *     "quiz_id": 1,
 *     "title": "Midterm Exam",
 *     "description": "Covers chapters 1-5",
 *     "target_category": "Student",
 *     "group_id": 1,
 *     "scheduled_date": "2025-06-15",
 *     "start_time": "09:00",
 *     "duration_minutes": 60,
 *     "is_active": true,
 *     "published_at": "2025-06-10T08:00:00",
 *     "questions_count": 10,
 *     "lecturer": { "id": 1, "full_name": "Dr. Smith" }
 * }
 */
public class Quiz {

    @SerializedName("quiz_id")
    private int quizId;  // NOT "id" — this is the primary key in Laravel

    @SerializedName("title")
    private String title;

    @SerializedName("description")
    private String description;

    @SerializedName("target_category")
    private String targetCategory;

    @SerializedName("group_id")
    private int groupId;

    @SerializedName("scheduled_date")
    private String scheduledDate;

    @SerializedName("start_time")
    private String startTime;

    @SerializedName("duration_minutes")
    private int durationMinutes;

    @SerializedName("is_active")
    private boolean isActive;

    @SerializedName("published_at")
    private String publishedAt;

    @SerializedName("questions_count")
    private int questionsCount;

    // Getters
    public int getQuizId() { return quizId; }
    public String getTitle() { return title; }
    public String getDescription() { return description; }
    public int getDurationMinutes() { return durationMinutes; }
    public boolean isActive() { return isActive; }
    public String getScheduledDate() { return scheduledDate; }
    public String getStartTime() { return startTime; }
}
```

### 10.2 QuizAttemptView.java — The Quiz Timer Screen

**File:** `views/QuizAttemptView.java`
**What it does:** The screen students see when taking a quiz. Shows one question at a time with a countdown timer.

**Backend endpoints used:**
1. `POST /api/v1/quizzes/{quiz}/attempt` — starts the attempt, returns attempt_id
2. `GET /api/v1/quizzes/{quiz}/attempt` — loads current attempt + questions
3. `POST /api/v1/quizzes/{quiz}/answer` — saves an answer
4. `POST /api/v1/quizzes/{quiz}/submit` — submits the attempt
5. `POST /api/v1/quizzes/{quiz}/auto-submit` — called when timer expires

**How the timer works with the backend:**
```
Quiz starts → Timer begins counting down from duration_minutes
                  │
User answers question → POST /api/v1/quizzes/{quiz}/answer
                  │
Timer reaches 0 → POST /api/v1/quizzes/{quiz}/auto-submit
                  → Laravel submits whatever answers were saved
                  → Returns result
                  │
User clicks Submit → POST /api/v1/quizzes/{quiz}/submit
                  → Laravel marks attempt as completed
                  → Calculates grade
```

```java
package com.yourforum.views;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import com.yourforum.api.ApiClient;
import com.yourforum.api.ApiException;
import com.yourforum.utils.AlertHelper;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.geometry.Insets;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import javafx.util.Duration;

/**
 * QuizAttemptView — the quiz-taking screen with a live countdown timer.
 *
 * HOW IT CONNECTS TO BACKEND:
 *
 * 1. START: POST /api/v1/quizzes/{quiz}/attempt
 *    → Laravel creates a StudentAttempt record
 *    → Returns: { "attempt_id": 5, "started_at": "..." }
 *
 * 2. LOAD QUESTIONS: GET /api/v1/quizzes/{quiz}/attempt
 *    → Returns the attempt with its questions and any saved answers
 *    → Questions come in order
 *
 * 3. SAVE ANSWER: POST /api/v1/quizzes/{quiz}/answer
 *    → Body: { "question_id": 10, "answer_text": "My answer" }
 *    → Laravel saves to student_answers table
 *
 * 4. SUBMIT: POST /api/v1/quizzes/{quiz}/submit
 *    → Laravel marks attempt as completed
 *    → Calculates grade
 *    → Returns: { "score": 8, "total": 10, "percentage": 80 }
 *
 * 5. AUTO-SUBMIT: POST /api/v1/quizzes/{quiz}/auto-submit
 *    → Same as submit, but triggered by timer expiry
 *    → Any unanswered questions are marked as 0
 */
public class QuizAttemptView {

    private static int remainingSeconds;
    private static Timeline timer;
    private static Label timerLabel;
    private static int currentQuestionIndex = 0;
    private static JsonArray questionsArray;
    private static int quizId;
    private static TextArea answerArea;
    private static Label questionLabel;
    private static Label questionNumLabel;

    public static Parent create(int quizIdParam, int durationMinutes) {
        quizId = quizIdParam;
        remainingSeconds = durationMinutes * 60;

        VBox root = new VBox(10);
        root.setPadding(new Insets(20));

        // ── Timer display (countdown at the top) ──
        timerLabel = new Label(formatTime(remainingSeconds));
        timerLabel.setStyle("-fx-font-size: 24px; -fx-font-weight: bold; -fx-text-fill: #e74c3c;");

        // ── Question number ──
        questionNumLabel = new Label("Question 1");
        questionNumLabel.setStyle("-fx-font-size: 14px; -fx-font-weight: bold;");

        // ── Question text ──
        questionLabel = new Label();
        questionLabel.setWrapText(true);
        questionLabel.setStyle("-fx-font-size: 16px; -fx-padding: 10 0;");

        // ── Answer area ──
        answerArea = new TextArea();
        answerArea.setPrefHeight(150);
        answerArea.setPromptText("Type your answer here...");

        // ── Navigation buttons ──
        Button prevBtn = new Button("← Previous");
        Button nextBtn = new Button("Next →");
        Button submitBtn = new Button("Submit Quiz");
        submitBtn.setStyle("-fx-background-color: #27ae60; -fx-text-fill: white;");

        prevBtn.setOnAction(e -> navigateQuestion(-1));
        nextBtn.setOnAction(e -> navigateQuestion(1));

        submitBtn.setOnAction(e -> {
            boolean confirmed = AlertHelper.showConfirmation(
                    "Submit Quiz",
                    "Are you sure? You cannot change your answers after submission."
            );
            if (confirmed) {
                submitQuiz();
            }
        });

        ProgressIndicator loading = new ProgressIndicator();

        root.getChildren().addAll(
                timerLabel, questionNumLabel, questionLabel,
                answerArea, prevBtn, nextBtn, submitBtn, loading
        );

        // ── Start the attempt when the screen loads ──
        javafx.concurrent.Task<Void> startTask = new javafx.concurrent.Task<>() {
            @Override
            protected Void call() throws Exception {
                // POST /api/v1/quizzes/{quiz}/attempt
                JsonObject response = ApiClient.getInstance().post(
                        "/quizzes/" + quizId + "/attempt", new JsonObject()
                );
                // Then load the questions
                JsonObject attemptResponse = ApiClient.getInstance().get(
                        "/quizzes/" + quizId + "/attempt"
                );
                // Parse questions array (exact JSON path depends on API response)
                // Typically: attemptResponse contains questions in its data
                JsonObject data = attemptResponse.getAsJsonObject("data");
                if (data.has("questions")) {
                    questionsArray = data.getAsJsonArray("questions");
                }

                return null;
            }
        };

        startTask.setOnSucceeded(e -> {
            loading.setVisible(false);
            if (questionsArray != null && questionsArray.size() > 0) {
                showQuestion(0);
                startTimer();
            } else {
                AlertHelper.showError("Error", "No questions found for this quiz.");
            }
        });

        startTask.setOnFailed(e -> {
            loading.setVisible(false);
            AlertHelper.showError("Error", "Failed to start quiz: "
                    + startTask.getException().getMessage());
        });

        loading.setVisible(true);
        new Thread(startTask).start();

        return root;
    }

    /**
     * Display the question at the given index.
     */
    private static void showQuestion(int index) {
        if (questionsArray == null || index < 0 || index >= questionsArray.size()) return;

        currentQuestionIndex = index;
        JsonObject question = questionsArray.get(index).getAsJsonObject();

        questionNumLabel.setText("Question " + (index + 1) + " of " + questionsArray.size());
        questionLabel.setText(question.get("question_text").getAsString());

        // Clear answer area for the new question
        answerArea.clear();

        // If this question already has a saved answer, load it
        if (question.has("student_answer") && !question.get("student_answer").isJsonNull()) {
            answerArea.setText(question.get("student_answer").getAsString());
        }
    }

    /**
     * Navigate to the previous or next question.
     * Saves the current answer before switching.
     */
    private static void navigateQuestion(int delta) {
        // Save current answer before moving
        saveCurrentAnswer();

        int newIndex = currentQuestionIndex + delta;
        if (newIndex >= 0 && newIndex < questionsArray.size()) {
            showQuestion(newIndex);
        }
    }

    /**
     * Save the current answer to the backend.
     */
    private static void saveCurrentAnswer() {
        String answerText = answerArea.getText().trim();
        if (answerText.isEmpty()) return;

        JsonObject question = questionsArray.get(currentQuestionIndex).getAsJsonObject();
        int questionId = question.get("id").getAsInt();

        JsonObject body = new JsonObject();
        body.addProperty("question_id", questionId);
        body.addProperty("answer_text", answerText);

        // Fire-and-forget: save answer in background
        javafx.concurrent.Task<Void> task = new javafx.concurrent.Task<>() {
            @Override
            protected Void call() throws Exception {
                ApiClient.getInstance().post("/quizzes/" + quizId + "/answer", body);
                return null;
            }
        };
        new Thread(task).start();
    }

    /**
     * Submit the quiz to the backend.
     */
    private static void submitQuiz() {
        timer.stop();

        // Save the last answer first
        saveCurrentAnswer();

        javafx.concurrent.Task<Void> task = new javafx.concurrent.Task<>() {
            @Override
            protected Void call() throws Exception {
                // POST /api/v1/quizzes/{quiz}/submit
                ApiClient.getInstance().post("/quizzes/" + quizId + "/submit", new JsonObject());
                return null;
            }
        };

        task.setOnSucceeded(e -> {
            AlertHelper.showInfo("Quiz Submitted", "Your quiz has been submitted.");
            // Show the result screen
            DashboardView.getContentArea().getChildren().clear();
            DashboardView.getContentArea().getChildren().add(
                    QuizResultView.create(quizId)
            );
        });

        task.setOnFailed(e -> {
            AlertHelper.showError("Error", "Failed to submit: "
                    + task.getException().getMessage());
        });

        new Thread(task).start();
    }

    /**
     * Start the countdown timer.
     * When it reaches 0, auto-submit the quiz.
     */
    private static void startTimer() {
        timer = new Timeline(
                new KeyFrame(Duration.seconds(1), e -> {
                    remainingSeconds--;

                    // Update the timer display
                    timerLabel.setText(formatTime(remainingSeconds));

                    // Change color when time is low
                    if (remainingSeconds < 60) {
                        timerLabel.setStyle(
                                "-fx-font-size: 24px; -fx-font-weight: bold; -fx-text-fill: red;"
                        );
                    }

                    // Auto-submit when time runs out
                    if (remainingSeconds <= 0) {
                        timer.stop();
                        autoSubmitQuiz();
                    }
                })
        );
        timer.setCycleCount(Timeline.INDEFINITE);
        timer.play();
    }

    /**
     * Auto-submit when the timer expires.
     * Any unanswered questions are submitted as-is (blank).
     */
    private static void autoSubmitQuiz() {
        AlertHelper.showInfo("Time's Up!",
                "Your quiz has been auto-submitted.");

        javafx.concurrent.Task<Void> task = new javafx.concurrent.Task<>() {
            @Override
            protected Void call() throws Exception {
                ApiClient.getInstance().post(
                        "/quizzes/" + quizId + "/auto-submit", new JsonObject()
                );
                return null;
            }
        };

        task.setOnSucceeded(e -> {
            DashboardView.getContentArea().getChildren().clear();
            DashboardView.getContentArea().getChildren().add(QuizResultView.create(quizId));
        });

        new Thread(task).start();
    }

    /**
     * Format seconds as MM:SS.
     */
    private static String formatTime(int totalSeconds) {
        int minutes = totalSeconds / 60;
        int seconds = totalSeconds % 60;
        return String.format("%02d:%02d", minutes, seconds);
    }
}
```

### 10.3 Person 3 — Remaining Quiz Screens

The other quiz screens follow the same Task + ApiClient pattern:

- **`QuizListView.java`** — Shows three tabs: Upcoming, Live, History. Each tab loads from a different endpoint (`/quizzes/upcoming`, `/quizzes/live`, `/me/quiz-history`). Students click a quiz to see the announcement or start it.
- **`CreateQuizView.java`** (Lecturer only) — A form with title, description, target category, group, scheduled date, start time, duration. POST to `/admin/quizzes`. This uses the admin prefix because quiz creation requires the `admin` middleware.
- **`QuestionEditorView.java`** (Lecturer only) — Shows all questions for a quiz in a ListView. Each question has "Add", "Edit", "Delete" buttons. Calls `/admin/quizzes/{quiz}/questions` CRUD endpoints.
- **`QuizResultView.java`** — Fetches `GET /api/v1/quizzes/{quiz}/result` and displays the score, total, percentage, and per-question breakdown.

---

## 11. Person 4 — Groups, Sync Engine, Recommendations

### 11.1 GroupListView.java

**File:** `views/GroupListView.java`
**What it does:** Shows groups the user belongs to.
**Backend endpoint:** `GET /api/v1/groups`
**Response shape:** `{ "data": [ { "id": 1, "group_name": "Class A", "description": "...", "users_count": 25 } ] }`

```java
package com.yourforum.views;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import com.yourforum.api.ApiClient;
import com.yourforum.api.ApiException;
import com.yourforum.utils.AlertHelper;
import javafx.beans.property.SimpleStringProperty;
import javafx.geometry.Insets;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;

/**
 * GroupListView — shows groups the user belongs to.
 *
 * CONNECTS TO BACKEND:
 *   GET /api/v1/groups
 *   → Returns: { "data": [ { "id": 1, "group_name": "Class A", ... } ] }
 *
 * IMPORTANT: For regular users, this returns a plain array (not paginated).
 * For admins, it returns a paginated response with meta data.
 * The code below handles both cases.
 *
 * Double-click a group → opens GroupDetailView showing members.
 */
public class GroupListView {

    public static Parent create() {
        VBox root = new VBox(10);
        root.setPadding(new Insets(20));

        Label titleLabel = new Label("My Groups");
        titleLabel.setStyle("-fx-font-size: 20px; -fx-font-weight: bold;");

        TableView<JsonObject> table = new TableView<>();

        TableColumn<JsonObject, String> nameCol = new TableColumn<>("Group Name");
        nameCol.setCellValueFactory(data ->
                new SimpleStringProperty(
                        data.getValue().get("group_name").getAsString()
                ));
        nameCol.setPrefWidth(200);

        TableColumn<JsonObject, String> descCol = new TableColumn<>("Description");
        descCol.setCellValueFactory(data -> {
            JsonObject obj = data.getValue();
            String desc = obj.has("description") && !obj.get("description").isJsonNull()
                    ? obj.get("description").getAsString()
                    : "";
            return new SimpleStringProperty(desc);
        });
        descCol.setPrefWidth(300);

        TableColumn<JsonObject, String> membersCol = new TableColumn<>("Members");
        membersCol.setCellValueFactory(data ->
                new SimpleStringProperty(
                        String.valueOf(data.getValue().get("users_count").getAsInt())
                ));
        membersCol.setPrefWidth(80);

        table.getColumns().addAll(nameCol, descCol, membersCol);
        table.setPrefHeight(500);

        // Double-click to view group members
        table.setOnMouseClicked(event -> {
            if (event.getClickCount() == 2) {
                JsonObject selected = table.getSelectionModel().getSelectedItem();
                if (selected != null) {
                    int groupId = selected.get("id").getAsInt();
                    DashboardView.getContentArea().getChildren().clear();
                    DashboardView.getContentArea().getChildren().add(
                            GroupDetailView.create(groupId)
                    );
                }
            }
        });

        ProgressIndicator loading = new ProgressIndicator();

        // Load groups
        javafx.concurrent.Task<Void> loadTask = new javafx.concurrent.Task<>() {
            @Override
            protected Void call() throws Exception {
                JsonObject response = ApiClient.getInstance().get("/groups");
                JsonArray dataArray = response.getAsJsonArray("data");

                javafx.application.Platform.runLater(() -> {
                    table.getItems().clear();
                    for (com.google.gson.JsonElement elem : dataArray) {
                        table.getItems().add(elem.getAsJsonObject());
                    }
                });
                return null;
            }
        };

        loadTask.setOnSucceeded(e -> loading.setVisible(false));
        loadTask.setOnFailed(e -> {
            loading.setVisible(false);
            AlertHelper.showError("Error", "Failed to load groups.");
        });

        loading.setVisible(true);
        new Thread(loadTask).start();

        root.getChildren().addAll(titleLabel, loading, table);
        return root;
    }
}
```

### 11.2 SyncEngine.java — Offline Sync

**File:** `utils/SyncEngine.java` (NOT in views/ — it's a utility, not a screen)
**What it does:** Runs in the background every 30 seconds, pushing offline messages and pulling new data from the server.

**Backend endpoints:**
- `GET /api/v1/sync/pull?device_id=X` — returns conversations, messages, status updates changed since last sync
- `POST /api/v1/sync/push` — sends offline messages to the server

**How offline sync works:**
```
User composes a message while offline
    → Message is saved to a local queue (list in memory)
    → SyncEngine runs every 30 seconds
    → Tries to POST /sync/push with the queued messages
    → If server is reachable: messages are sent, queue is cleared
    → If server is down: messages stay in queue, retry next cycle

SyncEngine also pulls:
    → GET /sync/pull?device_id=DESKTOP-PC-1
    → Server returns all conversations/messages that changed since last sync
    → Updates local display
```

```java
package com.yourforum.utils;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import com.yourforum.api.ApiClient;
import com.yourforum.api.ApiException;
import javafx.application.Platform;
import javafx.concurrent.ScheduledService;
import javafx.concurrent.Task;
import javafx.util.Duration;

import java.util.UUID;

/**
 * SyncEngine — keeps the desktop app in sync with the server
 * by periodically pushing and pulling data.
 *
 * HOW IT CONNECTS TO BACKEND:
 *
 * PULL: GET /api/v1/sync/pull?device_id={deviceId}
 *   Returns everything that changed since the device's last sync:
 *   { "data": { "conversations": [...], "messages": [...], "status_updates": [...] } }
 *
 * PUSH: POST /api/v1/sync/push
 *   Sends offline messages to the server:
 *   { "messages": [ { "client_id": "abc", "conversation_id": 1, "body": "Hello" } ] }
 */
public class SyncEngine {

    private static SyncEngine instance;
    private final ScheduledService<Void> syncService;
    private final String deviceId;

    // Queue of offline messages waiting to be sent
    private final java.util.List<JsonObject> offlineMessageQueue = new java.util.ArrayList<>();

    /**
     * Private constructor — use getInstance().
     * Generates a unique device ID on creation (first time only).
     */
    private SyncEngine() {
        // Generate or load a unique device ID
        // This tells the server which data this device has already synced
        String savedId = null;
        try {
            savedId = java.util.prefs.Preferences.userNodeForPackage(
                    com.yourforum.App.class
            ).get("device_id", null);
        } catch (Exception e) {
            // Ignore
        }

        if (savedId == null || savedId.isEmpty()) {
            deviceId = "desktop-" + UUID.randomUUID().toString().substring(0, 8);
            try {
                java.util.prefs.Preferences.userNodeForPackage(
                        com.yourforum.App.class
                ).put("device_id", deviceId);
            } catch (Exception e) {
                // Ignore
            }
        } else {
            deviceId = savedId;
        }

        // ScheduledService runs on a background thread every N seconds
        // It automatically handles: start, stop, pause on failure, retry
        syncService = new ScheduledService<>() {
            @Override
            protected Task<Void> createTask() {
                return new Task<>() {
                    @Override
                    protected Void call() throws Exception {
                        // Step 1: Push any offline messages
                        pushOfflineMessages();

                        // Step 2: Pull new data from the server
                        pullNewData();

                        return null;
                    }
                };
            }
        };

        // Run every 30 seconds (adjust based on your needs)
        syncService.setPeriod(Duration.seconds(30));

        // If a sync fails, wait 10 seconds before retrying
        syncService.setBackoffStrategy(new javafx.concurrent.Service.BackoffStrategy() {
            @Override
            public Duration computeBackoff(Duration currentDuration) {
                return Duration.seconds(10);
            }
        });
    }

    public static synchronized SyncEngine getInstance() {
        if (instance == null) {
            instance = new SyncEngine();
        }
        return instance;
    }

    public void start() {
        syncService.start();
    }

    public void stop() {
        syncService.cancel();
    }

    /**
     * Add a message to the offline queue.
     * Called by ConversationDetailView when the server is unreachable.
     */
    public void queueOfflineMessage(int conversationId, String body) {
        JsonObject msg = new JsonObject();
        msg.addProperty("client_id", UUID.randomUUID().toString());
        msg.addProperty("conversation_id", conversationId);
        msg.addProperty("body", body);
        synchronized (offlineMessageQueue) {
            offlineMessageQueue.add(msg);
        }
    }

    /**
     * Push offline messages to the server.
     * POST /api/v1/sync/push sends all queued messages.
     */
    private void pushOfflineMessages() {
        java.util.List<JsonObject> batch;
        synchronized (offlineMessageQueue) {
            if (offlineMessageQueue.isEmpty()) return;
            batch = new java.util.ArrayList<>(offlineMessageQueue);
            offlineMessageQueue.clear();
        }

        try {
            JsonObject body = new JsonObject();
            JsonArray messagesArray = new JsonArray();
            for (JsonObject msg : batch) {
                messagesArray.add(msg);
            }
            body.add("messages", messagesArray);

            ApiClient.getInstance().post("/sync/push", body);
        } catch (Exception e) {
            // Push failed — re-queue the messages
            synchronized (offlineMessageQueue) {
                offlineMessageQueue.addAll(batch);
            }
        }
    }

    /**
     * Pull new data from the server.
     * GET /api/v1/sync/pull?device_id={deviceId}
     *
     * The server returns everything that changed since the last sync.
     * This includes new conversations, messages, and status updates.
     */
    private void pullNewData() {
        try {
            JsonObject response = ApiClient.getInstance().get(
                    "/sync/pull?device_id=" + deviceId
            );

            // The response contains conversations, messages, and status updates
            // that have changed since the last sync.
            // For now, we just log it. In a full implementation, you would
            // update the UI with the new data.
            System.out.println("Sync completed at: "
                    + response.getAsJsonObject("data").get("synced_at").getAsString());
        } catch (Exception e) {
            // Server might be down — that's OK, we'll retry next cycle
        }
    }
}
```

**How to wire SyncEngine into the app:**

In `DashboardView.java`, start the sync engine when the dashboard loads:
```java
// In DashboardView.create(), after the layout is built:
SyncEngine.getInstance().start();

// In the logout button handler:
SyncEngine.getInstance().stop();
```

---

## 12. Person 5 — Admin Features

Person 5 builds all admin screens. These are behind the `admin` middleware, so only users with admin roles can access them.

### 12.1 AdminDashboardView.java

**File:** `views/AdminDashboardView.java`
**What it does:** Shows admin overview statistics.
**Backend endpoint:** `GET /api/v1/admin/dashboard`
**Response shape:** (varies based on what the admin dashboard controller returns)
**Note:** This endpoint is behind the `admin` middleware. If a non-admin user calls it, they get a 403 error. The code handles this by showing an "Access Denied" message.

```java
package com.yourforum.views;

import com.google.gson.JsonObject;
import com.yourforum.api.ApiClient;
import com.yourforum.api.ApiException;
import com.yourforum.utils.AlertHelper;
import javafx.geometry.Insets;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;

/**
 * AdminDashboardView — admin home screen with overview statistics.
 *
 * CONNECTS TO BACKEND:
 *   GET /api/v1/admin/dashboard
 *   → Returns statistics: total users, groups, topics, posts, quizzes, etc.
 *
 * IMPORTANT: This endpoint requires the 'admin' middleware.
 * If the logged-in user is NOT an admin (System Admin or Group Admin),
 * the server returns HTTP 403. We handle this gracefully.
 */
public class AdminDashboardView {

    public static Parent create() {
        VBox root = new VBox(10);
        root.setPadding(new Insets(20));

        Label titleLabel = new Label("Admin Dashboard");
        titleLabel.setStyle("-fx-font-size: 20px; -fx-font-weight: bold;");

        ProgressIndicator loading = new ProgressIndicator();

        // Stats cards displayed in a row
        HBox statsBox = new HBox(20);
        statsBox.setPadding(new Insets(20, 0, 20, 0));

        // Quick action buttons
        Label actionsLabel = new Label("Quick Actions:");
        actionsLabel.setStyle("-fx-font-weight: bold;");

        Button usersBtn = new Button("Manage Users");
        Button groupsBtn = new Button("Manage Groups");
        Button statsBtn = new Button("View Statistics");

        usersBtn.setOnAction(e -> navigateTo("users"));
        groupsBtn.setOnAction(e -> navigateTo("groups"));
        statsBtn.setOnAction(e -> navigateTo("statistics"));

        HBox actionsBox = new HBox(10, usersBtn, groupsBtn, statsBtn);

        root.getChildren().addAll(titleLabel, loading, statsBox, actionsLabel, actionsBox);

        // Load dashboard stats
        javafx.concurrent.Task<JsonObject> loadTask = new javafx.concurrent.Task<>() {
            @Override
            protected JsonObject call() throws Exception {
                // GET /api/v1/admin/dashboard
                // If the user is not an admin, this throws ApiException with 403
                return ApiClient.getInstance().get("/admin/dashboard");
            }
        };

        loadTask.setOnSucceeded(e -> {
            loading.setVisible(false);
            JsonObject response = loadTask.getValue();

            // Parse stats from the response and create stat cards
            // The exact JSON shape depends on the admin dashboard controller
            // Typically: { "total_users": 100, "total_topics": 50, ... }
            // or: { "data": { "users": 100, "topics": 50, ... } }

            statsBox.getChildren().clear();

            // Try to read stats directly from the response
            if (response.has("data")) {
                JsonObject data = response.getAsJsonObject("data");
                addStatCard(statsBox, "Total Users", data.get("total_users"));
                addStatCard(statsBox, "Active Groups", data.get("total_groups"));
                addStatCard(statsBox, "Topics", data.get("total_topics"));
                addStatCard(statsBox, "Posts", data.get("total_posts"));
            } else {
                // Fallback: show raw response
                statsBox.getChildren().add(new Label("Stats loaded. Check raw data."));
            }
        });

        loadTask.setOnFailed(e -> {
            loading.setVisible(false);
            Throwable ex = loadTask.getException();

            if (ex instanceof ApiException && ((ApiException) ex).getStatusCode() == 403) {
                // User is not an admin — show access denied
                AlertHelper.showError("Access Denied",
                        "You do not have admin privileges.");
                statsBox.getChildren().add(new Label("Access Denied — Admin only."));
            } else {
                AlertHelper.showError("Error", "Failed to load dashboard: "
                        + ex.getMessage());
            }
        });

        new Thread(loadTask).start();

        return root;
    }

    private static void addStatCard(HBox container, String label,
                                     com.google.gson.JsonElement value) {
        VBox card = new VBox(5);
        card.setStyle("-fx-border-color: #bdc3c7; -fx-border-radius: 5; "
                + "-fx-padding: 15; -fx-background-color: white;");
        Label valueLabel = new Label(
                value != null && !value.isJsonNull() ? value.getAsString() : "0"
        );
        valueLabel.setStyle("-fx-font-size: 24px; -fx-font-weight: bold; -fx-text-fill: #3498db;");
        Label nameLabel = new Label(label);
        card.getChildren().addAll(valueLabel, nameLabel);
        container.getChildren().add(card);
    }

    private static void navigateTo(String section) {
        // These will be wired to the actual admin screens
        AlertHelper.showInfo("Coming Soon",
                "The " + section + " management screen is being built.");
    }
}
```

### 12.2 Person 5 — Pattern for Admin CRUD Screens

All admin CRUD screens follow the same pattern as the other persons' screens but use the `/admin/` prefix in their API calls. For example:

```java
// User management — always prefix with /admin/
ApiClient.getInstance().get("/admin/users");           // List users
ApiClient.getInstance().post("/admin/users", body);    // Create user
ApiClient.getInstance().put("/admin/users/" + id, body); // Update user
ApiClient.getInstance().delete("/admin/users/" + id);    // Delete user
```

**The 403 pattern:** Always check for 403 in `setOnFailed`:
```java
if (ex instanceof ApiException && ((ApiException) ex).getStatusCode() == 403) {
    AlertHelper.showError("Access Denied", "Admin privileges required.");
}
```

All Person 5 screens follow the exact same `Task → ApiClient → parse → update UI` pattern as every other screen in the app.

---

## Summary: The Universal Pattern

Every screen in every person's feature area follows this ONE pattern:

```
┌─────────────────────────────────────────────────────────┐
│  1. User does something (opens screen, clicks button)   │
│  2. Create a Task (background thread)                   │
│  3. Inside Task: call ApiClient.get/post/put/delete     │
│  4. ApiClient sends HTTP request to Laravel             │
│  5. Laravel processes it, returns JSON                  │
│  6. Task parses JSON into Java objects                  │
│  7. Platform.runLater() updates the UI                  │
│  8. User sees the result                                │
└─────────────────────────────────────────────────────────┘
```
        table.getItems().setAll(loadTask.getValue());
    });
    new Thread(loadTask).start();
    
    root.getChildren().addAll(title, table);
    return root;
}
```

### Step D: Wire into Dashboard

In `DashboardView.java`, replace the placeholder button handler:

```java
// BEFORE (placeholder):
topicsBtn.setOnAction(e -> {
    contentArea.getChildren().clear();
    Label placeholder = new Label("Topics — coming soon");
    contentArea.getChildren().add(placeholder);
});

// AFTER (real):
topicsBtn.setOnAction(e -> {
    contentArea.getChildren().clear();
    contentArea.getChildren().add(TopicListView.create());
});
```

### Step E: Test

1. Start the Laravel server: `php artisan serve`
2. Start the desktop app: `mvnw.cmd clean javafx:run` (from the `smart-discussion-forum-desktop` folder)
3. Log in
4. Click your sidebar button
5. Verify the data loads correctly

---

## 13. Testing Your Feature

### Manual Testing Checklist (everyone does this):

- [ ] Screen opens without crashing
- [ ] Data loads from API (you see topics, messages, etc.)
- [ ] Creating data works (new topic, new message, etc.)
- [ ] Errors show user-friendly messages (not stack traces)
- [ ] Back button / navigation works
- [ ] Logout and re-login still works

### If Something Goes Wrong:

**"Cannot connect to the server"** — Is `php artisan serve` running? Check the terminal.

**"401 Unauthorized"** — Your token expired. Log out and log in again.

**"JSON parse error"** — The API returned something unexpected. Check:
1. Is your `@SerializedName` matching the actual JSON field name?
2. Did you use `getAsJsonArray()` when the field is actually an object?

**"White screen / nothing shows"** — Check the terminal for Java stack traces. Look for the first line that says `Exception` or `Error`.

---

## 14. Final Verification

When all 5 people have finished, verify:

1. **Open app → Login screen appears**
2. **Login with valid credentials → Dashboard with sidebar appears**
3. **Login with wrong password → Error message "Invalid credentials"**
4. **Login with warned account → Acknowledge warning dialog → Can log in**
5. **Click Topics → List of topics loads from API**
6. **Create a topic → Appears in the list**
7. **Click Chat → Start a conversation → Send a message**
8. **Click Quizzes → See upcoming/live quizzes → Attempt one**
9. **Click Groups → See groups → View members**
10. **Click Admin → See dashboard stats (if admin) or 403 error (if not)**
11. **Logout → Returns to login screen**
12. **Close app, reopen → Still logged in (session restored)**

---

## 15. Common Gotchas (Read This Before Starting)

### Gotcha 1: Role and group have TWO shapes

**Login returns:** `"role": "Student"` (a string)
**/me returns:** `"role": {"id": 3, "name": "Student"}` (an object)

The `User.java` model already handles this with `getRoleName()` and `getGroupName()` methods. Always use those methods instead of accessing `role` directly.

### Gotcha 2: Quiz IDs are `quiz_id`, NOT `id`

```java
// WRONG — will return null:
@SerializedName("id") private int id;

// CORRECT:
@SerializedName("quiz_id") private int quizId;
```

Same for attempts (`attempt_id`), grades (`grade_id`).

### Gotcha 3: Groups list is not always paginated

For regular users, `GET /groups` returns a plain array (no pagination metadata). For admins, it returns a paginated response. Handle both:

```java
JsonObject response = ApiClient.getInstance().get("/groups");
JsonArray groupsArray;

if (response.has("data") && response.get("data").isJsonArray()) {
    groupsArray = response.getAsJsonArray("data");
} else if (response.has("data") && response.get("data").isJsonObject()) {
    // Paginated — data is an object with a "data" array inside
    groupsArray = response.getAsJsonObject("data").getAsJsonArray("data");
} else {
    groupsArray = new JsonArray();
}
```

### Gotcha 4: PDF export returns binary, not JSON

When calling `GET /topics/{id}/export/pdf`, the API returns a PDF file, not JSON. In JavaFX, open it in the browser or save to file:

```java
// Use Java's Desktop class to open in default PDF viewer:
java.awt.Desktop.getDesktop().browse(
    new URI(ApiClient.getInstance().getBaseUrl() + "/topics/" + topicId + "/export/pdf")
);
```

But you need the auth token. Better approach: download the PDF using OkHttp's `Response` directly (not through `ApiClient.get()`), then save to `Downloads/` and open.

### Gotcha 5: Profile picture upload needs multipart

The `POST /profile/picture` endpoint expects `multipart/form-data`, not JSON. You need a separate method in `ApiClient`:

```java
// In ApiClient.java, add:
public JsonObject uploadFile(String endpoint, String filePath, String fieldName) throws ApiException {
    String url = BASE_URL + endpoint;
    
    OkHttpClient client = new OkHttpClient();  // separate client for multipart
    RequestBody fileBody = RequestBody.create(
        new java.io.File(filePath),
        MediaType.parse("image/jpeg")
    );
    
    MultipartBody multipartBody = new MultipartBody.Builder()
        .setType(MultipartBody.FORM)
        .addFormDataPart(fieldName, new java.io.File(filePath).getName(), fileBody)
        .build();
    
    Request.Builder builder = new Request.Builder()
        .url(url)
        .post(multipartBody);
    
    attachToken(builder);
    
    try (Response response = client.newCall(builder.build()).execute()) {
        String responseBody = response.body() != null ? response.body().string() : "{}";
        // ... error handling ...
        return JsonParser.parseString(responseBody).getAsJsonObject();
    }
}
```

### Gotcha 6: Login 403 is special

When a warned user tries to log in, the API returns:
- HTTP 403 (not 401)
- Body: `{ "message": "...", "requires_warning_acknowledgement": true, "user": { ... } }`

The `AuthManager.login()` method already handles this. But know that a 403 doesn't always mean "wrong password" — it could mean "you need to acknowledge a warning."

### Gotcha 7: Thread safety

**NEVER** update the UI from a background thread. Always use:
```java
// WRONG — will crash:
new Thread(() -> {
    label.setText("Hello");  // 💥 Not on JavaFX thread!
}).start();

// CORRECT:
Platform.runLater(() -> {
    label.setText("Hello");
});
```

The `Task.setOnSucceeded()` callback already runs on the JavaFX thread, so you're safe inside those handlers.

---

## 16. Quick Reference: Pattern for Every Screen

```java
package com.yourforum.views;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.yourforum.api.ApiClient;
import com.yourforum.api.ApiException;
import com.yourforum.utils.AlertHelper;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import javafx.geometry.Insets;

public class ExampleListView {

    public static Parent create() {
        VBox root = new VBox(10);
        root.setPadding(new Insets(20));

        Label title = new Label("Title");
        title.setStyle("-fx-font-size: 20px; -fx-font-weight: bold;");

        TableView<SomeModel> table = new TableView<>();
        // ... configure columns ...

        ProgressIndicator loading = new ProgressIndicator();

        Button refreshBtn = new Button("Refresh");
        refreshBtn.setOnAction(e -> loadData(table, loading));

        root.getChildren().addAll(title, refreshBtn, loading, table);

        // Load data on creation
        loadData(table, loading);

        return root;
    }

    private static void loadData(TableView<SomeModel> table, ProgressIndicator loading) {
        loading.setVisible(true);

        javafx.concurrent.Task<java.util.List<SomeModel>> task =
                new javafx.concurrent.Task<>() {
            @Override
            protected java.util.List<SomeModel> call() throws Exception {
                return fetchFromApi();
            }
        };

        task.setOnSucceeded(e -> {
            loading.setVisible(false);
            table.getItems().setAll(task.getValue());
        });

        task.setOnFailed(e -> {
            loading.setVisible(false);
            AlertHelper.showError("Error", task.getException().getMessage());
        });

        new Thread(task).start();
    }

    private static java.util.List<SomeModel> fetchFromApi() throws ApiException {
        JsonObject response = ApiClient.getInstance().get("/endpoint");
        JsonArray dataArray = response.getAsJsonArray("data");

        java.util.List<SomeModel> items = new java.util.ArrayList<>();
        for (JsonElement element : dataArray) {
            items.add(new com.google.gson.Gson().fromJson(element, SomeModel.class));
        }
        return items;
    }
}
```

---

## 17. API Response Reference

### Standard Success Response (most endpoints):
```json
{
    "data": { ... },
    "message": "Success message"
}
```

### Paginated Response:
```json
{
    "data": [
        { "id": 1, "title": "..." },
        { "id": 2, "title": "..." }
    ],
    "links": {
        "first": "...",
        "last": "...",
        "prev": null,
        "next": "..."
    },
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 20,
        "total": 100
    }
}
```

### Error Response:
```json
{
    "message": "Invalid credentials."
}
```

### Login Success (200):
```json
{
    "message": "Login successful",
    "token": "1|abcdef123456...",
    "user": {
        "id": 1,
        "full_name": "John Doe",
        "email": "john@example.com",
        "account_status": "active",
        "role": "Student",
        "group": "Group A",
        "email_verified_at": null,
        "last_active_at": "2025-01-01T12:00:00"
    }
}
```

### Login Warned (403):
```json
{
    "message": "Your account is warned. Please acknowledge the warning before continuing.",
    "requires_warning_acknowledgement": true,
    "user": {
        "id": 1,
        "full_name": "John Doe",
        "email": "john@example.com",
        "account_status": "warned",
        "role": "Student",
        "group": "Group A"
    }
}
```

### Login Blacklisted (403):
```json
{
    "message": "Your account is blacklisted until Jan 15, 2025."
}
```

### Login Rate Limited (429):
```json
{
    "message": "Too many login attempts. Try again in 30 seconds."
}
```

### /me Response (200):
```json
{
    "user": {
        "id": 1,
        "full_name": "John Doe",
        "email": "john@example.com",
        "account_status": "active",
        "role": { "id": 3, "name": "Student" },
        "group": { "id": 1, "name": "Group A" },
        "email_verified_at": null,
        "last_active_at": null,
        "profile_picture": null,
        "created_at": "2025-01-01T12:00:00",
        "updated_at": "2025-01-01T12:00:00"
    }
}
```

---

**That's the complete guide.** Each person follows their section and builds their screens using the pattern shown in Section 8. Start with Phase 0 (all 5 together), then split up. Person 1 finishes Phases 1-3 first so the rest have something to plug into.
