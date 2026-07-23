<?php

namespace App\Http\Controllers;

use App\Models\WhatsappTemplate;
use Exception;
use Illuminate\Http\Request;

class WhatsappTemplateController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = WhatsappTemplate::orderBy('display_name');

            if ($request->filled('search')) {
                $s = $request->search;
                $query->where(function ($q) use ($s) {
                    $q->where('name', 'like', "%$s%")
                      ->orWhere('display_name', 'like', "%$s%")
                      ->orWhere('used_in', 'like', "%$s%");
                });
            }

            if ($request->filled('is_active')) {
                $query->where('is_active', (bool) $request->is_active);
            }

            $templates = $query->get();

            return response()->json([
                'data'        => $templates,
                'total_count' => $templates->count(),
                'code'        => 200,
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 500]);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name'         => 'required|string|unique:tbl_whatsapp_templates,name',
                'display_name' => 'required|string',
            ]);

            $template = WhatsappTemplate::create([
                'name'            => trim($request->name),
                'display_name'    => trim($request->display_name),
                'description'     => $request->description,
                'variables_count' => (int) ($request->variables_count ?? 0),
                'variable_labels' => $request->variable_labels ?? [],
                'used_in'         => $request->used_in,
                'is_active'       => $request->boolean('is_active', true),
            ]);

            return response()->json([
                'message' => 'Template created successfully',
                'data'    => $template,
                'code'    => 200,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => implode(' ', $e->validator->errors()->all()), 'code' => 500]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 500]);
        }
    }

    public function show(Request $request)
    {
        try {
            $template = WhatsappTemplate::find($request->id);
            if (!$template) {
                return response()->json(['message' => 'Template not found', 'code' => 500]);
            }

            return response()->json(['data' => $template, 'code' => 200]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 500]);
        }
    }

    public function update(Request $request)
    {
        try {
            $template = WhatsappTemplate::find($request->id);
            if (!$template) {
                return response()->json(['message' => 'Template not found', 'code' => 500]);
            }

            $request->validate([
                'name' => 'sometimes|string|unique:tbl_whatsapp_templates,name,' . $template->id,
            ]);

            $template->update(array_filter([
                'name'            => $request->filled('name') ? trim($request->name) : null,
                'display_name'    => $request->filled('display_name') ? trim($request->display_name) : null,
                'description'     => $request->description,
                'variables_count' => $request->filled('variables_count') ? (int) $request->variables_count : null,
                'variable_labels' => $request->variable_labels,
                'used_in'         => $request->used_in,
                'is_active'       => $request->filled('is_active') ? $request->boolean('is_active') : null,
            ], fn ($v) => $v !== null));

            return response()->json([
                'message' => 'Template updated successfully',
                'data'    => $template->fresh(),
                'code'    => 200,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => implode(' ', $e->validator->errors()->all()), 'code' => 500]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 500]);
        }
    }

    public function destroy(Request $request)
    {
        try {
            $template = WhatsappTemplate::find($request->id);
            if (!$template) {
                return response()->json(['message' => 'Template not found', 'code' => 500]);
            }

            $template->delete();

            return response()->json(['message' => 'Template deleted successfully', 'code' => 200]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 500]);
        }
    }

    public function toggleActive(Request $request)
    {
        try {
            $template = WhatsappTemplate::find($request->id);
            if (!$template) {
                return response()->json(['message' => 'Template not found', 'code' => 500]);
            }

            $template->update(['is_active' => !$template->is_active]);

            return response()->json([
                'message'   => 'Template ' . ($template->is_active ? 'activated' : 'deactivated'),
                'is_active' => $template->is_active,
                'code'      => 200,
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 500]);
        }
    }
}
