<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Academic\Entities\Person;

class PersonController extends Controller
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
            $staffType = $request->post("staff_type");
            $limit = $request->post("limit");

            $query = Person::query()
                ->select("id", "title_id", "given_name", "surname")
                ->where("status", 1)
                ->orderBy("given_name", "ASC")
                ->orderBy("surname", "ASC");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($searchText != "") {
                $query->where(function ($query) use ($searchText) {
                    $query->where("name_with_init", "LIKE", "%" . $searchText . "%")
                        ->orWhere("name_in_full", "LIKE", "%" . $searchText . "%")
                        ->orWhere("given_name", "LIKE", "%" . $searchText . "%")
                        ->orWhere("surname", "LIKE", "%" . $searchText . "%");
                });
            }

            if ($staffType !== null) {
                $query = $query->where("staff_type", $staffType);
            }

            if ($idNot != "") {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("id", $idNot);
            }

            $results = $query->get()->toArray();

            $data = [];

            if (is_array($results) && count($results) > 0) {

                foreach ($results as $result) {

                    $record = [];
                    $record["id"] = $result["id"];
                    $record["name"] = $result["name"];

                    $data[] = $record;
                }
            }

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }
}
