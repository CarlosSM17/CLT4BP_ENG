<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    /**
     * The course instructor or an admin can manage the course.
     */
    public function manage(User $user, Course $course): bool
    {
        // Admin can manage any course
        if ($user->role === 'admin') {
            return true;
        }

        // Instructor can manage their own courses
        if ($user->role === 'instructor' && $course->instructor_id === $user->id) {
            return true;
        }

        return false;
    }

    public function view(User $user, Course $course): bool
    {
        // Admin and instructor can view any course
        if (in_array($user->role, ['admin', 'instructor'])) {
            return true;
        }

        // Students can view courses they are enrolled in
        if ($user->role === 'student') {
            return $course->students()->where('users.id', $user->id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'instructor']);
    }

    public function update(User $user, Course $course): bool
    {
        return $this->manage($user, $course);
    }

    public function delete(User $user, Course $course): bool
    {
        // Only admin can delete courses
        return $user->role === 'admin';
    }
}
