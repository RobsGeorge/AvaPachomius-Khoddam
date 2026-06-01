<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use App\Services\AutoTranslateService;
use App\Services\TranslationRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

class TranslationController extends Controller
{
    public function __construct(
        private TranslationRepository $repository,
        private AutoTranslateService $autoTranslate,
    ) {}

    public function index(Request $request)
    {
        $locale = $request->get('locale', app()->getLocale());
        $group  = $request->get('group', 'nav');
        $search = $request->get('search');

        $fileLines = Lang::get($group, [], $locale);
        if (! is_array($fileLines)) {
            $fileLines = [];
        }

        $dbLines = Translation::query()
            ->where('locale', $locale)
            ->where('group', $group)
            ->pluck('value', 'key');

        $keys = collect(array_keys($fileLines))
            ->merge($dbLines->keys())
            ->unique()
            ->sort()
            ->values();

        if ($search) {
            $keys = $keys->filter(function ($key) use ($fileLines, $dbLines, $search) {
                $value = $dbLines[$key] ?? ($fileLines[$key] ?? '');

                return str_contains(strtolower($key), strtolower($search))
                    || str_contains(strtolower((string) $value), strtolower($search));
            })->values();
        }

        $groups = collect(glob(lang_path('ar/*.php')))
            ->map(fn ($path) => basename($path, '.php'))
            ->sort()
            ->values();

        return view('admin.translations.index', compact(
            'locale', 'group', 'search', 'keys', 'fileLines', 'dbLines', 'groups'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'group'  => 'required|string|max:64',
            'key'    => 'required|string|max:191',
            'locale' => 'required|in:ar,en',
            'value'  => 'required|string',
        ]);

        Translation::updateOrCreate(
            [
                'group'  => $data['group'],
                'key'    => $data['key'],
                'locale' => $data['locale'],
            ],
            ['value' => $data['value']]
        );

        $this->repository->flushCache($data['locale']);

        return back()->with('success', __('admin.translation_saved'));
    }

    public function autoTranslate(Request $request)
    {
        $data = $request->validate([
            'group'      => 'required|string|max:64',
            'key'        => 'required|string|max:191',
            'from_locale'=> 'required|in:ar,en',
            'to_locale'  => 'required|in:ar,en',
        ]);

        $source = Lang::get("{$data['group']}.{$data['key']}", [], $data['from_locale']);
        if (! is_string($source) || $source === '') {
            $source = Translation::query()
                ->where('group', $data['group'])
                ->where('key', $data['key'])
                ->where('locale', $data['from_locale'])
                ->value('value');
        }

        if (! is_string($source) || trim($source) === '') {
            return back()->withErrors(['auto' => __('admin.no_source_text')]);
        }

        $translated = $this->autoTranslate->translate($source, $data['from_locale'], $data['to_locale']);

        if (! $translated) {
            return back()->withErrors(['auto' => __('admin.auto_translate_failed')]);
        }

        Translation::updateOrCreate(
            [
                'group'  => $data['group'],
                'key'    => $data['key'],
                'locale' => $data['to_locale'],
            ],
            ['value' => $translated]
        );

        $this->repository->flushCache($data['to_locale']);

        return back()->with('success', __('admin.auto_translate_done'));
    }
}
