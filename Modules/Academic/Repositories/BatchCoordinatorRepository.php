<?php
namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Modules\Academic\Entities\Batch;
use Modules\Academic\Entities\BatchCoordinator;

class BatchCoordinatorRepository extends BaseRepository
{
    public string $statusField= "status";
    public bool $isValidHOD = false;

    public array $statuses = [
        ["id" =>"1", "name" =>"Current Coordinator", "label"=>"success"],
        ["id" =>"0", "name" =>"Former Coordinator", "label"=>"info"]
    ];

    /**
     * @param $batchId
     * @return array
     */
    public function validateHOD($batchId): array
    {
        //check if current user is the HOD of the selected batch
        $batch = Batch::with(["syllabus"])->find($batchId);

        //assume this will get failed
        $this->isValidHOD = false;

        if ($batch) {

            $syllabus = $batch->syllabus;

            if ($syllabus->course) {
                $course = $syllabus->course;

                //get hod of above department
                $hod = DepartmentHeadRepository::getHODAdmin($course->dept_id);

                if ($hod && $hod["id"] === auth("admin")->user()->admin_id) {

                    $this->isValidHOD = true;
                    $response["notify"]["status"] = "success";
                } else {

                    $response["notify"]["status"] = "failed";
                    $response["notify"]["notify"][] = "You can not add batch coordinators to other department batches.";
                    $response["notify"]["notify"][] = "You need to be the department head of the corresponding department.";
                }
            } else {

                $response["notify"]["status"] = "failed";
                $response["notify"]["notify"][] = "Requested course does not exist of the requested batch.";
            }
        } else {

            $response["notify"]["status"] = "failed";
            $response["notify"]["notify"][] = "Requested batch does not exist.";
        }

        return $response;
    }

    /**
     * @param $batchId
     * @return array
     */
    public static function getBatchCoordinatorIds($batchId): array
    {
        $records = BatchCoordinator::query()
            ->select("admin_id")
            ->where("batch_id", $batchId)
            ->where("status", 1)
            ->get()
            ->keyBy("admin_id")
            ->toArray();

        return array_keys($records);
    }
}
