<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\StudentResponseController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\GradingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CltEffectsController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\Api\DataCollectionController;
use App\Http\Controllers\Api\ReportController;

/*
|--------------------------------------------------------------------------
| API Routes - Health Check
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'app' => config('app.name'),
        'environment' => config('app.env'),
        'timestamp' => now()->toIso8601String(),
    ]);
});

/*
|--------------------------------------------------------------------------
| API Routes - Authentication
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Users
    Route::get('/users', [UserController::class, 'index'])
        ->middleware('role:admin,instructor');

    Route::get('/users/{user}', [UserController::class, 'show']);

    Route::put('/users/{user}', [UserController::class, 'update']);

    Route::post('/users/instructors', [UserController::class, 'createInstructor'])
        ->middleware('role:admin');

    Route::delete('/users/{user}/deactivate', [UserController::class, 'deactivate'])
        ->middleware('role:admin');

    /*
    |--------------------------------------------------------------------------
    | Course Routes
    |--------------------------------------------------------------------------
    */

    // View specific course - Accessible for enrolled users
    Route::get('/courses/{course}', [CourseController::class, 'show']);

    // Course CRUD - Admin and instructor only
    Route::apiResource('courses', CourseController::class)
        ->except(['show'])
        ->middleware('role:admin,instructor');

    // Enrollments
    Route::prefix('courses/{course}')->group(function () {
        Route::post('/enroll', [EnrollmentController::class, 'enroll'])
            ->middleware('role:admin,instructor');

        Route::get('/students', [EnrollmentController::class, 'getStudents'])
            ->middleware('role:admin,instructor');

        Route::delete('/students/{student}', [EnrollmentController::class, 'unenroll'])
            ->middleware('role:admin,instructor');
    });

    // My enrollments (for students)
    Route::get('/my-enrollments', [EnrollmentController::class, 'myEnrollments'])
        ->middleware('role:student');

    /*
    |--------------------------------------------------------------------------
    | Assessment Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('courses/{course}')->group(function () {
        // Assessment CRUD (instructor)
        Route::apiResource('assessments', AssessmentController::class)
            ->except(['index', 'show']);

        // View course assessments (all roles)
        Route::get('/assessments', [AssessmentController::class, 'index']);
        Route::get('/assessments/{assessment}', [AssessmentController::class, 'show']);

        // Activate/deactivate assessment
        Route::post('/assessments/{assessment}/toggle', [AssessmentController::class, 'toggleActive'])
            ->middleware('role:admin,instructor');

        // Student responses
        Route::prefix('assessments/{assessment}')->group(function () {
            // Student: start, save, view response
            Route::post('/start', [StudentResponseController::class, 'start'])
                ->middleware('role:student');

            Route::post('/save', [StudentResponseController::class, 'save'])
                ->middleware('role:student');

            Route::get('/my-response', [StudentResponseController::class, 'show'])
                ->middleware('role:student');

            // Instructor: view all responses
            Route::get('/responses', [StudentResponseController::class, 'assessmentResponses'])
                ->middleware('role:admin,instructor');
        });

        // My course responses (student)
        Route::get('/my-responses', [StudentResponseController::class, 'myResponses'])
            ->middleware('role:student');
    });
    /*
    |--------------------------------------------------------------------------
    | Student Profile Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('courses/{course}/profiles')->middleware('role:admin,instructor')->group(function () {
        // Profile queries
        Route::get('/students', [ProfileController::class, 'getCourseProfiles']);
        Route::get('/students/{studentId}', [ProfileController::class, 'getStudentProfile']);
        Route::get('/group', [ProfileController::class, 'getGroupProfile']);

        // Profile generation
        Route::post('/students/{studentId}/generate', [ProfileController::class, 'generateStudentProfile']);
        Route::post('/students/generate-all', [ProfileController::class, 'generateAllStudentProfiles']);
        Route::post('/group/generate', [ProfileController::class, 'generateGroupProfile']);
        Route::post('/regenerate-all', [ProfileController::class, 'regenerateAllProfiles']);
    });

    /*
    |--------------------------------------------------------------------------
    | Assessment Template Routes
    |--------------------------------------------------------------------------
    */

    // List and view templates (accessible for instructor/admin)
    Route::get('/assessment-templates', [TemplateController::class, 'index']);
    Route::get('/assessment-templates/{template}', [TemplateController::class, 'show']);

    // Create assessment from template
    Route::post('/courses/{course}/assessments/from-template/{template}', [TemplateController::class, 'createFromTemplate'])
        ->middleware('role:admin,instructor');

    // Ver plantillas disponibles para un curso
    Route::get('/courses/{course}/available-templates', [TemplateController::class, 'availableForCourse'])
        ->middleware('role:admin,instructor');

    /*
    |--------------------------------------------------------------------------
    | Manual Grading Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('courses/{course}')->middleware('role:admin,instructor')->group(function () {
        // Summary of pending grades for the course
        Route::get('/pending-grading', [GradingController::class, 'coursePendingGrading']);

        // Recalculate grading statuses
        Route::post('/recalculate-grading', [GradingController::class, 'recalculateGradingStatus']);

        // Grading by assessment
        Route::prefix('assessments/{assessment}')->group(function () {
            // View pending grading responses
            Route::get('/pending-grading', [GradingController::class, 'pendingGrading']);

            // View a specific response for grading
            Route::get('/responses/{response}/grading', [GradingController::class, 'showResponse']);

            // Submit manual grade
            Route::post('/responses/{response}/grade', [GradingController::class, 'submitGrade']);

            // Revert manual grade
            Route::post('/responses/{response}/revert-grade', [GradingController::class, 'revertGrade']);
        });
    });
    Route::prefix('courses/{courseId}/clt-effects')->group(function () {
        Route::get('/available', [CltEffectsController::class, 'getAvailableEffects']);
        Route::get('/selection', [CltEffectsController::class, 'getSelection']);
        Route::post('/selection', [CltEffectsController::class, 'saveSelection']);
        Route::get('/recommendations', [CltEffectsController::class, 'getRecommendations']);
    });
    Route::prefix('courses/{courseId}/materials')->group(function () {
        // Student routes (before {materialId} to avoid conflict)
        Route::get('/student/list', [MaterialController::class, 'studentMaterials'])
            ->middleware('role:student');

        // Instructor routes
        Route::post('/generate', [MaterialController::class, 'generate']);
        Route::get('/', [MaterialController::class, 'listMaterials']);
        Route::get('/{materialId}', [MaterialController::class, 'getMaterial']);
        Route::post('/{materialId}/toggle-active', [MaterialController::class, 'toggleActive']);
        Route::put('/{materialId}/timer', [MaterialController::class, 'updateTimer']);
        Route::get('/{materialId}/access-logs', [MaterialController::class, 'getAccessLogs']);

        // Student access logging
        Route::post('/{materialId}/access/start', [MaterialController::class, 'logAccessStart'])
            ->middleware('role:student');
        Route::post('/{materialId}/access/complete', [MaterialController::class, 'logAccessComplete'])
            ->middleware('role:student');
    });
    Route::prefix('courses/{courseId}/reports')->group(function () {
        Route::get('/instructor', [ReportController::class, 'instructorReport'])
            ->middleware('role:instructor,admin');
        Route::get('/my-report', [ReportController::class, 'studentReport'])
            ->middleware('role:student');
    });

    Route::prefix('courses/{courseId}/data-collection')->middleware('role:instructor,admin')->group(function () {
        Route::get('/summary', [DataCollectionController::class, 'summary']);
        Route::get('/pre-post-comparison', [DataCollectionController::class, 'prePostComparison']);
        Route::get('/export', [DataCollectionController::class, 'exportCsv']);
        Route::get('/student/{studentId}', [DataCollectionController::class, 'studentDetail']);
    });
});
