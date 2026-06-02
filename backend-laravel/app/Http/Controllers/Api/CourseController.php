<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    /**
     * List courses
     */
    public function index(Request $request)
    {
        $query = Course::with(['instructor:id,name,email', 'enrollments']);

        // Filter by instructor (instructors see only their courses)
        if ($request->user()->isInstructor()) {
            $query->byInstructor($request->user()->id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        $courses = $query->latest()->get();

        // Add student count to each course
        $courses->each(function ($course) {
            $course->students_count = $course->enrolledStudentsCount();
        });

        return response()->json([
            'courses' => $courses,
        ]);
    }

    /**
     * Create course
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'learning_objectives' => 'nullable|array',
            'learning_objectives.*' => 'string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'sometimes|in:draft,active,inactive,completed',
        ]);

        // If instructor, assign their ID
        if ($request->user()->isInstructor()) {
            $validated['instructor_id'] = $request->user()->id;
        }
        // If admin, can specify the instructor
        elseif ($request->user()->isAdmin() && $request->has('instructor_id')) {
            $validated['instructor_id'] = $request->instructor_id;
        }
        else {
            return response()->json([
                'message' => 'Unauthorized to create courses',
            ], 403);
        }

        $course = Course::create($validated);

        return response()->json([
            'message' => 'Course created successfully',
            'course' => $course->load('instructor:id,name,email'),
        ], 201);
    }

    /**
     * View specific course
     */
    public function show(Request $request, Course $course)
    {
        if (!$this->canAccessCourse($request->user(), $course)) {
            return response()->json([
                'message' => 'Unauthorized to view this course',
            ], 403);
        }

        $course->load(['instructor:id,name,email', 'students:id,name,email']);
        $course->students_count = $course->enrolledStudentsCount();

        return response()->json([
            'course' => $course,
        ]);
    }

    /**
     * Update course
     */
    public function update(Request $request, Course $course)
    {
        if (!$this->canModifyCourse($request->user(), $course)) {
            return response()->json([
                'message' => 'Unauthorized to modify this course',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'learning_objectives' => 'nullable|array',
            'learning_objectives.*' => 'string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'sometimes|in:draft,active,inactive,completed',
        ]);

        $course->update($validated);

        return response()->json([
            'message' => 'Course updated successfully',
            'course' => $course->load('instructor:id,name,email'),
        ]);
    }

    /**
     * Delete course
     */
    public function destroy(Request $request, Course $course)
    {
        if (!$this->canModifyCourse($request->user(), $course)) {
            return response()->json([
                'message' => 'Unauthorized to delete this course',
            ], 403);
        }

        $course->delete();

        return response()->json([
            'message' => 'Course deleted successfully',
        ]);
    }

    /**
     * Check if user can access the course
     */
    private function canAccessCourse($user, $course): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isInstructor() && $course->instructor_id === $user->id) {
            return true;
        }

        if ($user->isStudent()) {
            // Check if student is enrolled in the course
            return $course->enrollments()
                ->where('student_id', $user->id)
                ->where('status', 'enrolled')
                ->exists();
        }

        return false;
    }

    /**
     * Check if user can modify the course
     */
    private function canModifyCourse($user, $course): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isInstructor() && $course->instructor_id === $user->id) {
            return true;
        }

        return false;
    }
}
