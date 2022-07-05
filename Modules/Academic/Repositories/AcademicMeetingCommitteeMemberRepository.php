<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\Storage;
use Modules\Academic\Entities\AcademicMeetingCommitteeMember;

class AcademicMeetingCommitteeMemberRepository extends BaseRepository
{
    public string $upload_dir = "public/committee_member_uploads/";

    public function getMeetingMemberIds($academicMeetingId)
    {
        $records = AcademicMeetingCommitteeMember::query()
            ->select("admin_id")
            ->where("academic_meeting_committee_id", $academicMeetingId)
            ->get()->keyBy("admin_id")->toArray();

        return array_keys($records);
    }

    public function uploadAppointmentLetter($currFileName = "")
    {
        $fileName = uniqid() . "_" . $_FILES["appointment_letter"]["name"];

        if (Storage::disk('local')->put($this->upload_dir . $fileName, file_get_contents($_FILES["appointment_letter"]["tmp_name"]))) {
            $this->deleteAppointmentLetter($currFileName);

            return $fileName;
        }

        return false;
    }

    public function downloadAppointmentLetter($fileName, $fileTitle = "")
    {
        if ($fileTitle == "") {
            $fileTitle = $fileName;
        }

        return Storage::download($this->upload_dir . $fileName, $fileTitle);
    }

    public function deleteAppointmentLetter($fileName)
    {
        if ($fileName != "") {
            Storage::delete($this->upload_dir . $fileName);
        }
    }
}
