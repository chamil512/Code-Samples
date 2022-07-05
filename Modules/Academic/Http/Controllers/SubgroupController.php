<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Academic\Entities\ModuleDeliveryMode;
use Modules\Academic\Entities\Subgroup;
use Modules\Academic\Entities\SubgroupModule;
use Modules\Academic\Repositories\SimilarCourseModuleRepository;

class SubgroupController extends Controller
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
            $groupId = $request->post("group_id");
            $groupIds = $request->post("group_ids");
            $deliveryModeId = $request->post("delivery_mode_id");
            $deliveryModeIds = $request->post("delivery_mode_ids");
            $moduleId = $request->post("module_id");
            $limit = $request->post("limit");

            $query = Subgroup::query()
                ->select("id", "sg_name", "max_students", "dm_id", "year", "semester")
                ->orderBy("sg_name");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($searchText != "") {
                $query->where("sg_name", "LIKE", "%" . $searchText . "%");
            }

            if ($groupId !== null) {
                if (is_array($groupId) && count($groupId) > 0) {

                    $query = $query->whereIn("main_gid", $groupId);
                } else {
                    $query->where("main_gid", $groupId);
                }
            } else if ($groupIds !== null) {

                $groupIds = json_decode($groupIds, true);

                if (is_array($groupIds) && count($groupIds) > 0) {

                    $query->whereIn("main_gid", $groupIds);
                }
            }

            if ($deliveryModeId !== null) {
                $query->where("dm_id", $deliveryModeId);

            } else if ($deliveryModeIds !== null) {

                $deliveryModeIds = json_decode($deliveryModeIds, true);

                if (is_array($deliveryModeIds) && count($deliveryModeIds) > 0) {

                    $query->whereIn("dm_id", $deliveryModeIds);
                }
            }

            if ($idNot != "") {
                $idNot = json_decode($idNot, true);
                $query->whereNotIn("id", $idNot);
            }

            if ($moduleId !== null) {

                //get subject group ids which are having this id or similar id
                $similarIds = SimilarCourseModuleRepository::getSimilarIds($moduleId);
                $similarIds[] = $moduleId;

                $subgroups = SubgroupModule::query()
                    ->select("subgroup_id")
                    ->whereIn("module_id", $similarIds)
                    ->get()->keyBy("subgroup_id")->toArray();

                $subgroupIds = array_keys($subgroups);

                $query->whereIn("id", $subgroupIds);
            }

            $results = $query->get()->toArray();

            $data = [];
            if (is_array($results) && count($results) > 0) {

                foreach ($results as $result) {

                    if (isset($result["academic_year"])) {

                        unset($result["academic_year"]);
                    }

                    if (isset($result["academic_semester"])) {

                        unset($result["academic_semester"]);
                    }

                    $data[] = $result;
                }
            }

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }

    /**
     * Get subgroup modules list
     * @param $id
     * @return JsonResponse
     */
    public function getSubGroupModules($id): JsonResponse
    {
        $model = Subgroup::with(["subgroupModules"])->find($id);

        if ($model) {
            $subgroupModules = $model->subgroupModules;

            $modules = [];
            if (count($subgroupModules) > 0) {
                foreach ($subgroupModules as $subgroupModule) {
                    $moduleModel = $subgroupModule->module;
                    if ($moduleModel != null) {
                        $record = $moduleModel->toArray();

                        $module = [];
                        $module["id"] = $record["id"];
                        $module["name"] = $record["name"];
                        $module["code"] = $record["module_code"];

                        $modules[] = $module;
                    }
                }
            }

            $response["status"] = "success";
            $response["data"] = $modules;
        } else {
            $response["status"] = "failed";
            $response["data"] = [];
        }

        return response()->json($response, 201);
    }

    public function searchGroupDeliveryMode(Request $request): JsonResponse
    {
        if ($request->expectsJson()) {
            $searchText = $request->post("query");
            $idNot = $request->post("idNot");
            $groupId = $request->post("group_id");
            $limit = $request->post("limit");

            $query = ModuleDeliveryMode::query()
                ->select("delivery_mode_id", "mode_name")
                ->orderBy("mode_name")
                ->join("subgroups", "delivery_mode_id", "=", "dm_id")
                ->where(["main_gid" => $groupId, "mode_status" => 1]);

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($searchText != "") {
                $query = $query->where("mode_name", "LIKE", "%" . $searchText . "%");
            }

            if ($idNot != "") {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("id", $idNot);
            }

            $results = $query->get()->toArray();

            $data = [];
            if (is_array($results) && count($results) > 0) {

                foreach ($results as $result) {

                    if (isset($result["academic_year"])) {

                        unset($result["academic_year"]);
                    }

                    if (isset($result["academic_semester"])) {

                        unset($result["academic_semester"]);
                    }

                    $data[] = $result;
                }
            }

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }
}
