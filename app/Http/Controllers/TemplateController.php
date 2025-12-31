<?php

namespace App\Http\Controllers;

use App\Models\TemplateModel;
use App\Http\Responses\ResponseBase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = TemplateModel::where('is_active', true);

            if ($request->filled('group') && $request->group === 'hierarchical') {
                $templates = $query->whereDoesntHave('parents')
                    ->with(['children' => function($query) {
                        $query->where('is_active', true)
                            ->orderBy('name');
                    }])
                    ->orderBy('name')
                    ->get();

                return ResponseBase::success(
                    $templates,
                    'Templates agrupados jerárquicamente'
                );
            }

            if ($request->filled('parent_id')) {
                $templates = TemplateModel::where('is_active', true)
                    ->whereHas('parents', function($query) use ($request) {
                        $query->where('template_models.id', $request->parent_id);
                    })
                    ->orderBy('name')
                    ->paginate($request->input('per_page', 15));

                return ResponseBase::success(
                    $templates,
                    'Templates obtenidos correctamente'
                );
            }

            if ($request->filled('only_roots') && $request->only_roots === 'true') {
                $query->whereHas('parents');
            }

            $templates = $query->orderBy('name')->paginate($request->input('per_page', 15));

            return ResponseBase::success(
                $templates,
                'Templates obtenidos correctamente'
            );

        } catch (\Exception $e) {
            Log::error('Error fetching templates', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al obtener templates',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255', 'unique:template_models,name'],
                'parent_ids' => ['nullable', 'array'],
                'parent_ids.*' => ['exists:template_models,id'],
                'is_active' => ['sometimes', 'boolean']
            ]);

            $template = TemplateModel::create([
                'name' => $validated['name'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (isset($validated['parent_ids']) && !empty($validated['parent_ids'])) {
                if (in_array($template->id, $validated['parent_ids'])) {
                    return ResponseBase::error(
                        'Un template no puede ser su propio padre',
                        [],
                        400
                    );
                }

                $template->parents()->attach($validated['parent_ids']);
            }

            $template->load('parents');

            return ResponseBase::success(
                $template,
                'Template creado exitosamente',
                201
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            Log::error('Error creating template', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al crear template',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $template = TemplateModel::findOrFail($id);

            $validated = $request->validate([
                'name' => ['sometimes', 'string', 'max:255'],
                'parent_ids' => ['nullable', 'array'],
                'parent_ids.*' => ['exists:template_models,id'],
                'is_active' => ['sometimes', 'boolean']
            ]);

            if (isset($validated['name'])) {
                $template->name = $validated['name'];
            }
            if (isset($validated['is_active'])) {
                $template->is_active = $validated['is_active'];
            }
            $template->save();

            if (isset($validated['parent_ids'])) {
                if (in_array($id, $validated['parent_ids'])) {
                    return ResponseBase::error(
                        'Un template no puede ser su propio padre',
                        [],
                        400
                    );
                }

                // Obtener los parent_ids actuales
                $currentParentIds = $template->parents()->pluck('template_models.id')->toArray();

                // Filtrar solo los nuevos parent_ids que no existen
                $newParentIds = array_diff($validated['parent_ids'], $currentParentIds);

                // Verificar referencias circulares solo para los nuevos
                foreach ($newParentIds as $parentId) {
                    if ($this->wouldCreateCircularReference($id, $parentId)) {
                        return ResponseBase::error(
                            'Esta operación crearía una referencia circular',
                            [],
                            400
                        );
                    }
                }

                // Agregar solo las nuevas relaciones sin eliminar las existentes
                if (!empty($newParentIds)) {
                    $template->parents()->attach($newParentIds);
                }
            }

            $template->load('parents');

            return ResponseBase::success(
                $template,
                'Template actualizado exitosamente'
            );

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseBase::error('Template no encontrado', [], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            Log::error('Error updating template', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al actualizar template',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Remove the specified resource from storage (soft delete).
     */
    public function destroy(string $id)
    {
        try {
            $template = TemplateModel::findOrFail($id);
            $template->update(['is_active' => false]);
            $template->children()->update(['is_active' => false]);

            return ResponseBase::success(
                null,
                'Template desactivado exitosamente'
            );

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseBase::error('Template no encontrado', [], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting template', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al desactivar template',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Verifica si asignar un parent_id crearía una referencia circular
     */
    private function wouldCreateCircularReference(string $templateId, string $newParentId): bool
    {
        $visited = [];
        $toCheck = [$newParentId];

        while (!empty($toCheck)) {
            $currentId = array_shift($toCheck);

            if ($currentId == $templateId) {
                return true;
            }   

            if (in_array($currentId, $visited)) {
                continue;
            }

            $visited[] = $currentId;

            $template = TemplateModel::find($currentId);
            if ($template) {
                $parentIds = $template->parents()->pluck('template_models.id')->toArray();
                $toCheck = array_merge($toCheck, $parentIds);
            }
        }

        return false;
    }
}
