<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Academic\Entities\BatchGroup;
use Modules\Academic\Entities\Group;

class GroupController extends Controller
{
    /**
     * Search records
     * @param Request $request
     * @return JsonResponse
     */
    public function searchData(Request $request)
    {
        if($request->expectsJson())
        {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $courseId = $request->post("course_id");
            $batchId = $request->post("batch_id");
            $limit = $request->post("limit");

            $query = Group::query()
                ->select("GroupID", "GroupName")
                ->orderBy("GroupName");

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
                $query = $query->where("GroupName", "LIKE", "%".$searchText."%");
            }

            if($courseId !== null)
            {
                if (is_array($courseId) && count($courseId) > 0) {

                    $query = $query->whereIn("courseID", $courseId);
                } else {
                    $query = $query->where("courseID", $courseId);
                }
            }

            if($batchId !== null)
            {

                $groupIds = BatchGroup::query()
                    ->select("group_id");

                if (is_array($batchId) && count($batchId) > 0) {

                    $groupIds = $groupIds->whereIn("batch_id", $batchId);
                } else {
                    $groupIds = $groupIds->where("batch_id", $batchId);
                }

                $groupIds = $groupIds->get()
                ->keyBy("group_id")
                ->toArray();

                $groupIds = array_keys($groupIds);

                $query = $query->whereIn("GroupID", $groupIds);
            }

            if($idNot != "")
            {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("GroupID", $idNot);
            }

            $data = $query->get();

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }
}
