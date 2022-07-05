<?php

namespace Modules\Academic\Repositories;

use App\Helpers\Helper;
use App\Repositories\BaseRepository;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Academic\Entities\ExamCalendar;
use Modules\Academic\Entities\ScrutinyBoard;
use Modules\Academic\Entities\ScrutinyBoardExamType;
use Modules\Academic\Entities\ScrutinyBoardModule;
use Modules\Academic\Entities\ScrutinyBoardPeople;
use Modules\Academic\Entities\ScrutinyBoardQuestion;
use Modules\Academic\Entities\SyllabusModule;
use Modules\Exam\Repositories\ExamCategoryRepository;
use Modules\Exam\Repositories\ExamTypeRepository;

class ScrutinyBoardRepository extends BaseRepository
{
    public string $statusField = "status";

    private array $currentPeopleRecords = [];
    private array $currentQuestionRecords = [];
    public bool $withPeople = false;
    private bool $createAssign = false;
    public bool $withQuestions = false;
    public bool $isForTable = false;
    private array $modules = [];
    private array $staffTypes = [
        ["id" => 1, "name" => "Internal"],
        ["id" => 2, "name" => "Visiting"],
    ];
    private ?array $staffTypesById = null;
    private array $examMethods = [
        ["id" => 1, "name" => "Onsite"],
        ["id" => 2, "name" => "Online"],
    ];
    private array $activeStatuses = [
        ["id" => 0, "name" => "Inactive"],
        ["id" => 1, "name" => "Active"],
    ];
    private array $durations = [
        ["id" => 60, "name" => "1 Hour"],
        ["id" => 120, "name" => "2 Hours"],
        ["id" => 180, "name" => "3 Hours"],
        ["id" => 240, "name" => "4 Hours"],
        ["id" => 300, "name" => "5 Hours"],
        ["id" => 360, "name" => "6 Hours"],
        ["id" => 420, "name" => "7 Hours"],
        ["id" => 480, "name" => "8 Hours"],
        ["id" => 540, "name" => "9 Hours"],
        ["id" => 600, "name" => "10 Hours"],
    ];
    private ?array $durationsById = null;

    private array $examPersonTypes = [
        ["id" => "examiner_type", "name" => "Examiner"],
        ["id" => "paper_typing", "name" => "Paper Typing"],
        ["id" => "paper_setting", "name" => "Paper Setting"],
        ["id" => "paper_marking", "name" => "Paper Marking"],
    ];
    private array $currentRecordIds = [
        "modules" => [],
        "exam_types" => [],
        "people" => [],
        "questions" => [],
    ];
    private array $updatingRecordIds = [
        "modules" => [],
        "exam_types" => [],
        "people" => [],
        "questions" => [],
    ];

    public array $statuses = [
        ["id" => "1", "name" => "Enabled", "label" => "success"],
        ["id" => "0", "name" => "Disabled", "label" => "danger"]
    ];

    public array $approvalStatuses = [
        ["id" => "", "name" => "Not Sent for Approval", "label" => "info"],
        ["id" => "0", "name" => "Verification Pending", "label" => "warning"],
        ["id" => "3", "name" => "Verified & Pending Approval", "label" => "success"],
        ["id" => "4", "name" => "Verification Declined", "label" => "danger"],
        ["id" => "1", "name" => "Approved", "label" => "success"],
        ["id" => "2", "name" => "Declined", "label" => "danger"],
    ];

    public array $assignedApprovalStatuses = [
        ["id" => "", "name" => "Not Sent for Approval", "label" => "info"],
        ["id" => "0", "name" => "Final Approval Pending", "label" => "warning"],
        ["id" => "1", "name" => "Approved", "label" => "success"],
        ["id" => "2", "name" => "Declined", "label" => "danger"],
    ];

    private array $moduleRowSpans = [];
    private array $examTypeRowSpans = [];
    private array $examCategoryRowSpans = [];
    private array $questionRowSpans = [];

    /*
     * Approval properties and methods starts
     */
    public string $approvalField = "approval_status";
    public $approvalDefaultStatus = "0";
    protected array $approvalSteps = [
        [
            "step" => "verification",
            "approvedStatus" => 3,
            "declinedStatus" => 4,
            "route" => "/academic/scrutiny_board/verification",
            "permissionRoutes" => [],
        ],
        [
            "step" => "approval",
            "approvedStatus" => 1,
            "declinedStatus" => 2,
            "route" => "/academic/scrutiny_board/approval",
            "permissionRoutes" => [],
        ],
    ];

    /**
     * @param $model
     */
    public function setApprovalData($model)
    {
        if ($model->type === 3) {

            if ($this->withQuestions) {

                $this->approvalField = "sb_approval_status";
                $this->approvalSteps = [
                    [
                        "step" => "verification",
                        "approvedStatus" => 3,
                        "declinedStatus" => 4,
                        "route" => "/academic/scrutiny_board_assign_sb/verification",
                        "permissionRoutes" => [],
                    ],
                    [
                        "step" => "approval",
                        "approvedStatus" => 1,
                        "declinedStatus" => 2,
                        "route" => "/academic/scrutiny_board_assign_sb/approval",
                        "permissionRoutes" => [],
                    ]
                ];
            } else {

                $this->approvalStatuses = $this->assignedApprovalStatuses;
                $this->approvalSteps = [
                    [
                        "step" => "approval",
                        "approvedStatus" => 1,
                        "declinedStatus" => 2,
                        "route" => "/academic/scrutiny_board_assign/approval",
                        "permissionRoutes" => [],
                    ]
                ];
            }
        }
    }

    /**
     * @param $model
     * @param $step
     * @return array
     */
    protected function getApprovalStepUsers($model, $step): array
    {
        $data = [];

        if ($model->type === 1) {

            if ($step === "verification") {

                $course = $model->course;

                //get department id
                $deptId = $course->dept_id;

                //get head of the department's id
                $data = DepartmentHeadRepository::getHODAdmins($deptId);
            }
        } else {

            $academicCalendar = $model->academicCalendar;

            if ($step === "verification") {

                if ($this->withQuestions) {

                    //get department id
                    $deptId = $academicCalendar->dept_id;

                    //get head of the department's id
                    $data = DepartmentHeadRepository::getHODAdmins($deptId);
                }
            } else {

                if ($this->withQuestions) {

                    //get department id
                    $facultyId = $academicCalendar->faculty_id;

                    //get head of the department's id
                    $admin = FacultyDeanRepository::getDeanAdmin($facultyId);

                    if ($admin && isset($admin["id"])) {

                        $data[] = $admin["id"];
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param $model
     * @param $step
     * @return string
     */
    protected function getApprovalStepTitle($model, $step): string
    {
        switch ($step) {
            case "verification" :

                if ($model->type === 1) {

                    $text = $model->name . " verification.";
                } else {

                    if ($this->withQuestions) {

                        $text = $model->name . " scrutiny board paper verification.";
                    } else {

                        $text = $model->name . " academics assigned scrutiny board verification.";
                    }
                }
                break;

            case "approval" :

                if ($model->type === 1) {

                    $text = $model->name . " approval.";
                } else {

                    if ($this->withQuestions) {

                        $text = $model->name . " scrutiny board paper approval.";
                    } else {

                        $text = $model->name . " academics assigned scrutiny board approval.";
                    }
                }
                break;

            default:
                $text = "";
                break;
        }

        return $text;
    }

    /**
     * @param $model
     * @param $step
     * @return string|Application|Factory|View
     */
    protected function getApprovalStepDescription($model, $step): View
    {
        $record = $model->toArray();

        $fileName = $step;
        if ($model->type === 1) {

            $url = URL::to("/academic/scrutiny_board/view/" . $model->id);

            return view("academic::scrutiny_board.approvals." . $fileName, compact('record', 'url'));
        } else {

            if ($this->withQuestions) {

                $url = URL::to("/academic/scrutiny_board_assign_sb/view/" . $model->id);

                return view("academic::scrutiny_board_assign.scrutiny_board.approvals." . $fileName, compact('record', 'url'));
            } else {

                $url = URL::to("/academic/scrutiny_board_assign/view/" . $model->id);

                return view("academic::scrutiny_board_assign.approvals." . $fileName, compact('record', 'url'));
            }
        }
    }

    protected function onApproved($model, $step, $previousStatus)
    {
        $model->{$this->statusField} = 1;
        $model->save();

        if ($model->type === 1) {

            if ($step === "approval") {

                if ($previousStatus !== 1) {

                    if ($model->academic) {

                        $slave = $model->academic;
                        $slave->delete();
                    }

                    $replica = $this->replicate($model);
                    $replica->type = 2;
                    $replica->{$this->statusField} = 1;
                    $replica->master_scrutiny_board_id = $model->id;
                    $replica->save();
                }
            }
        }
    }

    protected function onDeclined($model, $step, $previousStatus)
    {
        if ($model->type === 1) {

            if ($step === "approval" && $model->{$this->statusField} === 1) {

                $model->{$this->statusField} = 0;
                $model->save();

                if ($model->academic) {

                    $slave = $model->academic;
                    $slave->{$this->statusField} = 0;

                    $slave->save();
                }
            }
        }
    }

    /*
     * Approval properties and methods ends
     */

    public function replicate($model)
    {
        $replica = $model->replicate();

        $replica->push();

        $modules = $model->modules()->get();

        if (count($modules) > 0) {

            foreach ($modules as $module) {

                $modModel = $module->replicate();
                $modModel->scrutiny_board_id = $replica->id;

                $modModel->push();

                $examTypes = $module->examTypes()->get();

                if (count($examTypes) > 0) {

                    foreach ($examTypes as $examType) {

                        $eTModel = $examType->replicate();
                        $eTModel->scrutiny_board_module_id = $modModel->id;

                        $eTModel->push();
                    }
                }
            }
        }

        return $replica;
    }

    /**
     * @param $model
     * @param $statusField
     * @param $status
     * @param bool $allowed
     * @return bool
     */
    protected function isStatusUpdateAllowed($model, $statusField, $status, bool $allowed = true): bool
    {
        $approvalField = $this->approvalField;

        if ($model->{$approvalField} != "1") {

            $errors = [];
            $errors[] = "This record should have been approved to be eligible to update the status.";

            $this->setErrors($errors);
            $allowed = false;
        }

        return parent::isStatusUpdateAllowed($model, $statusField, $status, $allowed);
    }

    /**
     * @param $model
     * @return array
     */
    private function _getSBModules($model): array
    {
        $data = [];
        $currRecordIds = [];
        $currRecordIds["modules"] = [];
        $currRecordIds["exam_types"] = [];
        $currRecordIds["people"] = [];
        $currRecordIds["questions"] = [];

        $this->currentRecordIds = $currRecordIds;
        $this->currentPeopleRecords = [];
        $this->currentQuestionRecords = [];
        if ($model) {

            $relations = [
                "modules",
                "modules.examTypes",
                "modules.examTypes.examCategory:exam_category_id,exam_category",
            ];

            if ($this->withPeople) {

                $relations[] = "modules.examTypes.people:id,scrutiny_board_exam_type_id,person_id,exam_person_type,sort_order";
                $relations[] = "modules.examTypes.people.person:id,title_id,qualification_id,given_name,surname";
                $relations[] = "modules.examTypes.people.person.title:title_id,title_name";
                $relations[] = "modules.examTypes.people.person.qualification:qualification_id,qualification";
                $relations[] = "modules.examTypes.timeSlot:academic_timetable_information_id,tt_date,start_time,end_time,module_id";
                $relations[] = "modules.examTypes.timeSlot.module:module_id,module_code,module_name";
            }

            if ($this->withQuestions) {

                $relations[] = "modules.examTypes.questions";
                $relations[] = "modules.examTypes.questions.examCategory:exam_category_id,exam_category";
                $relations[] = "modules.examTypes.questions.questionMadeBy:id,title_id,qualification_id,given_name,surname";
            }

            $model->load($relations);

            $record = $model->toArray();

            $sBModules = $record["modules"];

            if (is_array($sBModules) && count($sBModules) > 0) {

                foreach ($sBModules as $sbModule) {

                    $moduleId = $sbModule["module_id"];
                    unset($sbModule["module_id"]);

                    $sbExamTypes = $sbModule["exam_types"];

                    $examTypes = [];
                    if (is_array($sbExamTypes) && count($sbExamTypes) > 0) {

                        foreach ($sbExamTypes as $sbExamType) {

                            $examMethodId = $sbExamType["exam_method"];
                            $examTypeId = $sbExamType["exam_type_id"];
                            $becId = $sbExamType["based_exam_category_id"];

                            $sbExamType["duration_hours"] = Helper::convertMinutesToHumanTime($sbExamType["duration_in_minutes"]);
                            $sbExamType = $this->_getStaffTypePrepared($sbExamType);
                            $sbExamType = $this->_getDurationPrepared($sbExamType);
                            $sbExamType = $this->_getActiveStatusPrepared($sbExamType);

                            $examCategory = [];
                            $examCategory["id"] = $sbExamType["exam_category"]["id"];
                            $examCategory["name"] = $sbExamType["exam_category"]["name"];
                            $examCategory["marks_percentage"] = $sbExamType["marks_percentage"];

                            if (!isset($examTypes[$examMethodId][$examTypeId][$becId])) {

                                $examTypes[$examMethodId][$examTypeId][$becId]["exam_category"]= $examCategory;
                                $examTypes[$examMethodId][$examTypeId][$becId]["records"] = [];
                            }

                            if ($this->withPeople) {

                                $timeSlot = $this->_getTimeSlotPrepared($sbExamType["time_slot"]);
                                $examTypes[$examMethodId][$examTypeId][$becId]["time_slot"] = $timeSlot;

                                $examTypes[$examMethodId][$examTypeId][$becId]["active_status"] = $sbExamType["active_status"];

                                $sbExamType["people"] = $sbExamType["people"] ?? [];
                                $sbExamType["people"] = $this->_getPreparedPeople($sbExamType["people"], $sbExamType["id"]);
                            } else {

                                $sbExamType["people"] = [];
                            }

                            if ($this->withQuestions) {

                                $sbExamType["questions"] = $sbExamType["questions"] ?? [];
                                $sbExamType["questions"] = $this->_getPreparedQuestions($sbExamType["questions"], $sbExamType["no_of_questions"], $sbExamType["id"]);
                            } else {

                                $sbExamType["questions"] = [];
                            }

                            $this->currentRecordIds["exam_types"][] = $sbExamType["id"];
                            $examTypes[$examMethodId][$examTypeId][$becId]["records"][] = $sbExamType;
                        }
                    }

                    $this->currentRecordIds["modules"][] = $sbModule["id"];

                    $data[$moduleId]["id"] = $sbModule["id"];
                    $data[$moduleId]["exam_types"] = $examTypes;
                }
            }
        }

        return $data;
    }

    /**
     * @param $examType
     * @return array
     */
    private function _getDurationPrepared($examType): array
    {
        $examType["duration"] = 0;

        if ($this->durationsById === null) {

            $this->durationsById = Helper::convertArrayToObject($this->durations, "id");
        }

        if (isset($this->durationsById[$examType["duration_in_minutes"]])) {

            $examType["duration"] = $this->durationsById[$examType["duration_in_minutes"]];
        }

        return $examType;
    }

    /**
     * @param $examType
     * @return array
     */
    private function _getActiveStatusPrepared($examType): array
    {
        if ($examType["active_status"] === "") {

            $examType["active_status"] = 0;
        }
        $activeStatuses = Helper::convertArrayToObject($this->activeStatuses, "id");

        $examType["active_status"] = $activeStatuses[$examType["active_status"]];

        return $examType;
    }

    /**
     * @param $timeSlot
     * @return array
     */
    private function _getTimeSlotPrepared($timeSlot): array
    {
        $data = [];
        if (isset($timeSlot["id"])) {

            $record = [];
            $record["id"] = $timeSlot["id"];
            $record["name"] = $timeSlot["name"];
            $record["date"] = date(" l, F d, Y", strtotime($timeSlot["tt_date"]));
            $record["start_time"] = date("h:i A", strtotime($timeSlot["start_time"]));
            $record["end_time"] = date("h:i A", strtotime($timeSlot["end_time"]));

            $data = $record;
        }

        return $data;
    }

    /**
     * @param $examType
     * @return array
     */
    private function _getStaffTypePrepared($examType): array
    {
        if ($this->staffTypesById === null) {

            $this->staffTypesById = Helper::convertArrayToObject($this->staffTypes, "id");
        }

        if (is_array($this->examPersonTypes) && count($this->examPersonTypes) > 0) {

            foreach ($this->examPersonTypes as $examPersonType) {

                $examPersonTypeId = $examPersonType["id"];

                $staffType = $examType[$examPersonTypeId] ?? "";
                $examType[$examPersonTypeId] = $this->staffTypesById[$staffType] ?? [];
            }
        }

        return $examType;
    }

    /**
     * @param $syllabus
     * @param $academicCalendar
     * @param bool $withPeople
     * @param null $model
     * @return array
     */
    public function getPreparedData($syllabus, $academicCalendar, $withPeople = false, $model = null): array
    {
        $this->withPeople = $withPeople;

        $academicYearId = null;
        $semesterId = null;
        if ($academicCalendar) {

            $academicYearId = $academicCalendar->academic_year_id;
            $semesterId = $academicCalendar->semester_id;
        }

        $syllabusModules = $this->_getSyllabusModules($syllabus->id, $academicYearId, $semesterId);
        $sBModules = $this->_getSBModules($model);

        $examCategories = ExamCategoryRepository::getExamCategories();
        $examCategoriesById = Helper::convertArrayToObject($examCategories, "id");

        $data = [];
        $data["records"] = [];
        $data["modules"] = Helper::convertObjectToArray($this->modules);
        $data["modulesById"] = $this->modules;
        $data["examMethods"] = Helper::convertArrayToObject($this->examMethods, "id");
        $data["examTypesById"] = Helper::convertArrayToObject(ExamTypeRepository::getExamTypes(), "id");
        $data["examCategories"] = $examCategories;
        $data["examCategoriesById"] = $examCategoriesById;
        $data["staffTypes"] = $this->staffTypes;
        $data["durations"] = $this->durations;
        $data["activeStatuses"] = $this->activeStatuses;
        $data["examPersonTypes"] = $this->examPersonTypes;
        $data["withPeople"] = $this->withPeople;
        $data["withQuestions"] = $this->withQuestions;
        $data["availablePeopleFetchUrl"] = URL::to("/academic/person/search_data");
        $data["timetableSlotFetchUrl"] = URL::to("/academic/academic_timetable_information/search_data");
        $data["examCategoryFetchUrl"] = URL::to("/academic/exam_category/search_data");

        if (is_array($syllabusModules) && count($syllabusModules) > 0) {

            foreach ($syllabusModules as $moduleId => $examTypes) {

                $record = [];
                $record["id"] = "";
                $record["module_id"] = $moduleId;
                $record["examMethods"] = [];

                $sbExamTypes = [];
                if (isset($sBModules[$moduleId])) {

                    $sBModule = $sBModules[$moduleId];

                    $record["id"] = $sBModule["id"];
                    $sbExamTypes = $sBModule["exam_types"];
                }

                foreach ($this->examMethods as $em) {

                    $examMethodId = $em["id"];

                    $examMethod = [];
                    $examMethod["id"] = $examMethodId;
                    $examMethod["examTypes"] = [];

                    if (is_array($examTypes) && count($examTypes) > 0) {

                        foreach ($examTypes as $examTypeId => $examCategories) {

                            $examType = [];
                            $examType["id"] = $examTypeId;
                            $examType["examCategories"] = [];

                            foreach ($examCategories as $bec) {

                                $becId = $bec["id"];
                                $marksPercentage = $bec["marks_percentage"];

                                $basedExamCategory = [];
                                $basedExamCategory["id"] = $becId;
                                $basedExamCategory["based_category"] = $examCategoriesById[$becId];
                                $basedExamCategory["based_category"]["marks_percentage"] = $marksPercentage;
                                $basedExamCategory["records"] = [];

                                if (isset($sbExamTypes[$examMethodId][$examTypeId][$becId])) {

                                    $examCategory = $sbExamTypes[$examMethodId][$examTypeId][$becId]["exam_category"];
                                    $sbETs = $sbExamTypes[$examMethodId][$examTypeId][$becId]["records"];

                                    if ($this->withPeople) {

                                        $timeSlot = $sbExamTypes[$examMethodId][$examTypeId][$becId]["time_slot"];
                                        $basedExamCategory["time_slot"] = $timeSlot;

                                        $activeStatus = $sbExamTypes[$examMethodId][$examTypeId][$becId]["active_status"];
                                        $basedExamCategory["active_status"] = $activeStatus;
                                    }

                                    if ($examMethodId === 1) {
                                        //for onsite always get the syllabus marks percentage
                                        $examCategory["marks_percentage"] = $marksPercentage;
                                    }

                                    if ($examCategory["marks_percentage"] === "") {

                                        $examCategory["marks_percentage"] = 0;
                                    }

                                    $basedExamCategory["exam_category"] = $examCategory;
                                    $basedExamCategory["records"] = $sbETs;
                                } else {

                                    $sbET = $this->_getEmptyExamType($examMethodId, $examTypeId, $becId);

                                    $basedExamCategory["active_status"] = $sbET["active_status"];
                                    $basedExamCategory["time_slot"] = [];
                                    $basedExamCategory["exam_category"] = $basedExamCategory["based_category"];
                                    $basedExamCategory["records"][] = $sbET;
                                }

                                $examType["examCategories"][] = $basedExamCategory;
                            }

                            $examMethod["examTypes"][] = $examType;
                        }
                    }

                    $record["examMethods"][] = $examMethod;
                }

                $data["records"][] = $record;
            }
        }

        return $data;
    }

    /**
     * @param $examMethodId
     * @param $examTypeId
     * @param $becId
     * @return array
     */
    private function _getEmptyExamType($examMethodId, $examTypeId, $becId): array
    {
        $examType = [];
        $examType["id"] = "";
        $examType["exam_method"] = $examMethodId;
        $examType["exam_type_id"] = $examTypeId;
        $examType["exam_category_id"] = $becId;
        $examType["active_status"] = 0;
        $examType["no_of_questions"] = 0;
        $examType["duration"] = [];
        $examType["duration_hours"] = 0;
        $examType["people"] = [];
        $examType["records"] = [];
        $examType["exam_category"] = [];

        $examType = $this->_getStaffTypePrepared($examType);
        $examType = $this->_getActiveStatusPrepared($examType);

        if ($this->withPeople) {

            $examType["people"] = $this->_getPreparedPeople();
        }

        if ($this->withQuestions) {

            $examType["questions"] = $this->_getPreparedQuestions();
        }

        return $examType;
    }

    /**
     * @param array $people
     * @param bool $examTypeId
     * @return array
     */
    private function _getPreparedPeople($people = [], $examTypeId = false): array
    {
        $data = [];
        $examPersonTypes = $this->examPersonTypes;
        if (is_array($examPersonTypes) && count($examPersonTypes) > 0) {

            foreach ($examPersonTypes as $personType) {

                $data[$personType["id"]] = [];
            }
        }

        if (is_array($people) && count($people) > 0) {

            $orderColumn = array_column($people, 'sort_order');
            array_multisort($orderColumn, SORT_ASC, $people);

            foreach ($people as $person) {

                $this->currentRecordIds["people"][] = $person["person"]["id"];

                $data[$person["exam_person_type"]][] = $person["person"];

                if ($examTypeId) {

                    $this->currentPeopleRecords[$examTypeId][$person["exam_person_type"]][$person["person_id"]] = $person["id"];
                }
            }
        }

        return $data;
    }

    /**
     * @param array $questions
     * @param int $noOfQuestions
     * @param bool $examTypeId
     * @return array
     */
    private function _getPreparedQuestions($questions = [], $noOfQuestions = 0, $examTypeId = false): array
    {
        $data = [];
        if ($this->isForTable) {

            if ($noOfQuestions === 0) {

                //set as 1 to overcome row issue in table
                $noOfQuestions = 1;
            }
        }

        $count = 0;
        if (is_array($questions) && count($questions) > 0) {

            $orderColumn = array_column($questions, 'question_no');
            array_multisort($orderColumn, SORT_ASC, $questions);

            foreach ($questions as $question) {

                $count++;

                if ($count <= $noOfQuestions) {

                    $examCategory = $question["exam_category"];
                    $questionMadeBy = $question["question_made_by"];

                    unset($question["exam_category"]);
                    unset($question["question_made_by"]);

                    $question["examCategory"] = $examCategory === null ? [] : $examCategory;
                    $question["questionMadeBy"] = $questionMadeBy === null ? [] : $questionMadeBy;

                    $data[] = $question;
                }

                $this->currentRecordIds["questions"][] = $question["id"];

                if ($examTypeId) {

                    $this->currentQuestionRecords[$examTypeId][$question["id"]] = $question["id"];
                }
            }
        }

        $remainingCount = $noOfQuestions - $count;

        if ($remainingCount > 0) {

            //set empty records for the remaining count
            for($inc = 0; $inc < $remainingCount; $inc++) {

                $count++;

                $question = [];
                $question["id"] = "";
                $question["question_no"] = "";
                $question["marks"] = "";
                $question["remarks"] = "";
                $question["examCategory"] = [];
                $question["questionMadeBy"] = [];

                $data[] = $question;
            }
        }

        return $data;
    }

    /**
     * @param $syllabusId
     * @param $academicYearId
     * @param $semesterId
     * @return array
     */
    private function _getSyllabusModules($syllabusId, $academicYearId = null, $semesterId = null): array
    {
        $query = SyllabusModule::query()
            ->with(["module:module_id,module_code,module_name",
                "examTypes",
                "examTypes.examType:exam_type_id,exam_type",
                "examTypes.examCategory:exam_category_id,exam_category"
            ])
            ->where("syllabus_id", $syllabusId);

        if ($academicYearId && $semesterId) {

            $query->whereHas("module", function ($query) use ($academicYearId, $semesterId) {

                $query->where("academic_year_id", $academicYearId)
                    ->where("semester_id", $semesterId);
            });
        }

        $records = $query->get()->keyBy("module_id")->toArray();

        $data = [];
        $this->modules = [];
        if (is_array($records) && count($records) > 0) {

            foreach ($records as $record) {

                $syllabusETs = $record["exam_types"];
                if (is_array($syllabusETs) && count($syllabusETs) > 0) {

                    $moduleId = $record["module"]["id"];

                    $module = [];
                    $module["id"] = $record["module"]["id"];
                    $module["name"] = $record["module"]["name"];

                    if (!isset($this->modules[$moduleId])) {

                        $this->modules[$moduleId] = $module;
                    }

                    $examTypes = [];
                    foreach ($syllabusETs as $syllabusET) {

                        if (isset($syllabusET["exam_type"]["id"]) && isset($syllabusET["exam_category"]["id"])) {

                            $examTypeId = $syllabusET["exam_type"]["id"];
                            $examCategoryId = $syllabusET["exam_category"]["id"];

                            if (!isset($examTypes[$examTypeId])) {

                                $examTypes[$examTypeId] = [];
                            }

                            $examCategory = [];
                            $examCategory["id"] = $examCategoryId;
                            $examCategory["marks_percentage"] = $syllabusET["marks_percentage"];

                            if (empty($examCategory["marks_percentage"])) {

                                $examCategory["marks_percentage"] = 0;
                            }

                            $examTypes[$examTypeId][] = $examCategory;
                        }
                    }

                    $data[$moduleId] = $examTypes;
                }
            }
        }

        return $data;
    }

    /**
     * @param $model
     * @param bool $withPeople
     * @param array $records
     * @return array
     */
    public function updateData($model, $withPeople = false, $records = []): array
    {
        $this->withPeople = $withPeople;

        $this->_getSBModules($model);
        $currentRecordIds = $this->currentRecordIds;

        $this->updatingRecordIds = [
            "modules" => [],
            "exam_types" => [],
            "people" => [],
            "questions" => [],
        ];

        //assume process will get success
        $success = true;
        DB::beginTransaction();
        try {

            if (!$records) {

                $records = request()->post("data");
                $records = json_decode($records, true);
            }

            if (is_array($records) && count($records) > 0) {

                foreach ($records as $record) {

                    $moduleId = $record["module_id"];

                    if (!$this->createAssign && isset($record["id"]) && $record["id"] !== "") {

                        $sbModuleId = $record["id"];
                        $this->updatingRecordIds["modules"][] = $sbModuleId;
                    } else {

                        $sbModule = new ScrutinyBoardModule();
                        $sbModule->scrutiny_board_id = $model->id;
                        $sbModule->module_id = $moduleId;

                        if ($sbModule->save()) {

                            $sbModuleId = $sbModule->id;
                        } else {

                            $success = false;
                            break;
                        }
                    }

                    if (is_array($record["examMethods"]) && count($record["examMethods"]) > 0) {

                        foreach ($record["examMethods"] as $examMethod) {

                            if (is_array($examMethod["examTypes"]) && count($examMethod["examTypes"]) > 0) {

                                foreach ($examMethod["examTypes"] as $examType) {

                                    if (is_array($examType["examCategories"]) && count($examType["examCategories"]) > 0) {

                                        foreach ($examType["examCategories"] as $examCategory) {

                                            $success = $this->_updateExamTypes($sbModuleId, $examCategory);

                                            if (!$success) {
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if ($success) {

                $updatingRecordIds = $this->updatingRecordIds;

                $notUpdatingModules = array_diff($currentRecordIds["modules"], $currentRecordIds["modules"]);
                ScrutinyBoardModule::query()->whereIn("id", $notUpdatingModules)->delete();

                $notUpdatingExamTypes = array_diff($currentRecordIds["exam_types"], $updatingRecordIds["exam_types"]);
                ScrutinyBoardExamType::query()->whereIn("id", $notUpdatingExamTypes)->delete();

                if ($this->withPeople) {

                    $notUpdatingPeople = array_diff($currentRecordIds["people"], $updatingRecordIds["people"]);
                    ScrutinyBoardPeople::query()->whereIn("id", $notUpdatingPeople)->delete();
                }

                if ($this->withQuestions) {

                    $notUpdatingQuestions = array_diff($currentRecordIds["questions"], $updatingRecordIds["questions"]);
                    ScrutinyBoardQuestion::query()->whereIn("id", $notUpdatingQuestions)->delete();
                }
            }
        } catch (Exception $ex) {

            $error = $ex->getMessage();
            $errorLine = $ex->getLine();
            $success = false;
        }

        if ($success) {

            DB::commit();

            $response = [];
            $response["notify"]["status"] = "success";
            $response["notify"]["notify"][] = "Successfully saved the structure details";
        } else {

            DB::rollBack();

            $response = [];
            $response["notify"]["status"] = "failed";
            $response["notify"]["notify"][] = "Structure details saving was failed.";
            $response["error"] = $error ?? "";
            $response["line"] = $errorLine ?? "";
        }

        return $response;
    }

    /**
     * @param $sbModuleId
     * @param $examCategory
     * @return bool
     */
    private function _updateExamTypes($sbModuleId, $examCategory): bool
    {
        $becId = $examCategory["id"] ?? "";
        $examCategoryId = $examCategory["exam_category"]["id"] ?? "";
        $timeSlotId = $examCategory["time_slot"]["id"] ?? "";
        $activeStatus = $examCategory["active_status"]["id"] ?? 0;
        $marksPercentage = $examCategory["exam_category"]["marks_percentage"] ?? 0;
        $marksPercentage = intval($marksPercentage);
        $records = $examCategory["records"] ?? [];

        $success = true;
        if (is_array($records) && count($records) > 0) {

            foreach ($records as $record) {

                $prevTimeSlotId = null;
                if (!$this->createAssign && isset($record["id"]) && $record["id"] !== "") {

                    $sbmET = ScrutinyBoardExamType::query()->find($record["id"]);

                    if ($sbmET) {

                        $prevTimeSlotId = $sbmET->academic_timetable_information_id;
                        $this->updatingRecordIds["exam_types"][] = $sbmET->id;
                    } else {

                        $success = false;
                        break;
                    }
                } else {

                    $sbmET = new ScrutinyBoardExamType();
                    $sbmET->scrutiny_board_module_id = $sbModuleId;
                    $sbmET->exam_type_id = $record["exam_type_id"];
                    $sbmET->based_exam_category_id = $becId;
                    $sbmET->exam_method = $record["exam_method"];
                }

                $sbmET->active_status = $activeStatus;
                $sbmET->exam_category_id = $examCategoryId;
                $sbmET->marks_percentage = $marksPercentage;
                $sbmET->no_of_questions = $record["no_of_questions"];
                $sbmET->duration_in_minutes = $record["duration"]["id"] ?? "";
                $sbmET->examiner_type = $record["examiner_type"]["id"] ?? "";
                $sbmET->paper_typing = $record["paper_typing"]["id"] ?? "";
                $sbmET->paper_setting = $record["paper_setting"]["id"] ?? "";
                $sbmET->paper_marking = $record["paper_marking"]["id"] ?? "";

                if ($this->withPeople) {

                    $sbmET->academic_timetable_information_id = $timeSlotId;
                }

                $save = $sbmET->save();
                if ($save) {

                    if ($this->withPeople) {

                        $sbmETId = $sbmET->id;

                        $success = $this->_updateExamTypePeople($sbmETId, $record["people"]);

                        if (!$success) {
                            break;
                        }
                    }

                    if ($this->withQuestions) {

                        $sbmETId = $sbmET->id;

                        $newTimeSlotId = null;
                        if ($prevTimeSlotId !== $timeSlotId) {

                            $newTimeSlotId = $timeSlotId;
                        }

                        $success = $this->_updateExamTypeQuestions($sbmETId, $record["questions"], $newTimeSlotId);

                        if (!$success) {
                            break;
                        }
                    }
                } else {

                    $success = false;
                    break;
                }
            }
        }

        return $success;
    }

    /**
     * @param $sbmETId
     * @param $records
     * @return bool
     */
    private function _updateExamTypePeople($sbmETId, $records): bool
    {
        $success = true;
        if (is_array($records) && count($records) > 0) {

            $currentRecords = $this->currentPeopleRecords;

            foreach ($records as $examPersonType => $people) {

                if (is_array($people) && count($people) > 0) {

                    $sortOrder = 0;
                    foreach ($people as $person) {

                        $sortOrder++;
                        $personId = $person["id"];

                        if (isset($currentRecords[$sbmETId][$examPersonType][$personId])) {

                            $recordId = $currentRecords[$sbmETId][$examPersonType][$personId];

                            $this->updatingRecordIds["people"][] = $recordId;

                            $sbPerson = ScrutinyBoardPeople::query()->find($recordId);

                            if ($sbPerson) {

                                $sbPerson->sort_order = $sortOrder;

                                if (!$sbPerson->save()) {

                                    $success = false;
                                    break;
                                }
                            } else {

                                $success = false;
                                break;
                            }
                        } else {

                            $sbPerson = new ScrutinyBoardPeople();
                            $sbPerson->scrutiny_board_exam_type_id = $sbmETId;
                            $sbPerson->person_id = $personId;
                            $sbPerson->exam_person_type = $examPersonType;
                            $sbPerson->sort_order = $sortOrder;

                            if (!$sbPerson->save()) {

                                $success = false;
                                break;
                            }
                        }
                    }
                }

                if (!$success) {
                    break;
                }
            }
        }

        return $success;
    }

    /**
     * @param $sbmETId
     * @param $records
     * @param $newTimeSlotId
     * @return bool
     */
    private function _updateExamTypeQuestions($sbmETId, $records, $newTimeSlotId): bool
    {
        $success = true;
        if (is_array($records) && count($records) > 0) {

            $currentRecords = $this->currentQuestionRecords;

            foreach ($records as $question) {

                $questionId = $question["id"];

                if (!empty($questionId)) {

                    $questionId = intval($questionId);
                    if (isset($currentRecords[$sbmETId][$questionId])) {

                        $recordId = $currentRecords[$sbmETId][$questionId];

                        $this->updatingRecordIds["questions"][] = $recordId;
                    }

                    $sbQuestion = ScrutinyBoardQuestion::query()->find($questionId);
                } else {

                    $sbQuestion = new ScrutinyBoardQuestion();
                    $sbQuestion->scrutiny_board_exam_type_id = $sbmETId;
                }

                $sbQuestion->question_no = $question["question_no"];
                $sbQuestion->marks = $question["marks"];
                $sbQuestion->exam_category_id = $question["examCategory"]["id"] ?? null;
                $sbQuestion->question_made_by = $question["questionMadeBy"]["id"] ?? null;

                if ($newTimeSlotId !== null) {

                    //get Exam calendar id
                    $examCal = ExamCalendar::query()
                        ->where("academic_timetable_information_id", $newTimeSlotId)
                        ->first();

                    if ($examCal) {

                        $sbQuestion->exam_calendar_id = $examCal->id;
                    }
                }

                if (!$sbQuestion->save()) {

                    $success = false;
                }

                if (!$success) {
                    break;
                }
            }
        }

        return $success;
    }

    /**
     * @param $model
     * @return array
     */
    public function createAssignRecord($model): array
    {
        $model->load(["academicCalendar", "syllabus", "base", "base.modules", "base.modules.examTypes"]);
        $data = $this->getPreparedData($model->syllabus, $model->academic_calendar, false, $model->base);

        $this->createAssign = true;
        return $this->updateData($model, false, $data["records"]);
    }

    /**
     * @param $syllabus
     * @param $academicCalendar
     * @param $model
     * @param bool $withPeople
     * @return array
     */
    public function getPreparedDataForTable($syllabus, $academicCalendar, $model, $withPeople = false): array
    {
        $this->withPeople = $withPeople;
        $this->isForTable = true;

        $academicYearId = null;
        $semesterId = null;
        if ($academicCalendar) {

            $academicYearId = $academicCalendar->academic_year_id;
            $semesterId = $academicCalendar->semester_id;
        }

        $syllabusModules = $this->_getSyllabusModules($syllabus->id, $academicYearId, $semesterId);
        $sBModules = $this->_getSBModules($model);

        $examCategoriesById = Helper::convertArrayToObject(ExamCategoryRepository::getExamCategories(), "id");
        $examTypesById = Helper::convertArrayToObject(ExamTypeRepository::getExamTypes(), "id");

        $data = [];
        $data["records"] = [];

        if (is_array($syllabusModules) && count($syllabusModules) > 0) {

            foreach ($syllabusModules as $moduleId => $examTypes) {

                $record = [];
                $record["id"] = "";
                $record["module"] = $this->modules[$moduleId];
                $record["examTypes"] = [];

                if (is_array($examTypes) && count($examTypes) > 0) {

                    $sbExamTypes = [];
                    if (isset($sBModules[$moduleId])) {

                        $sBModule = $sBModules[$moduleId];

                        $record["id"] = $sBModule["id"];
                        $sbExamTypes = $sBModule["exam_types"];
                    }

                    foreach ($examTypes as $examTypeId => $examCategories) {

                        $examType = [];
                        $examType["id"] = $examTypeId;
                        $examType["exam_type"] = $examTypesById[$examTypeId];

                        foreach ($examCategories as $bec) {

                            $becId = $bec["id"];
                            $marksPercentage = $bec["marks_percentage"];

                            $basedExamCategory = $examCategoriesById[$becId];
                            $basedExamCategory["marks_percentage"] = $marksPercentage;

                            $bec = [];
                            $bec["id"] = $becId;
                            $bec["time_slot"] = [];
                            $bec["active_status"] = [];

                            $maxCount = 1;
                            $examMethodRecords = [];
                            foreach ($this->examMethods as $em) {

                                $examMethodId = $em["id"];

                                if ($this->withPeople) {
                                    $bec["time_slot"] = $sbExamTypes[$examMethodId][$examTypeId][$becId]["time_slot"] ?? [];
                                    $bec["active_status"] = $sbExamTypes[$examMethodId][$examTypeId][$becId]["active_status"] ?? [];
                                }

                                if (isset($sbExamTypes[$examMethodId][$examTypeId][$becId]["records"])) {

                                    if (count($sbExamTypes[$examMethodId][$examTypeId][$becId]["records"]) > 0) {

                                        $examCategory = $sbExamTypes[$examMethodId][$examTypeId][$becId]["exam_category"];
                                        $sbETs = $sbExamTypes[$examMethodId][$examTypeId][$becId]["records"];

                                        $count = count($sbETs);

                                        $qCount = 1; //set as 1 to overcome row issue in table
                                        if ($count > 0) {

                                            foreach ($sbETs as $key => $sbET) {

                                                if ($examMethodId === 1) {

                                                    //for onsite always get the syllabus marks percentage
                                                    $examCategory["marks_percentage"] = $marksPercentage;
                                                } else {

                                                    if (empty($examCategory["marks_percentage"])) {

                                                        $examCategory["marks_percentage"] = 0;
                                                    }
                                                }

                                                if ($key === 0) {

                                                    if (empty($bec["time_slot"]) && !empty($sbET["time_slot"])) {

                                                        $bec["time_slot"] = $this->_getTimeSlotPrepared($sbET["time_slot"]);;
                                                    }
                                                }

                                                $sbET["exam_category"] = $examCategory;

                                                if ($this->withQuestions) {

                                                    $qCount = $sbET["no_of_questions"];

                                                    if (count($sbET["questions"]) > 0) {

                                                        foreach ($sbET["questions"] as $question) {

                                                            $question["rowId"] = $sbET["id"];
                                                            $question["record"] = $sbET;

                                                            $examMethodRecords[$examMethodId][] = $question;

                                                            $this->_setQuestionRowSpans($moduleId, $examTypeId, $examMethodId, $becId, $question["rowId"]);
                                                            $this->_setExamCategoryRowSpans($moduleId, $examTypeId, $examMethodId, $becId);
                                                        }
                                                    }
                                                } else {

                                                    $examMethodRecords[$examMethodId][] = $sbET;
                                                    $this->_setExamCategoryRowSpans($moduleId, $examTypeId, $examMethodId, $becId);
                                                }
                                            }
                                        }

                                        if ($this->withQuestions) {

                                            if ($maxCount < $qCount) {
                                                $maxCount = $qCount;
                                            }
                                        } else {

                                            if ($maxCount < $count) {
                                                $maxCount = $count;
                                            }
                                        }
                                    } else {

                                        $sbET["time_slot"] = [];
                                        $sbET["exam_category"] = $basedExamCategory;

                                        $sbET = $this->_getEmptyExamType($examMethodId, $examTypeId, $becId);

                                        if (count($sbET["questions"]) > 0) {

                                            $rowId = 0;
                                            foreach ($sbET["questions"] as $question) {
                                                $rowId++;

                                                $question["rowId"] = $rowId;
                                                $question["record"] = $sbET;

                                                $examMethodRecords[$examMethodId][] = $question;

                                                $this->_setQuestionRowSpans($moduleId, $examTypeId, $examMethodId, $becId, $question["rowId"]);
                                                $this->_setExamCategoryRowSpans($moduleId, $examTypeId, $examMethodId, $becId);
                                            }
                                        }

                                        $examMethodRecords[$examMethodId][] = $sbET;
                                    }
                                }
                            }

                            if ($maxCount > 0) {

                                for($inc = 0; $inc < $maxCount; $inc++) {

                                    $this->_setModuleRowSpans($moduleId);
                                    $this->_setExamTypeRowSpans($moduleId, $examTypeId);
                                }
                            }

                            $bec["count"] = $maxCount;
                            $bec["records"] = $examMethodRecords;

                            $examType["examCategories"][] = $bec;
                        }

                        $record["examTypes"][] = $examType;
                    }
                }

                $data["records"][] = $record;
            }
        }

        $data["examMethods"] = $this->examMethods;
        $data["moduleRowSpans"] = $this->moduleRowSpans;
        $data["examTypeRowSpans"] = $this->examTypeRowSpans;
        $data["examCategoryRowSpans"] = $this->examCategoryRowSpans;
        $data["questionRowSpans"] = $this->questionRowSpans;

        return $data;
    }

    private function _setQuestionRowSpans($moduleId, $examTypeId, $examMethodId, $examCategoryId, $questionId)
    {
        if (!isset($this->questionRowSpans[$moduleId][$examTypeId][$examMethodId][$examCategoryId][$questionId])) {

            $this->questionRowSpans[$moduleId][$examTypeId][$examMethodId][$examCategoryId][$questionId] = 0;
        }

        $this->questionRowSpans[$moduleId][$examTypeId][$examMethodId][$examCategoryId][$questionId] += 1;
    }

    private function _setExamCategoryRowSpans($moduleId, $examTypeId, $examMethodId, $examCategoryId)
    {
        if (!isset($this->examCategoryRowSpans[$moduleId][$examTypeId][$examMethodId][$examCategoryId])) {

            $this->examCategoryRowSpans[$moduleId][$examTypeId][$examMethodId][$examCategoryId] = 0;
        }

        $this->examCategoryRowSpans[$moduleId][$examTypeId][$examMethodId][$examCategoryId] += 1;
    }

    private function _setExamTypeRowSpans($moduleId, $examTypeId)
    {
        if (!isset($this->examTypeRowSpans[$moduleId][$examTypeId])) {

            $this->examTypeRowSpans[$moduleId][$examTypeId] = 0;
        }

        $this->examTypeRowSpans[$moduleId][$examTypeId] += 1;
    }

    private function _setModuleRowSpans($moduleId)
    {
        if (!isset($this->moduleRowSpans[$moduleId])) {

            $this->moduleRowSpans[$moduleId] = 0;
        }

        $this->moduleRowSpans[$moduleId] += 1;
    }

    /**
     * @param $id
     * @param array $moduleIds
     * @return array
     */
    public static function getSBPeople($id, $moduleIds = []): array
    {
        $relations = [];
        $relations[] = "examTypes";
        $relations[] = "examTypes.people:id,scrutiny_board_exam_type_id,person_id,exam_person_type";
        $relations[] = "examTypes.people.person";

        $query = ScrutinyBoardModule::with($relations)
            ->where("scrutiny_board_id", $id);

        if(!empty($moduleIds)) {

            $query->whereIn("module_id", $moduleIds);
        }

        $records = $query->get()->toArray();

        $data = [];
        if ($records) {

            foreach ($records as $record) {

                $examTypes = $record["exam_types"];
                if (is_array($examTypes) && count($examTypes) > 0) {

                    foreach ($examTypes as $examType) {

                        $people = $examType["people"];

                        if (is_array($people) && count($people) > 0) {

                            foreach ($people as $personRecord) {

                                $data[] = $personRecord["person"];
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }
}
