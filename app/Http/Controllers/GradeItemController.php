<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GradeCategory;
use App\Models\GradeItem;

class GradeItemController extends Controller
{
    public function store(Request $request, string $categoryId)
    {
        $request->validate([
            'title'       => 'required|string|max:150',
            'max_score'   => 'required|numeric|min:0.01',
            'item_date'   => 'nullable|date',
            'description' => 'nullable|string',
            'ordering'    => 'nullable|integer|min:0',
        ]);

        $category = GradeCategory::findOrFail($categoryId);

        GradeItem::create([
            'category_id' => $categoryId,
            'title'       => $request->title,
            'max_score'   => $request->max_score,
            'item_date'   => $request->item_date,
            'description' => $request->description,
            'ordering'    => $request->ordering ?? 0,
        ]);

        return redirect()
            ->route('grades.admin', $category->course_id)
            ->with('success', 'تم إضافة بند التقييم');
    }

    public function update(Request $request, string $itemId)
    {
        $request->validate([
            'title'       => 'required|string|max:150',
            'max_score'   => 'required|numeric|min:0.01',
            'item_date'   => 'nullable|date',
            'description' => 'nullable|string',
            'ordering'    => 'nullable|integer|min:0',
        ]);

        $item = GradeItem::findOrFail($itemId);
        $item->update($request->only('title', 'max_score', 'item_date', 'description', 'ordering'));

        return redirect()
            ->route('grades.admin', $item->category->course_id)
            ->with('success', 'تم تحديث البند');
    }

    public function destroy(string $itemId)
    {
        $item     = GradeItem::with('category')->findOrFail($itemId);
        $courseId = $item->category->course_id;
        $item->delete();

        return redirect()
            ->route('grades.admin', $courseId)
            ->with('success', 'تم حذف البند وجميع درجاته');
    }
}
