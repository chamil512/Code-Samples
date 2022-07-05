<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Academic\Entities\AcademicMeetingAgendaItem;

class AcademicMeetingAgendaItemController extends Controller
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
            $agendaId = $request->post("agenda_id");
            $parentItemId = $request->post("parent_item_id");
            $limit = $request->post("limit");

            $query = AcademicMeetingAgendaItem::query()
                ->select("id", "item_number", "item_heading")
                ->where("academic_meeting_agenda_id", "=", $agendaId)
                ->where("item_status", "=", "1")
                ->orderBy("item_heading");

            if ($limit === null) {

                $query->limit(10);
            } else {

                $limit = intval($limit);
                if ($limit > 0) {

                    $query->limit($limit);
                }
            }

            if ($parentItemId != "") {

                $query = $query->where("parent_item_id", "=", $parentItemId);
            }

            if($searchText != "")
            {
                $query = $query->where("item_heading", "LIKE", "%".$searchText."%");
            }

            if($idNot != "")
            {
                $idNot = json_decode($idNot, true);
                $query = $query->whereNotIn("id", $idNot);
            }

            $data = $query->get();

            return response()->json($data, 201);
        }

        abort("403", "You are not allowed to access this data");
    }
}
