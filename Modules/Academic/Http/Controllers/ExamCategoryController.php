<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Academic\Entities\ExamCategory;

class ExamCategoryController extends Controller
{
    /**
     * Search records
     * @param Request $request
     * @return JsonResponse
     */
    public function searchData(Request $request): JsonResponse
    {
        if ($request->expectsJson()) {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $limit = $request->post("limit");

            $query = ExamCategory::query()
                ->select("exam_category_id", "exam_category")
                ->where("category_status", "=", "1")
                ->orderBy("exam_category");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($searchText != "") {
                $query = $query->where("exam_category", "LIKE", "%" . $searchText . "%");
            }

            if ($idNot != "") {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("exam_category_id", $idNot);
            }

            $data = $query->get();

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }
}
