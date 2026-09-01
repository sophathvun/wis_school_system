<?php

namespace App\Http\Controllers;

use App\Models\DashboardAssignment;
use App\Models\DashboardTemplate;
use App\Models\DashboardWidget;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardTemplateController
{
    private const SORTABLE_COLUMNS = ['name', 'code', 'layout', 'display_order', 'status'];

    private function authorizeSuperUser(Request $request): void
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeSuperUser($request);

        $search = trim((string) $request->query('search'));
        $perPage = min(max($request->integer('per_page', 10), 10), 100);
        $sortBy = in_array($request->query('sortBy', 'display_order'), self::SORTABLE_COLUMNS, true)
            ? $request->query('sortBy', 'display_order')
            : 'display_order';
        $sortDir = strtolower($request->query('sortDir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $templates = DashboardTemplate::with(['widgets', 'assignments'])
            ->withCount(['widgets', 'assignments'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('layout', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDir)
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $editTemplate = $request->integer('edit')
            ? DashboardTemplate::with(['widgets', 'assignments'])->find($request->integer('edit'))
            : null;

        return view('dashboard-templates', [
            'templates' => $templates,
            'editTemplate' => $editTemplate,
            'widgets' => DashboardWidget::where('status', 1)->orderBy('name')->get(),
            'departments' => Department::where('status', 1)->orderBy('name')->get(),
            'roles' => Role::where('status', 1)->orderBy('name')->get(),
            'users' => User::where('status', 1)->orderBy('name')->get(['id', 'name', 'username']),
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    public function save(Request $request)
    {
        $this->authorizeSuperUser($request);

        $id = $request->integer('template_id');

        if (!$id && !$request->has('status')) {
            $request->merge(['status' => '1']);
        }

        $data = $request->validate([
            'template_id' => ['nullable', 'exists:dashboard_templates,id'],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'alpha_dash', 'max:80', 'unique:dashboard_templates,code,' . $id],
            'description' => ['nullable', 'string', 'max:500'],
            'layout' => ['required', 'in:premium_grid,two_columns,three_columns,executive'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['required', 'in:0,1'],
            'sections_payload' => ['nullable', 'string'],
            'widget_ids' => ['nullable', 'array'],
            'widget_ids.*' => ['integer', 'exists:dashboard_widgets,id'],
            'widget_widths' => ['nullable', 'array'],
            'widget_widths.*' => ['nullable', 'in:small,medium,large,full,col-1,col-2,col-3,col-4,col-5,col-6'],
            'widget_sections' => ['nullable', 'array'],
            'widget_sections.*' => ['nullable', 'string', 'max:80'],
            'widget_chart_types' => ['nullable', 'array'],
            'widget_chart_types.*' => ['nullable', 'in:standard,donut,vertical_bar,grouped_bar,horizontal_bar,compact_list'],
            'assignments' => ['nullable', 'array'],
            'assignments.departments' => ['nullable', 'array'],
            'assignments.departments.*' => ['integer', 'exists:access_departments,id'],
            'assignments.roles' => ['nullable', 'array'],
            'assignments.roles.*' => ['integer', 'exists:access_roles,id'],
            'assignments.users' => ['nullable', 'array'],
            'assignments.users.*' => ['integer', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($request, $data, $id) {
            if ($request->boolean('is_default')) {
                DashboardTemplate::query()->update(['is_default' => false]);
            }

            $payload = [
                'name' => $data['name'],
                'code' => $data['code'] ?: $this->uniqueCode($data['name'], $id),
                'description' => $data['description'] ?? null,
                'layout' => $data['layout'],
                'display_order' => $data['display_order'] ?? 0,
                'is_default' => $request->boolean('is_default'),
                'status' => $data['status'],
                'settings' => [
                    'sections' => $this->normalizedSections($request->input('sections_payload')),
                ],
            ];

            $template = $id ? DashboardTemplate::findOrFail($id) : new DashboardTemplate(['created_by' => $request->user()->id]);
            $template->fill($payload);
            $template->save();

            $syncWidgets = [];
            foreach (array_values($data['widget_ids'] ?? []) as $index => $widgetId) {
                $syncWidgets[$widgetId] = [
                    'display_order' => $index + 1,
                    'width' => $data['widget_widths'][$widgetId] ?? 'medium',
                    'status' => true,
                    'settings' => json_encode([
                        'section_id' => $data['widget_sections'][$widgetId] ?? 'section-1',
                        'chart_type' => $data['widget_chart_types'][$widgetId] ?? 'standard',
                    ]),
                ];
            }
            $template->widgets()->sync($syncWidgets);

            $template->assignments()->delete();
            $this->createAssignments($template, 'department', $data['assignments']['departments'] ?? [], 300);
            $this->createAssignments($template, 'role', $data['assignments']['roles'] ?? [], 200);
            $this->createAssignments($template, 'user', $data['assignments']['users'] ?? [], 100);
        });

        return redirect()
            ->route('dashboard-templates.index')
            ->with('success', $id ? 'Dashboard template updated successfully.' : 'Dashboard template created successfully.');
    }

    public function delete(Request $request, DashboardTemplate $dashboardTemplate)
    {
        $this->authorizeSuperUser($request);

        if ($dashboardTemplate->is_default || $dashboardTemplate->assignments()->exists()) {
            return back()->withErrors([
                'dashboard_template' => 'This dashboard template cannot be deleted because it is default or already assigned.',
            ]);
        }

        $dashboardTemplate->widgets()->detach();
        $dashboardTemplate->delete();

        return back()->with('success', 'Dashboard template deleted successfully.');
    }

    private function createAssignments(DashboardTemplate $template, string $type, array $ids, int $priority): void
    {
        $ids = array_unique(array_filter($ids));

        if ($ids) {
            DashboardAssignment::where('assignment_type', $type)->whereIn('assignment_id', $ids)->delete();
        }

        foreach ($ids as $id) {
            DashboardAssignment::create([
                'dashboard_template_id' => $template->id,
                'assignment_type' => $type,
                'assignment_id' => $id,
                'priority' => $priority,
                'status' => true,
            ]);
        }
    }

    private function uniqueCode(string $name, ?int $ignoreId = null): string
    {
        $baseCode = Str::slug($name) ?: 'dashboard-template';
        $code = $baseCode;
        $counter = 2;

        while (DashboardTemplate::where('code', $code)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $code = "{$baseCode}-{$counter}";
            $counter++;
        }

        return $code;
    }

    private function normalizedSections(?string $payload): array
    {
        $sections = json_decode($payload ?: '[]', true);

        if (!is_array($sections) || !$sections) {
            $sections = [
                ['id' => 'section-1', 'title' => 'Section 1', 'columns' => '4'],
            ];
        }

        return collect($sections)
            ->filter(fn ($section) => is_array($section))
            ->values()
            ->map(function ($section, $index) {
                return [
                    'id' => preg_replace('/[^A-Za-z0-9_-]/', '', $section['id'] ?? 'section-' . ($index + 1)) ?: 'section-' . ($index + 1),
                    'title' => trim((string) ($section['title'] ?? 'Section ' . ($index + 1))) ?: 'Section ' . ($index + 1),
                    'columns' => in_array(($section['columns'] ?? '4'), ['1', '6', '5', '4', '3', '2', '8-4', '4-8', '7-5', '5-7'], true)
                        ? $section['columns']
                        : '4',
                ];
            })
            ->all();
    }
}
