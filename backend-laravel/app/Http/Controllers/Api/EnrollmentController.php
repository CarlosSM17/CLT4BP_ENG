<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    /**
     * Enroll students in a course
     */
    public function enroll(Request $request, Course $course)
    {
        if (!$request->user()->isInstructor() && !$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized to enroll students',
            ], 403);
        }

        if ($request->user()->isInstructor() && $course->instructor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You cannot enroll students in other instructors\' courses',
            ], 403);
        }

        $validated = $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:users,id',
        ]);

        $enrolled = [];
        $alreadyEnrolled = [];

        foreach ($validated['student_ids'] as $studentId) {
            $student = User::find($studentId);
            if (!$student->isStudent()) {
                continue;
            }

            $exists = CourseEnrollment::where('course_id', $course->id)
                ->where('student_id', $studentId)
                ->exists();

            if ($exists) {
                $alreadyEnrolled[] = $student->name;
                continue;
            }

            CourseEnrollment::create([
                'course_id' => $course->id,
                'student_id' => $studentId,
                'status' => 'enrolled',
            ]);

            $enrolled[] = $student->name;
        }

        return response()->json([
            'message' => 'Enrollment processed',
            'enrolled' => $enrolled,
            'already_enrolled' => $alreadyEnrolled,
        ]);
    }

    /**
     * Get course students
     */
    public function getStudents(Course $course)
    {
        $students = $course->students()
            ->withPivot('status', 'enrollment_date', 'completion_date')
            ->get();

        return response()->json([
            'students' => $students,
        ]);
    }

    /**
     * Remove student from a course
     */
    public function unenroll(Request $request, Course $course, User $student)
    {
        if (!$request->user()->isInstructor() && !$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($request->user()->isInstructor() && $course->instructor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $enrollment = CourseEnrollment::where('course_id', $course->id)
            ->where('student_id', $student->id)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'message' => 'Student is not enrolled in this course',
            ], 404);
        }

        $enrollment->update(['status' => 'dropped']);

        return response()->json([
            'message' => 'Student removed from course',
        ]);
    }

    /**
     * Get student's enrollments
     */
    public function myEnrollments(Request $request)
    {
        $enrollments = CourseEnrollment::with(['course.instructor'])
            ->where('student_id', $request->user()->id)
            ->where('status', 'enrolled')
            ->latest()
            ->get();

        return response()->json([
            'enrollments' => $enrollments,
        ]);
    }
}
