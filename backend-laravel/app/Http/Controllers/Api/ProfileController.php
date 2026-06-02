<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\StudentProfile;
use App\Models\GroupProfile;
use App\Services\StudentProfileGeneratorService;
use App\Services\GroupProfileGeneratorService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    private StudentProfileGeneratorService $studentProfileGenerator;
    private GroupProfileGeneratorService $groupProfileGenerator;

    public function __construct(
        StudentProfileGeneratorService $studentProfileGenerator,
        GroupProfileGeneratorService $groupProfileGenerator
    ) {
        $this->studentProfileGenerator = $studentProfileGenerator;
        $this->groupProfileGenerator = $groupProfileGenerator;
    }

    /**
     * Generate individual student profile
     */
    public function generateStudentProfile(Request $request, Course $course, int $studentId)
    {
        if (!$this->canManageCourse($request->user(), $course)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $profile = $this->studentProfileGenerator->generateProfile($studentId, $course->id);

            return response()->json([
                'success' => true,
                'message' => 'Profile generated successfully',
                'data' => $profile
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate profiles for all course students
     */
    public function generateAllStudentProfiles(Request $request, Course $course)
    {
        if (!$this->canManageCourse($request->user(), $course)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $students = $course->students;
        $generated = [];
        $errors = [];

        foreach ($students as $student) {
            try {
                $profile = $this->studentProfileGenerator->generateProfile(
                    $student->id,
                    $course->id
                );
                $generated[] = $profile;
            } catch (\Exception $e) {
                $errors[] = [
                    'student_id' => $student->id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => true,
            'generated_count' => count($generated),
            'errors_count' => count($errors),
            'profiles' => $generated,
            'errors' => $errors
        ]);
    }

    /**
     * Generate group profile
     */
    public function generateGroupProfile(Request $request, Course $course)
    {
        if (!$this->canManageCourse($request->user(), $course)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $groupProfile = $this->groupProfileGenerator->generateGroupProfile($course->id);

            return response()->json([
                'success' => true,
                'message' => 'Group profile generated successfully',
                'data' => $groupProfile
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating group profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get individual profile
     */
    public function getStudentProfile(Request $request, Course $course, int $studentId)
    {
        // Students can view their own profile
        if ($request->user()->isStudent()) {
            if ($request->user()->id !== $studentId) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } elseif (!$this->canManageCourse($request->user(), $course)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $profile = StudentProfile::where('course_id', $course->id)
            ->where('student_id', $studentId)
            ->with('student:id,name,email')
            ->first();

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $profile
        ]);
    }

    /**
     * Get all course profiles
     */
    public function getCourseProfiles(Request $request, Course $course)
    {
        if (!$this->canManageCourse($request->user(), $course)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $profiles = StudentProfile::where('course_id', $course->id)
            ->with('student:id,name,email')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $profiles
        ]);
    }

    /**
     * Get group profile
     */
    public function getGroupProfile(Request $request, Course $course)
    {
        if (!$this->canManageCourse($request->user(), $course)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $groupProfile = GroupProfile::where('course_id', $course->id)->first();

        if (!$groupProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Group profile not found. Generate individual profiles first.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $groupProfile
        ]);
    }

    /**
     * Regenerate all profiles (individual and group)
     */
    public function regenerateAllProfiles(Request $request, Course $course)
    {
        if (!$this->canManageCourse($request->user(), $course)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $students = $course->students;
            $generated = 0;
            $errors = [];

            // Generate individual profiles
            foreach ($students as $student) {
                try {
                    $this->studentProfileGenerator->generateProfile($student->id, $course->id);
                    $generated++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'student_id' => $student->id,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Generate group profile if individual profiles were generated
            $groupProfile = null;
            if ($generated > 0) {
                try {
                    $groupProfile = $this->groupProfileGenerator->generateGroupProfile($course->id);
                } catch (\Exception $e) {
                    $errors[] = [
                        'type' => 'group_profile',
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Profiles regenerated successfully',
                'student_profiles_count' => $generated,
                'group_profile_generated' => $groupProfile !== null,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error regenerating profiles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user can manage the course
     */
    private function canManageCourse($user, Course $course): bool
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
