<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Academic\Entities\Batch;

class BatchController extends Controller
{
    /**
     * Search records
     * @param Request $request
     * @return JsonResponse
     */
    public function searchData(Request $request): JsonResponse
    {
        if($request->expectsJson())
        {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $courseId = $request->post("course_id");
            $syllabusId = $request->post("syllabus_id");
            $limit = $request->post("limit");

            $query = Batch::query()
                ->select("batch_id", "batch_name")
                ->where("batch_status", "APPROVED")
                ->orderBy("batch_name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if($searchText != "")
            {
                $query = $query->where("batch_name", "LIKE", "%".$searchText."%");
            }

            if($courseId !== null)
            {
                if (is_array($courseId) && count($courseId) > 0) {

                    $courseIds = $courseId;
                } else {

                    $courseIds = [];
                    $courseIds[] = $courseId;
                }

                $query->whereHas("syllabus", function ($query) use($courseIds) {

                    $query->whereIn("course_id", $courseIds);
                });
            }

            if($syllabusId !== null)
            {
                if (is_array($syllabusId) && count($syllabusId) > 0) {

                    $query->whereIn("syllabus_id", $syllabusId);
                } else {
                    $query->where("syllabus_id", $syllabusId);
                }
            }

            if($idNot != "")
            {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("batch_id", $idNot);
            }

            $data = $query->get();

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }
}
