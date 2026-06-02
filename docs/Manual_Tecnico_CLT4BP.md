# Technical Manual — CLT4BP
## Instructional Design Platform based on Cognitive Load Theory

**Version:** 1.0
**Date:** February 2026
**Project Status:** Sprints 1–7 completed

---

## Table of Contents

1. [General Overview](#1-general-overview)
2. [System Architecture](#2-system-architecture)
3. [Technology Stack](#3-technology-stack)
4. [Project Structure](#4-project-structure)
5. [Installation and Configuration](#5-installation-and-configuration)
6. [Database](#6-database)
7. [Laravel Backend — REST API](#7-laravel-backend--rest-api)
8. [Python AI Service](#8-python-ai-service)
9. [Vue.js Frontend](#9-vuejs-frontend)
10. [Authentication and Roles System](#10-authentication-and-roles-system)
11. [Functional Modules](#11-functional-modules)
12. [API Endpoints](#12-api-endpoints)
13. [Environment Variables](#13-environment-variables)
14. [Data Flow and Key Processes](#14-data-flow-and-key-processes)
15. [Implemented CLT Effects](#15-implemented-clt-effects)
16. [Assessment Types](#16-assessment-types)
17. [AI-Assisted Material Generation](#17-ai-assisted-material-generation)
18. [Development Commands](#18-development-commands)
19. [Testing](#19-testing)

---

## 1. General Overview

**CLT4BP** (*Cognitive Load Theory for Best Practice*) is an intelligent educational platform that integrates **Cognitive Load Theory (CLT)** and the **4C/ID (Four-Component Instructional Design)** instructional model to generate personalised learning materials using Artificial Intelligence.

### Purpose

The system enables instructors to:
- Create courses with structured learning objectives
- Administer standardised assessments (MSLQ, CLS, CIS, IMMS, Recall, Comprehension)
- Automatically generate individual and group learning profiles
- Select appropriate CLT effects for the pedagogical context
- Generate personalised instructional materials assisted by AI (Claude API)
- Collect and export data for educational research

The system enables students to:
- Access courses and personalised learning materials
- Complete diagnostic and performance assessments
- View their own progress and learning report

### Pedagogical Foundation

The platform applies the principles of **Cognitive Load Theory** (Sweller, 1988) and the **4C/ID** model (van Merriënboer, 1997), which structure instructional design around four components:
1. **Learning Tasks** — Full skills practice
2. **Supportive Information** — Conceptual foundation for transfer
3. **Procedural Information** — Step-by-step instructions
4. **Part-Task Practice** — Automation exercises

---

## 2. System Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│                         CLIENT (Browser)                         │
│                    Vue.js 3 SPA + Tailwind CSS                   │
│                         Port: 5173 (dev)                         │
└─────────────────────────────┬────────────────────────────────────┘
                              │ HTTP/REST (Axios)
                              │
┌─────────────────────────────▼────────────────────────────────────┐
│                    MAIN BACKEND (Laravel 12)                      │
│              REST API + Eloquent ORM + Sanctum Auth              │
│                         Port: 8000                               │
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────────┐ │
│  │  Controllers │  │   Models     │  │      Services          │ │
│  │  (API REST)  │  │  (Eloquent)  │  │  (ProfileGenerator,    │ │
│  └──────────────┘  └──────────────┘  │   MaterialService)     │ │
│                                      └────────────────────────┘ │
└──────┬────────────────────────────────────────────┬─────────────┘
       │                                            │
┌──────▼───────┐                       ┌────────────▼────────────┐
│  PostgreSQL  │                       │  AI Service (Python)    │
│  Port 5432   │                       │  FastAPI + Claude API   │
│  Database    │                       │  Port: 8001             │
└──────────────┘                       └─────────────────────────┘
       │
┌──────▼───────┐
│    Redis     │
│  Port 6379   │
│  Cache/Queue │
│  /Session    │
└──────────────┘
```

### Inter-Service Communication

- **Frontend → Laravel**: HTTP REST with Sanctum tokens (Bearer Token)
- **Laravel → Python AI**: Internal HTTP with shared token (`X-API-Token` header)
- **Laravel → PostgreSQL**: Direct PDO connection via Eloquent ORM
- **Laravel → Redis**: Predis client for cache, queues and sessions

---

## 3. Technology Stack

### Main Backend
| Component | Technology | Version |
|---|---|---|
| Framework | Laravel | 12.0 |
| Language | PHP | 8.2+ |
| ORM | Eloquent | — |
| Authentication | Laravel Sanctum | 4.2 |
| Redis Client | Predis | 3.3 |
| Database | PostgreSQL | — |
| Cache / Queues | Redis | — |

### Frontend
| Component | Technology | Version |
|---|---|---|
| Framework | Vue.js | 3.5.27 |
| Global State | Pinia | 3.0.4 |
| Routing | Vue Router | 4.6.4 |
| HTTP Client | Axios | 1.13.2 |
| CSS Framework | Tailwind CSS | 3.4.19 |
| UI Components | DaisyUI | 4.12.24 |
| Build Tool | Vite | 7.0.7 |

### AI Service
| Component | Technology | Version |
|---|---|---|
| Framework | FastAPI | 0.128.0 |
| Language | Python | 3.x |
| ASGI Server | Uvicorn | 0.40.0 |
| Validation | Pydantic | 2.12.5 |
| Claude SDK | Anthropic | 0.76.0 |
| HTTP Client | HTTPX | 0.28.1 |

### Infrastructure
| Component | Details |
|---|---|
| Database | PostgreSQL (port 5432, database: `clt4bp_dev3`) |
| Cache | Redis (port 6379) |
| Queues | Redis Queue |
| AI | Claude API (Anthropic) — model `claude-sonnet-4-20250514` |

---

## 4. Project Structure

```
CLT4BP/
├── backend-laravel/              # Main Laravel backend
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Api/          # REST Controllers
│   │   │   │   │   ├── AuthController.php
│   │   │   │   │   ├── UserController.php
│   │   │   │   │   ├── CourseController.php
│   │   │   │   │   ├── EnrollmentController.php
│   │   │   │   │   ├── AssessmentController.php
│   │   │   │   │   ├── StudentResponseController.php
│   │   │   │   │   ├── ProfileController.php
│   │   │   │   │   ├── TemplateController.php
│   │   │   │   │   ├── GradingController.php
│   │   │   │   │   ├── DataCollectionController.php
│   │   │   │   │   └── ReportController.php
│   │   │   │   ├── CltEffectsController.php
│   │   │   │   └── MaterialController.php
│   │   │   └── Middleware/
│   │   ├── Models/               # Eloquent Models
│   │   │   ├── User.php
│   │   │   ├── Course.php
│   │   │   ├── CourseEnrollment.php
│   │   │   ├── Assessment.php
│   │   │   ├── StudentResponse.php
│   │   │   ├── StudentProfile.php
│   │   │   ├── GroupProfile.php
│   │   │   ├── CltEffectsSelection.php
│   │   │   ├── InstructionalMaterial.php
│   │   │   └── MaterialAccessLog.php
│   │   └── Services/             # Business services
│   │       ├── StudentProfileGeneratorService.php
│   │       ├── GroupProfileGeneratorService.php
│   │       └── (MaterialService)
│   ├── database/
│   │   ├── migrations/           # 16 migrations
│   │   └── seeders/              # Assessment templates
│   ├── resources/
│   │   └── js/                   # Vue.js Frontend
│   │       ├── views/            # Views by role
│   │       │   ├── auth/
│   │       │   ├── admin/
│   │       │   ├── instructor/
│   │       │   ├── student/
│   │       │   ├── courses/
│   │       │   └── assessments/
│   │       ├── stores/           # Pinia stores
│   │       ├── services/         # Axios modules
│   │       ├── components/       # Vue components
│   │       ├── router/           # Vue Router config
│   │       └── composables/      # Vue composables
│   ├── routes/
│   │   └── api.php               # API route definitions
│   ├── composer.json
│   ├── package.json
│   ├── vite.config.js
│   └── tailwind.config.js
│
├── backend-python/               # FastAPI AI Service
│   ├── app/
│   │   ├── main.py               # FastAPI entry point
│   │   ├── config.py             # Pydantic Settings configuration
│   │   ├── routers/              # FastAPI endpoints
│   │   │   └── materials.py
│   │   ├── services/             # AI business logic
│   │   ├── schemas/              # Pydantic schemas
│   │   ├── models/               # Data models
│   │   ├── core/                 # Core utilities
│   │   ├── api/                  # API layer
│   │   └── utils/                # Auxiliary tools
│   ├── requirements.txt
│   └── run.py                    # Uvicorn startup
│
├── docs/                         # Documentation
├── scripts/                      # Deployment scripts
├── Arquitectura_CLT4BP_MVP.pdf
├── Despliegue.pdf
├── iniciarservers.txt
└── Progreso.txt
```

---

## 5. Installation and Configuration

### Prerequisites

| Software | Minimum version |
|---|---|
| PHP | 8.2+ |
| Composer | 2.x |
| Node.js | 18+ |
| npm | 9+ |
| PostgreSQL | 14+ |
| Redis | 7+ |
| Python | 3.10+ |

### Laravel Backend Installation

```bash
cd backend-laravel

# 1. Install PHP dependencies
composer install

# 2. Copy and configure the environment
cp .env.example .env
php artisan key:generate

# 3. Configure the database in .env (see section 13)

# 4. Run migrations
php artisan migrate

# 5. Seed assessment templates
php artisan db:seed

# 6. Install Node.js dependencies
npm install

# 7. Compile production assets
npm run build
```

### Python AI Service Installation

```bash
cd backend-python

# 1. Create virtual environment
python -m venv venv

# Activate on Windows
.\venv\Scripts\activate

# Activate on Linux/macOS
source venv/bin/activate

# 2. Install dependencies
pip install -r requirements.txt

# 3. Configure environment variables
# Create .env file (see section 13)
```

### Starting Services in Development

```bash
# Terminal 1: Laravel Server (API)
cd backend-laravel
php artisan serve
# Available at: http://localhost:8000

# Terminal 2: Vite (Frontend hot-reload)
cd backend-laravel
npm run dev
# Available at: http://localhost:5173

# Terminal 3: Python AI Service
cd backend-python
.\venv\Scripts\activate  # Windows
python run.py
# Available at: http://localhost:8001
```

### Automated Setup (single command)

```bash
cd backend-laravel
composer run setup    # Installs everything and compiles assets
composer run dev      # Starts all processes in parallel
```

---

## 6. Database

### Engine and Configuration

- **Engine**: PostgreSQL
- **Port**: 5432
- **Database (development)**: `clt4bp_dev3`
- **User**: `postgres`

### Table Schema

#### `users`
```sql
id              BIGINT PRIMARY KEY
name            VARCHAR
email           VARCHAR UNIQUE
password        VARCHAR (hashed)
role            ENUM('admin', 'instructor', 'student')
is_active       BOOLEAN DEFAULT true
last_login      TIMESTAMP NULL
email_verified_at TIMESTAMP NULL
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

#### `courses`
```sql
id                  BIGINT PRIMARY KEY
instructor_id       BIGINT FK → users.id
title               VARCHAR
description         TEXT
learning_objectives JSON        -- Array of objectives
status              ENUM('draft', 'active', 'inactive', 'completed')
start_date          DATE NULL
end_date            DATE NULL
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

#### `course_enrollments`
```sql
id              BIGINT PRIMARY KEY
student_id      BIGINT FK → users.id
course_id       BIGINT FK → courses.id
status          VARCHAR
enrollment_date DATE
completion_date DATE NULL
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

#### `assessments`
```sql
id                      BIGINT PRIMARY KEY
course_id               BIGINT FK → courses.id
assessment_type         ENUM (see section 16)
title                   VARCHAR
description             TEXT NULL
questions               JSON        -- Array of questions
config                  JSON NULL   -- Additional configuration
is_active               BOOLEAN DEFAULT false
time_limit              INT NULL    -- Minutes
is_template             BOOLEAN DEFAULT false
requires_manual_grading BOOLEAN DEFAULT false
source_template_id      BIGINT FK → assessments.id NULL
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

#### `student_responses`
```sql
id              BIGINT PRIMARY KEY
assessment_id   BIGINT FK → assessments.id
student_id      BIGINT FK → users.id
responses       JSON        -- Responses per question
score           DECIMAL NULL
time_spent      INT NULL    -- Seconds
started_at      TIMESTAMP NULL
completed_at    TIMESTAMP NULL
grading_status  ENUM('auto_graded', 'pending_grading', 'graded')
manual_scores   JSON NULL   -- Manual scores
graded_by       BIGINT FK → users.id NULL
graded_at       TIMESTAMP NULL
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

#### `student_profiles`
```sql
id              BIGINT PRIMARY KEY
student_id      BIGINT FK → users.id
course_id       BIGINT FK → courses.id
profile_data    JSON        -- Generated profile data
generated_at    TIMESTAMP
created_at      TIMESTAMP
updated_at      TIMESTAMP
UNIQUE (student_id, course_id)
```

#### `group_profiles`
```sql
id              BIGINT PRIMARY KEY
course_id       BIGINT FK → courses.id
profile_data    JSON        -- Group profile data
student_count   INT
generated_at    TIMESTAMP
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

#### `clt_effects_selections`
```sql
id                BIGINT PRIMARY KEY
course_id         BIGINT FK → courses.id
selected_effects  JSON    -- Array of selected effect IDs
created_at        TIMESTAMP
updated_at        TIMESTAMP
```

#### `instructional_materials`
```sql
id                BIGINT PRIMARY KEY
course_id         BIGINT FK → courses.id
material_type     VARCHAR     -- learning_tasks | support_info | procedural_info | verbal_protocols | example
target_type       ENUM('individual', 'group')
target_student_id BIGINT FK → users.id NULL  -- For individual type only
content           JSON        -- AI-generated content
is_active         BOOLEAN DEFAULT false
timer_seconds     INT NULL
activated_at      TIMESTAMP NULL
deactivated_at    TIMESTAMP NULL
created_at        TIMESTAMP
updated_at        TIMESTAMP
```

#### `material_access_logs`
```sql
id                BIGINT PRIMARY KEY
material_id       BIGINT FK → instructional_materials.id
student_id        BIGINT FK → users.id
started_at        TIMESTAMP
completed_at      TIMESTAMP NULL
duration_seconds  INT NULL
created_at        TIMESTAMP
updated_at        TIMESTAMP
```

#### `personal_access_tokens` (Laravel Sanctum)
```sql
id          BIGINT PRIMARY KEY
tokenable_type  VARCHAR
tokenable_id    BIGINT
name        VARCHAR
token       VARCHAR (hashed)
abilities   TEXT NULL
last_used_at    TIMESTAMP NULL
expires_at  TIMESTAMP NULL
created_at  TIMESTAMP
updated_at  TIMESTAMP
```

### Migrations

Migrations are executed in chronological order:

| Migration | Table | Description |
|---|---|---|
| `0001_01_01_000000` | users | Base users table |
| `0001_01_01_000001` | cache | Cache table |
| `0001_01_01_000002` | jobs | Queues table |
| `2026_01_23_004436` | personal_access_tokens | Sanctum tokens |
| `2026_01_23_004453` | users | Role column |
| `2026_01_23_171600` | courses | Courses table |
| `2026_01_23_171800` | course_enrollments | Enrollments |
| `2026_01_23_215325` | assessments | Assessments |
| `2026_01_23_215326` | student_responses | Responses |
| `2026_01_25_033852` | student_profiles | Individual profiles |
| `2026_01_25_034126` | group_profiles | Group profiles |
| `2026_01_25_120000` | assessments | Template and grading support |
| `2026_01_25_130000` | assessments | prior_knowledge type |
| `2026_01_26_230627` | clt_effects_selections | CLT effects |
| `2026_01_26_231551` | instructional_materials | AI materials |
| `2026_02_11_000001` | material_access_logs | Access logs |

---

## 7. Laravel Backend — REST API

### Controller Organisation

```
app/Http/Controllers/
├── Api/
│   ├── AuthController.php          # Register, login, logout, profile
│   ├── UserController.php          # User CRUD, role management
│   ├── CourseController.php        # Course CRUD
│   ├── EnrollmentController.php    # Student enrollments
│   ├── AssessmentController.php    # Assessment CRUD
│   ├── StudentResponseController.php # Student responses
│   ├── ProfileController.php       # Individual and group profiles
│   ├── TemplateController.php      # Assessment templates
│   ├── GradingController.php       # Manual grading
│   ├── DataCollectionController.php # Data collection and research
│   └── ReportController.php        # Instructor/student reports
├── CltEffectsController.php        # CLT effects management
└── MaterialController.php          # AI instructional materials
```

### Business Services

```
app/Services/
├── StudentProfileGeneratorService.php
│   └── Aggregates assessment results and generates the student's JSON profile
└── GroupProfileGeneratorService.php
    └── Consolidates individual profiles into the course group profile
```

### Role Middleware

The application implements a custom `role` middleware that validates the authenticated user has the required role:

```php
Route::middleware('role:admin,instructor')->group(...)
Route::middleware('role:student')->group(...)
```

The three available roles are:
- **admin**: Full system access
- **instructor**: Course, assessment and material management
- **student**: Access to materials, own assessments and personal reports

---

## 8. Python AI Service

### FastAPI Architecture

```
backend-python/app/
├── main.py             # FastAPI app + CORS + middleware + routers
├── config.py           # Pydantic Settings (environment variables)
├── routers/
│   └── materials.py    # Material generation endpoints
├── services/           # Claude API call logic
├── schemas/            # Request/response validation (Pydantic)
├── models/             # Internal data models
├── core/               # Core utilities and configuration
└── utils/              # Auxiliary tools
```

### Service Authentication

The Python service validates a shared token with Laravel on every request:

```python
async def verify_api_token(x_api_token: str = Header(...)):
    if x_api_token != settings.laravel_api_token:
        raise HTTPException(status_code=401, detail="Invalid API token")
```

### Claude API Configuration

| Parameter | Description |
|---|---|
| `ANTHROPIC_API_KEY` | Anthropic API access key |
| `CLAUDE_MODEL` | `claude-sonnet-4-20250514` (default) |
| `MAX_TOKENS` | Maximum tokens in the response |
| `TEMPERATURE` | Generation creativity (0.0–1.0) |
| `MAX_RETRIES` | Retries on rate limit errors |
| `RETRY_DELAY` | Wait time between retries |
| `TIMEOUT` | Maximum wait time for a response |

### Python Service Endpoints

```
GET  /                          # Service status
GET  /health                    # Health check
POST /api/v1/materials/generate # Generate instructional material
POST /api/v1/materials/validate # Validate API connection
GET  /api/v1/materials/clt-effects # List available CLT effects
```

### Server Startup

```python
# run.py
uvicorn.run(
    "app.main:app",
    host="0.0.0.0",
    port=8001,
    reload=True,     # Development only
    log_level="info"
)
```

---

## 9. Vue.js Frontend

### View Structure

```
resources/js/
├── App.vue               # Root component
├── app.js                # Entry point + Vue mount
├── bootstrap.js          # Axios configuration
├── router/               # Vue Router — role-protected routes
├── stores/               # Pinia stores (auth, courses, etc.)
├── services/             # API call modules
├── components/           # Reusable components
├── composables/          # Reusable reactive logic
├── utils/                # Utility functions
└── views/
    ├── auth/
    │   ├── LoginView.vue
    │   └── RegisterView.vue
    ├── DashboardView.vue
    ├── ProfileView.vue
    ├── admin/            # Administrator views
    ├── instructor/       # Instructor views
    ├── student/          # Student views
    ├── courses/          # Course views
    └── assessments/      # Assessment views
```

### Main Routes

| Route | Component | Role |
|---|---|---|
| `/login` | `LoginView.vue` | Public |
| `/register` | `RegisterView.vue` | Public |
| `/dashboard` | `DashboardView.vue` | All |
| `/admin/users` | Admin views | Administrator |
| `/instructor/courses` | Instructor views | Instructor |
| `/student/courses` | Student views | Student |

### State Management (Pinia)

Pinia stores maintain the reactive state of the application:
- **Auth store**: Session token, user data, active role
- **Course store**: Loaded courses, enrollments
- **Assessment store**: Assessments for the active course
- **Profile store**: Loaded profiles
- **Material store**: Instructional materials

### Build and Compilation

```bash
# Development (with hot-reload)
npm run dev

# Production (optimised)
npm run build
# Generates: public/build/
```

### Vite Configuration

```js
// vite.config.js
plugins: [
    laravel({ input: ['resources/css/app.css', 'resources/js/app.js'] }),
    vue()
]
// Alias @ → resources/js
```

---

## 10. Authentication and Roles System

### Authentication with Laravel Sanctum

CLT4BP uses Sanctum **API Token Authentication** (not SPA cookies). The flow is:

1. User sends `POST /api/login` with email and password
2. Laravel validates credentials and generates a personal token
3. The token is returned in the response and the frontend stores it
4. All subsequent requests include the header: `Authorization: Bearer {token}`
5. On logout (`POST /api/logout`), the token is revoked

### Roles and Permissions

| Operation | Admin | Instructor | Student |
|---|:---:|:---:|:---:|
| Create instructor | ✓ | — | — |
| Deactivate user | ✓ | — | — |
| View user list | ✓ | ✓ | — |
| Create/edit courses | ✓ | ✓ | — |
| Enrol students | ✓ | ✓ | — |
| Create assessments | ✓ | ✓ | — |
| Grade manually | ✓ | ✓ | — |
| Generate AI profiles | ✓ | ✓ | — |
| Select CLT effects | ✓ | ✓ | — |
| Generate AI materials | ✓ | ✓ | — |
| View instructor reports | ✓ | ✓ | — |
| Export CSV data | ✓ | ✓ | — |
| Take assessments | — | — | ✓ |
| View assigned materials | — | — | ✓ |
| View own report | — | — | ✓ |
| View my enrolled courses | — | — | ✓ |

---

## 11. Functional Modules

### 11.1 User Management

**File:** `app/Http/Controllers/Api/UserController.php`

Allows the Administrator to create instructor accounts, list all users and deactivate accounts. Instructors can view the system user list. All users can update their own profile.

### 11.2 Course Management

**File:** `app/Http/Controllers/Api/CourseController.php`

Courses have lifecycle states: `draft → active → inactive / completed`. Each course includes:
- Title and description
- Learning objectives (JSON array)
- Optional start and end dates
- List of enrolled students

### 11.3 Assessment System

**File:** `app/Http/Controllers/Api/AssessmentController.php`

Assessments are created from scratch or from predefined templates. They support:
- **Multiple-choice questions**: Auto-graded by comparing against `correct_answer`
- **Open-ended questions** (text, essay): Require manual grading
- **Likert scale**: For psychometric questionnaires (MSLQ, CLS, IMMS, CIS)
- **Time limit**: Timer configurable in minutes

#### Grading States

```
auto_graded       → Objective questions only, graded automatically
pending_grading   → Contains open-ended questions not yet graded
graded            → All questions graded (auto + manual)
```

### 11.4 Learning Profiles

**File:** `app/Http/Controllers/Api/ProfileController.php`
**Services:** `StudentProfileGeneratorService`, `GroupProfileGeneratorService`

The system automatically generates learning profiles by querying the results of all the student's assessments in the course:

**Individual Profile includes:**
- Knowledge level (recall + comprehension)
- Motivation level (MSLQ motivation)
- Learning strategies (MSLQ strategies)
- Cognitive load (CLS)
- Course interest (CIS)
- Motivation towards materials (IMMS)
- Pre/post performance metrics

**Group Profile includes:**
- Averages of all individual indicators
- Statistical distribution of the group
- Number of students considered
- Recommendations for the group

### 11.5 CLT Effects

**File:** `app/Http/Controllers/CltEffectsController.php`

The Instructor selects up to 16 CLT effects to guide material generation. The selected effects are stored per course and incorporated into the AI prompt.

### 11.6 AI Material Generation

**File:** `app/Http/Controllers/MaterialController.php`

The Instructor requests the generation of a material by specifying:
- Material type (learning_tasks, support_info, procedural_info, verbal_protocols, example)
- Target: individual (one student) or group (entire course)
- Specific topic to develop

The system:
1. Retrieves the target student or group profile
2. Obtains the CLT effects selected for the course
3. Sends the request to the Python service with all the data
4. The service calls the Claude API with a structured prompt
5. The generated material is stored as `is_active = false` (pending review)
6. The Instructor reviews and activates the material

### 11.7 Manual Grading

**File:** `app/Http/Controllers/Api/GradingController.php`

For assessments with open-ended questions (essay, text), the Instructor can:
- View the list of responses pending grading
- Review each response individually
- Assign manual scores per question
- Revert a manual grade if needed
- Recalculate the grading status for the course

### 11.8 Data Collection and Research

**File:** `app/Http/Controllers/Api/DataCollectionController.php`

Provides tools for researchers and instructors:
- **Course summary**: General participation and performance statistics
- **Pre/post comparison**: Contrast of metrics before and after the intervention
- **CSV export**: All course data in tabular format for external analysis
- **Student detail**: Complete data for an individual student

### 11.9 Reports

**File:** `app/Http/Controllers/Api/ReportController.php`

- **Instructor Report**: Consolidated view of course performance, assessment statistics and material access
- **Student Report**: Personal progress, scores per assessment and materials accessed

### 11.10 Material Access Logs

The system records when students:
- **Start** accessing a material (`POST .../access/start`)
- **Complete** reviewing a material (`POST .../access/complete`)

This allows the Instructor to see who has accessed each material and how much time was spent.

---

## 12. API Endpoints

**Base URL**: `http://localhost:8000/api`

### Authentication

| Method | Route | Description | Roles |
|---|---|---|---|
| POST | `/register` | Register user | Public |
| POST | `/login` | Log in | Public |
| POST | `/logout` | Log out | All |
| GET | `/me` | Get current user | All |
| GET | `/health` | Server status | Public |

### Users

| Method | Route | Description | Roles |
|---|---|---|---|
| GET | `/users` | List users | Admin, Instructor |
| GET | `/users/{id}` | View user | All |
| PUT | `/users/{id}` | Update user | All |
| POST | `/users/instructors` | Create instructor | Admin |
| DELETE | `/users/{id}/deactivate` | Deactivate user | Admin |

### Courses

| Method | Route | Description | Roles |
|---|---|---|---|
| GET | `/courses/{id}` | View course | All |
| GET | `/courses` | List courses | Admin, Instructor |
| POST | `/courses` | Create course | Admin, Instructor |
| PUT | `/courses/{id}` | Update course | Admin, Instructor |
| DELETE | `/courses/{id}` | Delete course | Admin, Instructor |
| POST | `/courses/{id}/enroll` | Enrol student | Admin, Instructor |
| GET | `/courses/{id}/students` | List enrolled students | Admin, Instructor |
| DELETE | `/courses/{id}/students/{sid}` | Unenrol student | Admin, Instructor |
| GET | `/my-enrollments` | My enrolled courses | Student |

### Assessments

| Method | Route | Description | Roles |
|---|---|---|---|
| GET | `/courses/{c}/assessments` | List assessments | All |
| POST | `/courses/{c}/assessments` | Create assessment | Admin, Instructor |
| GET | `/courses/{c}/assessments/{a}` | View assessment | All |
| PUT | `/courses/{c}/assessments/{a}` | Update assessment | Admin, Instructor |
| DELETE | `/courses/{c}/assessments/{a}` | Delete assessment | Admin, Instructor |
| POST | `/courses/{c}/assessments/{a}/toggle` | Activate/deactivate | Admin, Instructor |
| POST | `/courses/{c}/assessments/from-template/{t}` | Create from template | Admin, Instructor |
| GET | `/courses/{c}/available-templates` | Available templates | Admin, Instructor |
| GET | `/assessment-templates` | List global templates | Admin, Instructor |

### Student Responses

| Method | Route | Description | Roles |
|---|---|---|---|
| POST | `/courses/{c}/assessments/{a}/start` | Start assessment | Student |
| POST | `/courses/{c}/assessments/{a}/save` | Save response | Student |
| GET | `/courses/{c}/assessments/{a}/my-response` | View my response | Student |
| GET | `/courses/{c}/assessments/{a}/responses` | View all responses | Admin, Instructor |
| GET | `/courses/{c}/my-responses` | My responses in course | Student |

### Profiles

| Method | Route | Description | Roles |
|---|---|---|---|
| GET | `/courses/{c}/profiles/students` | Course profiles | Admin, Instructor |
| GET | `/courses/{c}/profiles/students/{sid}` | Student profile | Admin, Instructor |
| GET | `/courses/{c}/profiles/group` | Group profile | Admin, Instructor |
| POST | `/courses/{c}/profiles/students/{sid}/generate` | Generate individual profile | Admin, Instructor |
| POST | `/courses/{c}/profiles/students/generate-all` | Generate all profiles | Admin, Instructor |
| POST | `/courses/{c}/profiles/group/generate` | Generate group profile | Admin, Instructor |
| POST | `/courses/{c}/profiles/regenerate-all` | Regenerate all | Admin, Instructor |

### CLT Effects

| Method | Route | Description | Roles |
|---|---|---|---|
| GET | `/courses/{c}/clt-effects/available` | Available effects | All |
| GET | `/courses/{c}/clt-effects/selection` | Selected effects | All |
| POST | `/courses/{c}/clt-effects/selection` | Save selection | Admin, Instructor |
| GET | `/courses/{c}/clt-effects/recommendations` | Recommendations | All |

### Instructional Materials

| Method | Route | Description | Roles |
|---|---|---|---|
| POST | `/courses/{c}/materials/generate` | Generate with AI | Admin, Instructor |
| GET | `/courses/{c}/materials` | List materials | Admin, Instructor |
| GET | `/courses/{c}/materials/{m}` | View material | Admin, Instructor |
| POST | `/courses/{c}/materials/{m}/toggle-active` | Activate/deactivate | Admin, Instructor |
| PUT | `/courses/{c}/materials/{m}/timer` | Configure timer | Admin, Instructor |
| GET | `/courses/{c}/materials/{m}/access-logs` | View access logs | Admin, Instructor |
| GET | `/courses/{c}/materials/student/list` | View my materials | Student |
| POST | `/courses/{c}/materials/{m}/access/start` | Log access start | Student |
| POST | `/courses/{c}/materials/{m}/access/complete` | Log access end | Student |

### Manual Grading

| Method | Route | Description | Roles |
|---|---|---|---|
| GET | `/courses/{c}/pending-grading` | Course pending items | Admin, Instructor |
| POST | `/courses/{c}/recalculate-grading` | Recalculate states | Admin, Instructor |
| GET | `/courses/{c}/assessments/{a}/pending-grading` | Pending per assessment | Admin, Instructor |
| GET | `/courses/{c}/assessments/{a}/responses/{r}/grading` | View for grading | Admin, Instructor |
| POST | `/courses/{c}/assessments/{a}/responses/{r}/grade` | Submit grade | Admin, Instructor |
| POST | `/courses/{c}/assessments/{a}/responses/{r}/revert-grade` | Revert grade | Admin, Instructor |

### Reports and Data

| Method | Route | Description | Roles |
|---|---|---|---|
| GET | `/courses/{c}/reports/instructor` | Instructor report | Admin, Instructor |
| GET | `/courses/{c}/reports/my-report` | Student report | Student |
| GET | `/courses/{c}/data-collection/summary` | Collection summary | Admin, Instructor |
| GET | `/courses/{c}/data-collection/pre-post-comparison` | Pre/post comparison | Admin, Instructor |
| GET | `/courses/{c}/data-collection/export` | Export CSV | Admin, Instructor |
| GET | `/courses/{c}/data-collection/student/{sid}` | Student detail | Admin, Instructor |

---

## 13. Environment Variables

### Laravel (`backend-laravel/.env`)

```env
# Application
APP_NAME=CLT4BP
APP_ENV=local
APP_KEY=base64:...           # Generated by artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_LOCALE=en

# Logs
LOG_CHANNEL=stack
LOG_LEVEL=debug

# PostgreSQL Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=clt4bp_dev3
DB_USERNAME=postgres
DB_PASSWORD=your_password

# Redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0

# Cache and Queues
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost:3000

# Python AI Service
AI_SERVICE_URL=http://localhost:8001
AI_SERVICE_TOKEN=your_shared_token
AI_SERVICE_TIMEOUT=120

# Claude API (if used directly from Laravel)
CLAUDE_API_KEY=sk-ant-...
CLAUDE_API_VERSION=2023-06-01
CLAUDE_MODEL=claude-sonnet-4-20250514
```

### Python (`backend-python/.env`)

```env
# Service
APP_NAME=CLT4BP-AI-Service
APP_VERSION=1.0.0
DEBUG=true

# Claude API
ANTHROPIC_API_KEY=sk-ant-...
CLAUDE_MODEL=claude-sonnet-4-20250514
MAX_TOKENS=4096
TEMPERATURE=0.7

# Laravel API (for token validation)
LARAVEL_API_URL=http://localhost:8000
LARAVEL_API_TOKEN=your_shared_token

# Service configuration
LOG_LEVEL=info
MAX_RETRIES=3
RETRY_DELAY=1.0
TIMEOUT=120
```

---

## 14. Data Flow and Key Processes

### 14.1 Complete Learning Cycle

```
1. Instructor creates Course
         ↓
2. Instructor enrols Students
         ↓
3. Instructor creates Assessments (from templates)
   - Pre-test: recall_initial, comprehension_initial
   - Diagnostic: mslq_motivation_initial, cognitive_load
         ↓
4. Students take the Assessments
         ↓
5. Instructor generates Learning Profiles
   (StudentProfileGeneratorService aggregates the data)
         ↓
6. Instructor selects appropriate CLT Effects
         ↓
7. Instructor generates Materials with AI
   (Claude generates personalised content)
         ↓
8. Instructor reviews and Activates the Materials
         ↓
9. Students access the Materials
         ↓
10. Instructor creates final Assessments
    - Post-test: recall_final, comprehension_final
    - Closing: mslq_motivation_final, imms, course_interest
         ↓
11. Instructor exports Data for research (CSV)
```

### 14.2 AI Material Generation Flow

```
Laravel MaterialController
    └─ Receives request with: courseId, materialType, targetType,
       targetStudentId (if individual), topic
         ↓
    └─ Retrieves student/group profile (from student_profiles or group_profiles)
         ↓
    └─ Retrieves selected CLT effects (from clt_effects_selections)
         ↓
    └─ Builds payload for Python service
         ↓
Python FastAPI Service (POST /api/v1/materials/generate)
    └─ Validates X-API-Token
         ↓
    └─ Builds system prompt (CLT + 4C/ID principles)
         ↓
    └─ Calls Claude API with: system prompt + profile data + CLT effects
         ↓
    └─ Claude generates the material in structured JSON format
         ↓
    └─ Validates generated JSON structure
         ↓
    └─ Returns material to Laravel
         ↓
Laravel saves InstructionalMaterial with is_active = false
    └─ Instructor reviews and activates
```

### 14.3 Student Profile Generation Flow

```
ProfileController::generateStudentProfile
    └─ Retrieves all student responses in the course
         ↓
StudentProfileGeneratorService
    └─ Extracts data from: recall_initial + recall_final
    └─ Extracts data from: comprehension_initial + comprehension_final
    └─ Extracts data from: cognitive_load (CLS)
    └─ Extracts data from: mslq_motivation_initial/final
    └─ Extracts data from: mslq_strategies
    └─ Extracts data from: course_interest (CIS)
    └─ Extracts data from: imms
         ↓
    └─ Calculates: knowledge_level = average(recall + comprehension scores)
    └─ Calculates: motivation_level = average(MSLQ motivation scores)
    └─ Calculates: learning_strategies = average(MSLQ strategies scores)
         ↓
    └─ Generates profile_data JSON with all indicators
         ↓
Saves to student_profiles (upsert by student_id + course_id)
```

---

## 15. Implemented CLT Effects

The platform implements **16 CLT effects** organised in two categories:

### For New Knowledge

| ID | Effect | Description |
|---|---|---|
| 1 | Goal-Free Effect | Solve problems without a specific goal to reduce means-ends search |
| 2 | Worked Examples | Present solved examples before the student attempts independently |
| 3 | Completion Problems | Provide partial solutions that the student must complete |
| 4 | Split Attention Management | Integrate separated sources of information to avoid split attention |
| 5 | Modality Effect | Combine visual and auditory information instead of visual-only |
| 6 | Redundancy Elimination | Remove redundant information that unnecessarily increases load |
| 7 | Variability | Vary examples to facilitate learning transfer |
| 8 | Isolated Elements | Present elements in isolation before combining them |
| 9 | Element Interactivity | Manage the interaction between information elements |

### For Prior Knowledge

| ID | Effect | Description |
|---|---|---|
| 10 | Self-Explanation | Ask the student to explain the material in their own words |
| 11 | Imagination/Visualization | Ask the student to mentally visualise concepts |
| 12 | Expertise Reversal | Reduce support as the student gains competence |
| 13 | Guidance-Fading | Gradually withdraw guidance as knowledge increases |
| 14 | Collective Memory | Leverage the distributed knowledge of the group |
| 15 | Self-Management | Allow the student to manage their own learning |
| 16 | Human Movement | Incorporate demonstration of human movements for procedural tasks |
| — | Transient Information Management | Manage transient information to reduce its fading effect |

---

## 16. Assessment Types

The system supports the following standardised assessment types:

| Type | Full Name | Purpose |
|---|---|---|
| `recall_initial` | Initial Recall Test | Measure prior knowledge before instruction |
| `recall_final` | Final Recall Test | Measure retention after instruction |
| `comprehension_initial` | Initial Comprehension Test | Measure conceptual understanding pre-instruction |
| `comprehension_final` | Final Comprehension Test | Measure conceptual understanding post-instruction |
| `cognitive_load` | Cognitive Load Scale (CLS) | Measure perceived cognitive load during learning |
| `mslq_motivation_initial` | MSLQ Initial Motivation | Measure motivation to learn (Pintrich et al.) |
| `mslq_motivation_final` | MSLQ Final Motivation | Compare motivation after the intervention |
| `mslq_strategies` | MSLQ Learning Strategies | Assess cognitive and metacognitive strategies |
| `course_interest` | Course Interest Scale (CIS) | Measure student interest in the course |
| `imms` | IMMS Scale | Measure motivation towards instructional materials (Keller) |

### Supported Question Types

| Type | Grading | Description |
|---|---|---|
| `multiple_choice` | Automatic | Single option with a defined `correct_answer` |
| `likert` | Automatic | Numeric scale (1–5 or 1–7) |
| `text` | Manual | Short open-ended text response |
| `essay` | Manual | Long extended-response answer |

---

## 17. AI-Assisted Material Generation

### Material Types

| Type | 4C/ID Component | Description |
|---|---|---|
| `learning_tasks` | Learning Tasks | Complete and authentic practical activities |
| `support_info` | Supportive Information | Conceptual foundation and mental models |
| `procedural_info` | Procedural Information | Step-by-step instructions and rules |
| `verbal_protocols` | Verbal Protocols | Examples of expert reasoning aloud |
| `example` | Worked Example | Complete solution of a representative problem |

### Material Target

- **Individual** (`target_type: 'individual'`): Generated specifically for a student's profile
- **Group** (`target_type: 'group'`): Generated for the aggregated profile of the entire course

### Material Lifecycle

```
generate (is_active = false)
    ↓ Instructor reviews
toggle-active (is_active = true)
    ↓ Student accesses
access/start → access/complete
    ↓ Instructor monitors
access-logs
```

### Claude API Integration

The prompt sent to Claude includes:
1. **Role and context**: Expert in CLT and 4C/ID-based instructional design
2. **Learner profile**: Knowledge level, motivation, strategies, cognitive load
3. **Course objectives**: The `learning_objectives` defined by the Instructor
4. **CLT effects to apply**: List of selected effects with their descriptions
5. **Material type**: Which 4C/ID component must be generated
6. **Specific topic**: The thematic content to develop
7. **Output format**: Structured JSON with validated fields

---

## 18. Development Commands

### Laravel (Artisan)

```bash
# Development server
php artisan serve

# Migrations
php artisan migrate
php artisan migrate:rollback
php artisan migrate:fresh --seed

# Seeders
php artisan db:seed
php artisan db:seed --class=AssessmentTemplateSeeder

# Cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Queues
php artisan queue:listen --tries=1 --timeout=0
php artisan queue:work

# Tinker (interactive REPL)
php artisan tinker

# Real-time logs
php artisan pail --timeout=0

# Linting (Laravel Pint)
./vendor/bin/pint
```

### Node.js / Frontend

```bash
# Install dependencies
npm install

# Development with hot-reload
npm run dev

# Production build
npm run build

# All processes in parallel
composer run dev
```

### Python

```bash
# Activate virtual environment (Windows)
.\venv\Scripts\activate

# Activate virtual environment (Linux/macOS)
source venv/bin/activate

# Install dependencies
pip install -r requirements.txt

# Start development server
python run.py

# Directly with Uvicorn (without reload)
uvicorn app.main:app --host 0.0.0.0 --port 8001
```

---

## 19. Testing

### Laravel Tests

```bash
# Run all tests
php artisan test

# With detailed output
php artisan test --verbose

# Specific test
php artisan test --filter TestName

# With code coverage
php artisan test --coverage
```

Test configuration is located in `phpunit.xml`.

### Python Tests

```bash
# Tests directory
cd backend-python/tests/

# Run tests with pytest (if installed)
pytest

# With detailed output
pytest -v
```

---

## Version History

| Sprint | Completed Features |
|---|---|
| Sprint 1 | Authentication, roles, course CRUD, enrollments |
| Sprint 2 | Assessment system, student responses, auto-grading |
| Sprint 3 | Assessment templates, manual grading |
| Sprint 4 | Individual and group learning profiles with AI |
| Sprint 5 | CLT effects, instructional material generation with Claude API |
| Sprint 6 | Material access logs, timers, reporting system |
| Sprint 7 | Final tests (Recall/Comprehension), CS/CIS/IMMS questionnaires, final MSLQ, data collection and CSV export |

---

*Technical Manual automatically generated — CLT4BP v1.0 — February 2026*
