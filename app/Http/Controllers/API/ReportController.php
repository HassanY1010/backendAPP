<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ad_id' => 'required|exists:ads,id',
            'reason' => 'required|string',
            'description' => 'nullable|string',
            'type' => 'in:ad,user,message', 
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $adId = $request->ad_id;
        $ad = \App\Models\Ad::find($adId);

        $report = Report::create([
            'reporter_id' => $request->user()->id,
            'ad_id' => $adId,
            'reported_user_id' => $ad ? $ad->user_id : null,
            'reason' => $request->reason,
            'description' => $request->description,
            'type' => $request->type ?? 'ad',
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Report submitted successfully',
            'data' => $report
        ], 201);
    }
}
