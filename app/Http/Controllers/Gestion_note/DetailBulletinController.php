<?php

namespace App\Http\Controllers\Gestion_note;

use App\Http\Controllers\Controller;
use App\Models\Gestion_note\DetailBulletins;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DetailBulletinController extends Controller
{
    public function getByBulletin($bulletinId)
    {
        return response()->json([
            'success' => true,
            'data' => DetailBulletins::where('bulletin_id', $bulletinId)
                ->with('matiere')
                ->get()
        ]);
    }

    public function update(Request $request, $id)
    {
        $detail = DetailBulletins::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'moyenne_matiere' => 'sometimes|numeric|min:0|max:20',
            'rang_matiere' => 'sometimes|integer|min:1',
            'appreciation' => 'sometimes|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $detail->update($request->only([
            'moyenne_matiere',
            'rang_matiere',
            'appreciation'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'DÃ©tail du bulletin mis Ã  jour',
            'data' => $detail->refresh()->load('matiere')
        ]);
    }
}
