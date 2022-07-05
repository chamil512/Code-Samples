<?php

namespace Modules\Academic\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\Storage;
use Modules\Academic\Entities\LecturerDocument;

class LecturerDocumentRepository extends BaseRepository
{
    private string $upload_dir = "public/lecturer_documents/";
    private string $formFileName = "file_name";
    private array $fileTypes = [
        ["type" => "application/pdf", "ext" => "pdf"],
        ["type" => "application/msword", "ext" => "doc"],
        ["type" => "application/vnd.openxmlformats-officedocument.wordprocessingml", "ext" => "docx"],
        ["type" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document", "ext" => "docx"],
        ["type" => "image/jpeg", "ext" => "jpg"],
        ["type" => "image/jpeg", "ext" => "jpg"],
        ["type" => "image/png", "ext" => "png"],
        ["type" => "image/gif", "ext" => "gif"],
        ["type" => "application/zip", "ext" => "zip"],
        ["type" => "application/x-zip-compressed", "ext" => "zip"],
    ];

    private array $currentRecords = [];

    public function update($lecturer)
    {
        $ids = request()->post("lc_doc_id");
        $documentTypes = request()->post("document_type");
        $documentDetails = request()->post("document_name");

        $currentIds = $this->getRecordIds($lecturer);
        $updatingIds = [];

        if (is_array($ids) && count($ids) > 0) {
            $fileKey = -1;
            foreach ($ids as $key => $id) {
                if ($id == "" || !in_array($id, $currentIds)) {
                    $record = new LecturerDocument();
                    $record->lecturer_id = $lecturer->id;
                    $record->document_type = $documentTypes[$key];
                    $record->document_name = $documentDetails[$key];

                    $fileKey++;

                    if ($this->isValidFile($fileKey)) {
                        $fileName = $_FILES[$this->formFileName]["name"][$fileKey];
                        $fileName = md5(microtime() . $fileName) . $fileName;

                        $record->file_name = $fileName;

                        if ($record->save()) {
                            $this->uploadDocument($fileName, $fileKey);
                        }
                    }
                } else {
                    $record = LecturerDocument::query()->find($id);
                    $record->document_name = $documentDetails[$key];

                    $record->save();

                    $updatingIds[] = $id;
                }
            }
        }

        $notUpdatingIds = array_diff($currentIds, $updatingIds);

        if (count($notUpdatingIds) > 0) {
            $lecturer->documents()->whereIn("id", $notUpdatingIds)->delete();
            $this->deleteNotUpdatingDocuments($notUpdatingIds);
        }
    }

    private function deleteNotUpdatingDocuments($notUpdatingIds)
    {
        $records = $this->currentRecords;

        if (count($records) > 0) {
            foreach ($records as $record) {
                if (in_array($record["id"], $notUpdatingIds)) {
                    $this->deleteDocument($record["file_name"]);
                }
            }
        }
    }

    public function getRecordIds($lecturer): array
    {
        $lecturerDocuments = $lecturer->documents->toArray();

        $ids = [];
        if (is_array($lecturerDocuments) && count($lecturerDocuments) > 0) {
            foreach ($lecturerDocuments as $record) {
                $ids[] = $record["id"];
            }
        }

        $this->currentRecords = $lecturerDocuments;

        return $ids;
    }

    private function uploadDocument($fileName, $key, $currFileName = "")
    {
        if (Storage::disk('local')->put($this->upload_dir . $fileName, file_get_contents($_FILES[$this->formFileName]["tmp_name"][$key]))) {
            $this->deleteDocument($currFileName);
        }
    }

    private function deleteDocument($fileName)
    {
        if ($fileName != "") {
            Storage::delete($this->upload_dir . $fileName);
        }
    }

    private function downloadDocument($fileName, $fileTitle = "")
    {
        if ($fileTitle == "") {
            $fileTitle = $fileName;
        }

        return Storage::download($this->upload_dir . $fileName, $fileTitle . "-" . $fileName);
    }

    public function triggerDownloadDocument($lecturer, $documentId)
    {
        $record = LecturerDocument::query()->find($documentId);

        if ($record) {
            $docName = str_replace(".", " ", $lecturer->name . "-" . $lecturer->id . "-") . $record->document_name;
            $docName = str_replace(["/", "\\"], "", $docName);

            return $this->downloadDocument($record->file_name, $docName);
        }

        abort(404);

        return false;
    }

    private function isValidFile($key): bool
    {
        $matchFound = false;
        $thisFileType = strtolower($_FILES[$this->formFileName]["type"][$key]);
        $expDel = "."; //end delimiter

        $fileName = $_FILES[$this->formFileName]["name"][$key];
        $fileName = explode($expDel, $fileName);
        $thisFileExt = strtolower(end($fileName));

        $fileTypes = $this->fileTypes;

        if (isset($fileTypes)) {
            foreach ($fileTypes as $fileType) {
                if ($thisFileType == strtolower($fileType["type"]) && $thisFileExt == strtolower($fileType["ext"])) {
                    $matchFound = true;
                    break;
                }
            }
        }

        return $matchFound;
    }
}
