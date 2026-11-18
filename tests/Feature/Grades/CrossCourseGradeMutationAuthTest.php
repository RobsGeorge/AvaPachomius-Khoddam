<?php

namespace Tests\Feature\Grades;

use App\Models\GradeCategory;
use App\Models\GradeItem;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\EventModuleTestCase;

/**
 * Course-scoped gate for gradebook mutations reached by category/item id.
 * Staff of Course A must not read or mutate Course B grades via coarse
 * permission:staff (staff-anywhere) on /grade-items/{item} and /grade-categories/{category}.
 */
class CrossCourseGradeMutationAuthTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('permissions:sync');
    }

    /**
     * @return array{
     *   instructor: User,
     *   courseA: \App\Models\Course,
     *   courseB: \App\Models\Course,
     *   categoryA: GradeCategory,
     *   categoryB: GradeCategory,
     *   itemA: GradeItem,
     *   itemB: GradeItem,
     *   studentA: User,
     *   studentB: User
     * }
     */
    private function seedInstructorOnlyOnCourseA(): array
    {
        $courseA = $this->createCourse(['title' => 'Grade Auth Course A']);
        $courseB = $this->createCourse(['title' => 'Grade Auth Course B']);

        $instructor = $this->createUser(['email' => 'grade-mutation-cross@example.com']);
        $this->assignCourseRole(
            $instructor,
            $courseA,
            $this->courseRoleWithPermissions($courseA, 'instructor', [
                'grade.manage',
                'grade.view',
                'curriculum.manage',
            ])
        );

        $studentRole = $this->createRole('Student');
        $studentA = $this->createUser([
            'first_name' => 'Student',
            'second_name' => 'A',
            'email' => 'grade-mutation-student-a@example.com',
        ]);
        $studentB = $this->createUser([
            'first_name' => 'Student',
            'second_name' => 'B',
            'email' => 'grade-mutation-student-b@example.com',
        ]);
        $this->assignCourseRole($studentA, $courseA, $studentRole);
        $this->assignCourseRole($studentB, $courseB, $studentRole);

        $categoryA = GradeCategory::create([
            'course_id' => $courseA->course_id,
            'type' => 'exam',
            'name' => 'Exams A',
            'weight_percentage' => 100,
            'ordering' => 0,
        ]);
        $categoryB = GradeCategory::create([
            'course_id' => $courseB->course_id,
            'type' => 'exam',
            'name' => 'Exams B',
            'weight_percentage' => 100,
            'ordering' => 0,
        ]);
        $itemA = GradeItem::create([
            'category_id' => $categoryA->category_id,
            'title' => 'Quiz A',
            'max_score' => 100,
            'ordering' => 0,
        ]);
        $itemB = GradeItem::create([
            'category_id' => $categoryB->category_id,
            'title' => 'Quiz B',
            'max_score' => 100,
            'ordering' => 0,
        ]);

        return compact(
            'instructor',
            'courseA',
            'courseB',
            'categoryA',
            'categoryB',
            'itemA',
            'itemB',
            'studentA',
            'studentB',
        );
    }

    public function test_staff_of_course_a_cannot_bulk_save_scores_on_course_b_item(): void
    {
        [
            'instructor' => $instructor,
            'itemB' => $itemB,
            'studentB' => $studentB,
        ] = $this->seedInstructorOnlyOnCourseA();

        $this->actingAs($instructor)
            ->post(route('grade-items.scores.save', $itemB->item_id), [
                'scores' => [$studentB->user_id => 77],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('student_grades', [
            'item_id' => $itemB->item_id,
            'user_id' => $studentB->user_id,
        ]);
    }

    public function test_staff_of_course_a_cannot_view_course_b_item_scores(): void
    {
        [
            'instructor' => $instructor,
            'itemB' => $itemB,
        ] = $this->seedInstructorOnlyOnCourseA();

        $this->actingAs($instructor)
            ->get(route('grade-items.scores', $itemB->item_id))
            ->assertForbidden();
    }

    public function test_staff_of_course_a_cannot_delete_course_b_category(): void
    {
        [
            'instructor' => $instructor,
            'categoryB' => $categoryB,
            'itemB' => $itemB,
        ] = $this->seedInstructorOnlyOnCourseA();

        $this->actingAs($instructor)
            ->delete(route('grade-categories.destroy', $categoryB->category_id))
            ->assertForbidden();

        $this->assertDatabaseHas('grade_categories', [
            'category_id' => $categoryB->category_id,
        ]);
        $this->assertDatabaseHas('grade_items', [
            'item_id' => $itemB->item_id,
        ]);
    }

    public function test_staff_of_course_a_cannot_update_course_b_item(): void
    {
        [
            'instructor' => $instructor,
            'itemB' => $itemB,
        ] = $this->seedInstructorOnlyOnCourseA();

        $this->actingAs($instructor)
            ->put(route('grade-items.update', $itemB->item_id), [
                'title' => 'Hijacked',
                'max_score' => 50,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('grade_items', [
            'item_id' => $itemB->item_id,
            'title' => 'Quiz B',
            'max_score' => 100,
        ]);
    }

    public function test_staff_of_course_a_can_bulk_save_on_own_course_item(): void
    {
        [
            'instructor' => $instructor,
            'itemA' => $itemA,
            'studentA' => $studentA,
        ] = $this->seedInstructorOnlyOnCourseA();

        $this->actingAs($instructor)
            ->post(route('grade-items.scores.save', $itemA->item_id), [
                'scores' => [$studentA->user_id => 91],
            ])
            ->assertRedirect(route('grade-items.scores', $itemA->item_id));

        $this->assertDatabaseHas('student_grades', [
            'item_id' => $itemA->item_id,
            'user_id' => $studentA->user_id,
            'score' => 91,
            'graded_by_id' => $instructor->user_id,
        ]);
    }

    public function test_staff_of_course_a_can_update_own_course_category(): void
    {
        [
            'instructor' => $instructor,
            'categoryA' => $categoryA,
        ] = $this->seedInstructorOnlyOnCourseA();

        $this->actingAs($instructor)
            ->put(route('grade-categories.update', $categoryA->category_id), [
                'name' => 'Exams A Renamed',
                'weight_percentage' => 80,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('grade_categories', [
            'category_id' => $categoryA->category_id,
            'name' => 'Exams A Renamed',
            'weight_percentage' => 80,
        ]);
    }
}
