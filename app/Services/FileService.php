<?php

namespace SegWeb\Services;

use SegWeb\File;

class FileService
{
    public function saveFile($user_id, $file_path, $original_file_name, $file_type, $repository_id = null)
    {
        $file = new File();

        $file->user_id = $user_id;

        $file->file_path = $file_path;

        $file->original_file_name = $original_file_name;

        $file->type = $file_type;

        if ($repository_id !== null) {
            $file->repository_id = $repository_id;
        }

        $file->save();

        return $file;
    }
}
